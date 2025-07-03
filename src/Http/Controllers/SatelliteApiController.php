<?php

namespace UnicySatellite\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use UnicySatellite\Services\MetricsCollectorService;
use UnicySatellite\Services\SatelliteHubService;

class SatelliteApiController extends Controller
{
    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        try {
            /** @var SatelliteHubService $hubService */
            $hubService = app(SatelliteHubService::class);
            $checks = $hubService->performHealthChecks();
            
            $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'healthy') 
                ? 'healthy' 
                : 'degraded';
            
            return response()->json([
                'status' => $overallStatus,
                'timestamp' => now()->toISOString(),
                'satellite' => config('satellite.satellite.name'),
                'checks' => $checks
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'satellite' => config('satellite.satellite.name')
            ], 500);
        }
    }

    /**
     * Status endpoint
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'satellite' => [
                'name' => config('satellite.satellite.name'),
                'type' => config('satellite.satellite.type'),
                'version' => config('satellite.satellite.version'),
                'url' => config('satellite.satellite.url'),
            ],
            'sync' => [
                'enabled' => config('satellite.sync.enabled'),
                'last_sync' => Cache::get('satellite.last_sync'),
                'interval' => config('satellite.sync.interval'),
            ],
            'metrics' => [
                'enabled' => config('satellite.metrics.enabled'),
                'last_sent' => Cache::get('satellite.last_metrics_sent'),
                'interval' => config('satellite.metrics.interval'),
            ],
            'uptime' => $this->getUptime(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Metrics endpoint
     */
    public function metrics(MetricsCollectorService $metricsCollector): JsonResponse
    {
        try {
            $metrics = $metricsCollector->collectAll();
            
            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Execute command endpoint
     */
    public function executeCommand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string',
            'parameters' => 'array'
        ]);

        $command = $validated['command'];
        $parameters = $validated['parameters'] ?? [];

        // Liste des commandes autorisées pour la sécurité
        $allowedCommands = [
            'satellite:sync',
            'satellite:metrics', 
            'cache:clear',
            'config:cache',
            'route:cache',
            'view:cache'
        ];

        if (!in_array($command, $allowedCommands)) {
            return response()->json([
                'success' => false,
                'error' => 'Command not allowed',
                'allowed_commands' => $allowedCommands
            ], 403);
        }

        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'command' => $command,
                'parameters' => $parameters,
                'exit_code' => $exitCode,
                'output' => $output,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $command,
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Receive updates endpoint
     */
    public function receiveUpdates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.type' => 'required|string',
            'updates.*.data' => 'required|array'
        ]);

        $updates = $validated['updates'];
        $results = [];

        foreach ($updates as $update) {
            try {
                $result = $this->processUpdate($update);
                $results[] = [
                    'type' => $update['type'],
                    'success' => true,
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'type' => $update['type'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'processed' => count($updates),
            'results' => $results,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clear cache endpoint
     */
    public function clearCache(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tags' => 'sometimes|array',
            'keys' => 'sometimes|array',
            'all' => 'sometimes|boolean'
        ]);

        try {
            if ($validated['all'] ?? false) {
                Cache::flush();
                $message = 'All cache cleared';
            } elseif (isset($validated['tags'])) {
                Cache::tags($validated['tags'])->flush();
                $message = 'Cache cleared for tags: ' . implode(', ', $validated['tags']);
            } elseif (isset($validated['keys'])) {
                Cache::deleteMultiple($validated['keys']);
                $message = 'Cache cleared for keys: ' . implode(', ', $validated['keys']);
            } else {
                // Clear satellite specific cache
                Cache::tags(['satellite', 'sync', 'unicyhub'])->flush();
                $message = 'Satellite cache cleared';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Information endpoint
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'satellite' => [
                'name' => config('satellite.satellite.name'),
                'type' => config('satellite.satellite.type'),
                'version' => config('satellite.satellite.version'),
                'url' => config('satellite.satellite.url'),
                'api_prefix' => config('satellite.satellite.api_prefix'),
            ],
            'laravel' => [
                'version' => app()->version(),
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'os' => PHP_OS,
                'hostname' => gethostname(),
            ],
            'capabilities' => $this->getCapabilities(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Process a single update
     */
    protected function processUpdate(array $update): string
    {
        $type = $update['type'];
        $data = $update['data'];

        switch ($type) {
            case 'config_update':
                return $this->processConfigUpdate($data);
            case 'cache_clear':
                return $this->processCacheClear($data);
            case 'tenant_update':
                return $this->processTenantUpdate($data);
            default:
                throw new \InvalidArgumentException("Unknown update type: {$type}");
        }
    }

    /**
     * Process config update
     */
    protected function processConfigUpdate(array $data): string
    {
        // Mettre à jour la configuration en cache
        if (isset($data['key']) && isset($data['value'])) {
            config([$data['key'] => $data['value']]);
            return "Config updated: {$data['key']}";
        }
        return 'Config update processed';
    }

    /**
     * Process cache clear request
     */
    protected function processCacheClear(array $data): string
    {
        if (isset($data['tags'])) {
            Cache::tags($data['tags'])->flush();
            return 'Cache cleared for tags: ' . implode(', ', $data['tags']);
        }
        
        if (isset($data['keys'])) {
            Cache::deleteMultiple($data['keys']);
            return 'Cache cleared for keys: ' . implode(', ', $data['keys']);
        }
        
        Cache::flush();
        return 'All cache cleared';
    }

    /**
     * Process tenant update
     */
    protected function processTenantUpdate(array $data): string
    {
        // Cette méthode sera adaptée selon les besoins spécifiques
        // de chaque application satellite
        return 'Tenant update processed';
    }

    /**
     * Get uptime information
     */
    protected function getUptime(): array
    {
        $uptimeFile = storage_path('framework/down');
        
        if (file_exists($uptimeFile)) {
            return [
                'status' => 'maintenance',
                'since' => filemtime($uptimeFile)
            ];
        }

        // Approximation basée sur le cache
        $bootTime = Cache::remember('app_boot_time', 86400, fn() => now());
        
        return [
            'status' => 'up',
            'since' => $bootTime,
            'duration' => now()->diffInSeconds($bootTime)
        ];
    }

    /**
     * Get satellite capabilities
     */
    protected function getCapabilities(): array
    {
        $capabilities = [
            'health_monitoring' => config('satellite.health.enabled', true),
            'metrics_reporting' => config('satellite.metrics.enabled', true),
            'remote_commands' => true,
            'cache_management' => true,
            'sync_support' => config('satellite.sync.enabled', true),
        ];

        // Ajouter des capacités spécifiques selon le type
        $type = config('satellite.satellite.type');
        switch ($type) {
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
} 