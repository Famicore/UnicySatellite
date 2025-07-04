<?php

namespace UnicySatellite\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use UnicySatellite\Services\SatelliteHubService;
use UnicySatellite\Services\MetricsCollectorService;
use UnicySatellite\Services\SyncService;
use UnicySatellite\Commands\RegisterSatelliteCommand;
use UnicySatellite\Commands\SyncWithHubCommand;
use UnicySatellite\Commands\SendMetricsCommand;
use UnicySatellite\Middleware\SatelliteAuthMiddleware;

class UnicySatelliteServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/satellite.php',
            'satellite'
        );

        // Register services
        $this->app->singleton(SatelliteHubService::class, function ($app) {
            $hubUrl = config('satellite.hub.url', '');
            $apiKey = config('satellite.hub.api_key', '');
            
            // Don't instantiate if critical config is missing
            if (empty($hubUrl) || empty($apiKey)) {
                throw new \RuntimeException(
                    'UnicySatellite configuration missing. Please run: php artisan vendor:publish --tag="satellite-config" and configure your .env file'
                );
            }
            
            return new SatelliteHubService($hubUrl, $apiKey);
        });

        $this->app->singleton(MetricsCollectorService::class);
        $this->app->singleton(SyncService::class);

        // Register facade
        $this->app->alias(SatelliteHubService::class, 'satellite-hub');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publier la configuration
        $this->publishes([
            __DIR__ . '/../../config/satellite.php' => config_path('satellite.php'),
        ], 'satellite-config');

        // Publier les migrations
        $this->publishesMigrations([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'satellite-migrations');

        // Publier les routes
        $this->publishes([
            __DIR__ . '/../../routes' => base_path('routes/satellite'),
        ], 'satellite-routes');

        // Charger les routes API
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Enregistrer les commandes
        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterSatelliteCommand::class,
                SyncWithHubCommand::class,
                SendMetricsCommand::class,
            ]);
        }

        // Enregistrer le middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('satellite.auth', SatelliteAuthMiddleware::class);

        // Programmer les tâches automatiques
        $this->scheduleAutomaticTasks();

        // Auto-registration si activé (seulement si la configuration est complète)
        if (config('satellite.sync.auto_register', true) 
            && config('satellite.hub.url') 
            && config('satellite.hub.api_key')
        ) {
            $this->autoRegisterSatellite();
        }
    }

    /**
     * Programme les tâches automatiques de synchronisation et métriques
     */
    protected function scheduleAutomaticTasks(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Synchronisation périodique
            if (config('satellite.sync.enabled', true)) {
                $interval = config('satellite.sync.interval', 300);
                $intervalMinutes = ceil($interval / 60);
                
                $syncEvent = $schedule->command('satellite:sync');
                
                if ($intervalMinutes <= 1) {
                    $syncEvent->everyMinute();
                } elseif ($intervalMinutes <= 5) {
                    $syncEvent->everyFiveMinutes();
                } elseif ($intervalMinutes <= 10) {
                    $syncEvent->everyTenMinutes();
                } elseif ($intervalMinutes <= 15) {
                    $syncEvent->everyFifteenMinutes();
                } elseif ($intervalMinutes <= 30) {
                    $syncEvent->everyThirtyMinutes();
                } else {
                    $syncEvent->hourly();
                }
                
                $syncEvent->withoutOverlapping()->runInBackground();
            }

            // Envoi de métriques
            if (config('satellite.metrics.enabled', true)) {
                $interval = config('satellite.metrics.interval', 60);
                $intervalMinutes = ceil($interval / 60);
                
                $metricsEvent = $schedule->command('satellite:metrics');
                
                if ($intervalMinutes <= 1) {
                    $metricsEvent->everyMinute();
                } elseif ($intervalMinutes <= 5) {
                    $metricsEvent->everyFiveMinutes();
                } elseif ($intervalMinutes <= 10) {
                    $metricsEvent->everyTenMinutes();
                } elseif ($intervalMinutes <= 15) {
                    $metricsEvent->everyFifteenMinutes();
                } elseif ($intervalMinutes <= 30) {
                    $metricsEvent->everyThirtyMinutes();
                } else {
                    $metricsEvent->hourly();
                }
                
                $metricsEvent->withoutOverlapping()->runInBackground();
            }
        });
    }

    /**
     * Auto-enregistrement du satellite auprès d'UnicyHub
     */
    protected function autoRegisterSatellite(): void
    {
        $this->app->booted(function () {
            try {
                // Double vérification de la configuration avant d'essayer
                if (empty(config('satellite.hub.url')) || empty(config('satellite.hub.api_key'))) {
                    logger()->info('Satellite auto-registration skipped - configuration incomplete');
                    return;
                }
                
                /** @var SatelliteHubService $hubService */
                $hubService = $this->app->make(SatelliteHubService::class);
                
                if ($hubService->shouldRegister()) {
                    $hubService->registerSatellite();
                }
            } catch (\Exception $e) {
                // Log l'erreur mais ne pas interrompre l'application
                logger()->warning('Auto-registration satellite failed', [
                    'error' => $e->getMessage(),
                    'satellite' => config('satellite.satellite.name', 'unknown')
                ]);
            }
        });
    }
} 