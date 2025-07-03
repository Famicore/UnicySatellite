<?php

namespace UnicySatellite\Services;

use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowsOnErrors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use UnicySatellite\Exceptions\SatelliteException;
use UnicySatellite\Http\Integrations\UnicyHubConnector;
use UnicySatellite\Http\Requests\RegisterSatelliteRequest;
use UnicySatellite\Http\Requests\SendMetricsRequest;
use UnicySatellite\Http\Requests\SyncDataRequest;
use UnicySatellite\Http\Requests\HealthCheckRequest;

class SatelliteHubService
{
    protected UnicyHubConnector $connector;
    protected string $hubUrl;
    protected string $apiKey;

    public function __construct(string $hubUrl, string $apiKey)
    {
        $this->hubUrl = $hubUrl;
        $this->apiKey = $apiKey;
        $this->connector = new UnicyHubConnector($hubUrl, $apiKey);
    }

    /**
     * Vérifie si le satellite doit s'enregistrer
     */
    public function shouldRegister(): bool
    {
        if (!config('satellite.sync.enabled', true)) {
            return false;
        }

        // Vérifier si déjà enregistré récemment
        $lastRegistration = Cache::get('satellite.last_registration');
        
        if ($lastRegistration && now()->diffInHours($lastRegistration) < 24) {
            return false;
        }

        return true;
    }

    /**
     * Enregistre ce satellite auprès d'UnicyHub
     */
    public function registerSatellite(): array
    {
        try {
            $data = [
                'name' => config('satellite.satellite.name'),
                'type' => config('satellite.satellite.type'),
                'version' => config('satellite.satellite.version'),
                'url' => config('satellite.satellite.url'),
                'api_prefix' => config('satellite.satellite.api_prefix'),
                'capabilities' => $this->getSatelliteCapabilities(),
                'health_endpoint' => config('satellite.health.endpoint'),
                'metrics_enabled' => config('satellite.metrics.enabled'),
                'sync_enabled' => config('satellite.sync.enabled'),
            ];

            $request = new RegisterSatelliteRequest($data);
            $response = $this->connector->send($request);

            $result = $response->json();

            // Sauvegarder les informations d'enregistrement
            Cache::put('satellite.registration_data', $result, now()->addDays(1));
            Cache::put('satellite.last_registration', now(), now()->addDays(1));

            Log::info('Satellite registered successfully', [
                'satellite_id' => $result['satellite_id'] ?? null,
                'name' => config('satellite.satellite.name')
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to register satellite', [
                'error' => $e->getMessage(),
                'satellite' => config('satellite.satellite.name')
            ]);

            throw new SatelliteException("Registration failed: " . $e->getMessage());
        }
    }

    /**
     * Envoie les métriques vers UnicyHub
     */
    public function sendMetrics(array $metrics): bool
    {
        try {
            $data = [
                'satellite_name' => config('satellite.satellite.name'),
                'timestamp' => now()->toISOString(),
                'metrics' => $metrics,
            ];

            $request = new SendMetricsRequest($data);
            $response = $this->connector->send($request);

            Log::debug('Metrics sent successfully', [
                'satellite' => config('satellite.satellite.name'),
                'metrics_count' => count($metrics)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::warning('Failed to send metrics', [
                'error' => $e->getMessage(),
                'satellite' => config('satellite.satellite.name')
            ]);

            return false;
        }
    }

    /**
     * Synchronise les données avec UnicyHub
     */
    public function syncData(array $data, string $type = 'tenants'): bool
    {
        try {
            $payload = [
                'satellite_name' => config('satellite.satellite.name'),
                'type' => $type,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ];

            $request = new SyncDataRequest($payload);
            $response = $this->connector->send($request);

            $result = $response->json();

            // Traiter les données de retour si nécessaire
            if (isset($result['updates'])) {
                $this->processRemoteUpdates($result['updates']);
            }

            Log::info('Data synchronized successfully', [
                'satellite' => config('satellite.satellite.name'),
                'type' => $type,
                'count' => count($data)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to sync data', [
                'error' => $e->getMessage(),
                'satellite' => config('satellite.satellite.name'),
                'type' => $type
            ]);

            return false;
        }
    }

    /**
     * Effectue un health check avec UnicyHub
     */
    public function healthCheck(): array
    {
        try {
            $data = [
                'satellite_name' => config('satellite.satellite.name'),
                'timestamp' => now()->toISOString(),
                'status' => 'healthy',
                'checks' => $this->performHealthChecks(),
            ];

            $request = new HealthCheckRequest($data);
            $response = $this->connector->send($request);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'satellite' => config('satellite.satellite.name')
            ]);

            throw new SatelliteException("Health check failed: " . $e->getMessage());
        }
    }

