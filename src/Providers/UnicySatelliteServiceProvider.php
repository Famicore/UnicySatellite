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
            return new SatelliteHubService(
                config('satellite.hub.url'),
                config('satellite.hub.api_key')
            );
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

        // Auto-registration si activé
        if (config('satellite.sync.auto_register', true)) {
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
                $schedule->command('satellite:sync')
                    ->everyNMinutes(ceil($interval / 60))
                    ->withoutOverlapping()
                    ->runInBackground();
            }

            // Envoi de métriques
            if (config('satellite.metrics.enabled', true)) {
                $interval = config('satellite.metrics.interval', 60);
                $schedule->command('satellite:metrics')
                    ->everyNMinutes(ceil($interval / 60))
                    ->withoutOverlapping()
                    ->runInBackground();
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
                /** @var SatelliteHubService $hubService */
                $hubService = $this->app->make(SatelliteHubService::class);
                
                if ($hubService->shouldRegister()) {
                    $hubService->registerSatellite();
                }
            } catch (\Exception $e) {
                // Log l'erreur mais ne pas interrompre l'application
                logger()->warning('Auto-registration satellite failed', [
                    'error' => $e->getMessage(),
                    'satellite' => config('satellite.satellite.name')
                ]);
            }
        });
    }
} 