    /**
     * Récupère les capacités de ce satellite
     */
    protected function getSatelliteCapabilities(): array
    {
        $capabilities = [
            'tenant_management' => true,
            'user_sync' => true,
            'metrics_reporting' => config('satellite.metrics.enabled', true),
            'health_monitoring' => config('satellite.health.enabled', true),
            'remote_commands' => true,
        ];

        // Ajouter des capacités spécifiques selon le type
        switch (config('satellite.satellite.type')) {
            case 'logistik':
                $capabilities['order_management'] = true;
                $capabilities['shipping_tracking'] = true;
                break;
            case 'vinci':
                $capabilities['broker_management'] = true;
                $capabilities['job_tracking'] = true;
                break;
            case 'pixel':
                $capabilities['qr_generation'] = true;
                $capabilities['scan_tracking'] = true;
                break;
        }

        return $capabilities;
    }

    /**
     * Effectue les checks de santé locaux
     */
    public function performHealthChecks(): array
    {
        $checks = [];

        if (config('satellite.health.checks.database', true)) {
            $checks['database'] = $this->checkDatabase();
        }

        if (config('satellite.health.checks.cache', true)) {
            $checks['cache'] = $this->checkCache();
        }

        if (config('satellite.health.checks.storage', true)) {
            $checks['storage'] = $this->checkStorage();
        }

        if (config('satellite.health.checks.queue', true)) {
            $checks['queue'] = $this->checkQueue();
        }

        return $checks;
    }

    /**
     * Vérifie la santé de la base de données
     */
    protected function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Vérifie la santé du cache
     */
    protected function checkCache(): array
    {
        try {
            Cache::put('health_check', 'test', 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            
            return $value === 'test' 
                ? ['status' => 'healthy', 'message' => 'Cache working OK']
                : ['status' => 'warning', 'message' => 'Cache read/write issue'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache failed: ' . $e->getMessage()];
        }
    }

    /**
     * Vérifie la santé du storage
     */
    protected function checkStorage(): array
    {
        try {
            $diskSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usagePercent = round((($totalSpace - $diskSpace) / $totalSpace) * 100, 2);

            $status = $usagePercent > 90 ? 'warning' : 'healthy';
            
            return [
                'status' => $status,
                'message' => "Disk usage: {$usagePercent}%",
                'free_space' => $diskSpace,
                'total_space' => $totalSpace
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Vérifie la santé des queues
     */
    protected function checkQueue(): array
    {
        try {
            // Simple check pour voir si les queues sont configurées
            $queueDriver = config('queue.default');
            
            return [
                'status' => 'healthy',
                'message' => "Queue driver: {$queueDriver}",
                'driver' => $queueDriver
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Queue check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Traite les mises à jour distantes reçues d'UnicyHub
     */
    protected function processRemoteUpdates(array $updates): void
    {
        foreach ($updates as $update) {
            try {
                switch ($update['type']) {
                    case 'tenant_update':
                        $this->processTenantUpdate($update['data']);
                        break;
                    case 'user_update':
                        $this->processUserUpdate($update['data']);
                        break;
                    case 'config_update':
                        $this->processConfigUpdate($update['data']);
                        break;
                    default:
                        Log::warning('Unknown update type received', [
                            'type' => $update['type'],
                            'data' => $update['data']
                        ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to process remote update', [
                    'type' => $update['type'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Traite une mise à jour de tenant
     */
    protected function processTenantUpdate(array $data): void
    {
        // Cette méthode sera implémentée selon les besoins spécifiques
        // de chaque application satellite
        Log::info('Processing tenant update', $data);
    }

    /**
     * Traite une mise à jour d'utilisateur
     */
    protected function processUserUpdate(array $data): void
    {
        // Cette méthode sera implémentée selon les besoins spécifiques
        // de chaque application satellite
        Log::info('Processing user update', $data);
    }

    /**
     * Traite une mise à jour de configuration
     */
    protected function processConfigUpdate(array $data): void
    {
        // Cette méthode sera implémentée selon les besoins spécifiques
        // de chaque application satellite
        Log::info('Processing config update', $data);
    }
} 