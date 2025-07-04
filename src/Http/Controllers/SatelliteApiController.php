<?php

namespace UnicySatellite\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use UnicySatellite\Services\MetricsCollectorService;
use UnicySatellite\Services\SatelliteHubService;
use UnicySatellite\Services\SensitiveDataDetector;

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
     * Tenants endpoint - toutes les informations sur les tenants
     */
    public function tenants(): JsonResponse
    {
        try {
            $tenants = $this->collectTenantsData();
            
            return response()->json([
                'success' => true,
                'total_tenants' => $tenants['count'],
                'tenants' => $tenants['data'],
                'stats' => $tenants['stats'],
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
     * Performance metrics endpoint
     */
    public function performance(): JsonResponse
    {
        try {
            $performance = $this->collectPerformanceMetrics();
            
            return response()->json([
                'success' => true,
                'performance' => $performance,
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
     * Sensitive keys endpoint - Détection automatique des données sensibles
     */
    public function sensitiveKeys(SensitiveDataDetector $detector, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'show_raw_values' => 'sometimes|boolean',
                'include_stats' => 'sometimes|boolean'
            ]);

            $showRawValues = $validated['show_raw_values'] ?? false;
            $includeStats = $validated['include_stats'] ?? false;

            // Scanner le fichier .env pour détecter les données sensibles
            $sensitiveData = $detector->scanEnvironmentFile($showRawValues);
            
            $response = [
                'success' => true,
                'sensitive_keys' => $sensitiveData,
                'count' => count($sensitiveData),
                'timestamp' => now()->toISOString(),
                'satellite' => config('satellite.satellite.name')
            ];

            // Ajouter les statistiques de sécurité si demandées
            if ($includeStats) {
                $response['security_stats'] = $detector->getSecurityStats();
                $response['patterns_info'] = [
                    'sensitive_patterns_count' => count($detector->getSensitivePatterns()),
                    'ignored_variables_count' => count($detector->getIgnoredVariables()),
                ];
            }

            return response()->json($response);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to scan sensitive data: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
                'satellite' => config('satellite.satellite.name')
            ], 500);
        }
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
     * Collect comprehensive tenants data
     */
    protected function collectTenantsData(): array
    {
        $tenants = [];
        $stats = [
            'active' => 0,
            'inactive' => 0,
            'total_users' => 0,
            'total_activity' => 0
        ];

        // Vérifier si le modèle Tenant existe (pour les apps avec tenancy)
        if (class_exists('\App\Models\Tenant')) {
            $tenantModel = '\App\Models\Tenant';
            $allTenants = $tenantModel::all();
            
            foreach ($allTenants as $tenant) {
                $tenantData = [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? $tenant->id,
                    'domain' => $tenant->domain ?? null,
                    'status' => $tenant->status ?? 'active',
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                ];

                // Collecter les statistiques spécifiques par tenant
                $tenantStats = $this->getTenantSpecificStats($tenant);
                $tenantData['stats'] = $tenantStats;

                $tenants[] = $tenantData;
                
                // Agrégation des stats globales
                if ($tenantData['status'] === 'active') {
                    $stats['active']++;
                } else {
                    $stats['inactive']++;
                }
                
                $stats['total_users'] += $tenantStats['users_count'] ?? 0;
                $stats['total_activity'] += $tenantStats['activity_count'] ?? 0;
            }
        } else {
            // Pas de système multi-tenant, retourner les données globales
            $tenants[] = [
                'id' => 'default',
                'name' => config('app.name'),
                'domain' => config('app.url'),
                'status' => 'active',
                'stats' => $this->getGlobalStats(),
                'created_at' => null,
                'updated_at' => now()
            ];
            $stats['active'] = 1;
        }

        return [
            'count' => count($tenants),
            'data' => $tenants,
            'stats' => $stats
        ];
    }

    /**
     * Collect detailed performance metrics
     */
    protected function collectPerformanceMetrics(): array
    {
        return [
            'system' => $this->getSystemPerformance(),
            'application' => $this->getApplicationPerformance(),
            'database' => $this->getDatabasePerformance(),
            'cache' => $this->getCachePerformance(),
            'queue' => $this->getQueuePerformance(),
            'network' => $this->getNetworkPerformance()
        ];
    }

    /**
     * Get tenant-specific statistics
     */
    protected function getTenantSpecificStats($tenant): array
    {
        $stats = [
            'users_count' => 0,
            'activity_count' => 0,
            'storage_usage' => 0,
            'last_activity' => null
        ];

        try {
            // Compter les utilisateurs du tenant
            if (method_exists($tenant, 'users')) {
                $stats['users_count'] = $tenant->users()->count();
                $stats['last_activity'] = $tenant->users()
                    ->whereNotNull('last_login_at')
                    ->max('last_login_at');
            }

            // Stats spécifiques selon le type d'application
            $satelliteType = config('satellite.satellite.type');
            switch ($satelliteType) {
                case 'logistik':
                    $stats['orders_count'] = $this->getTenantOrders($tenant);
                    $stats['shipments_count'] = $this->getTenantShipments($tenant);
                    break;
                case 'vinci':
                    $stats['brokers_count'] = $this->getTenantBrokers($tenant);
                    $stats['jobs_count'] = $this->getTenantJobs($tenant);
                    break;
                case 'pixel':
                    $stats['qr_codes_count'] = $this->getTenantQRCodes($tenant);
                    $stats['scans_count'] = $this->getTenantScans($tenant);
                    break;
            }

        } catch (\Exception $e) {
            // Log l'erreur mais continuer
            logger()->warning('Error collecting tenant stats', [
                'tenant' => $tenant->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }

        return $stats;
    }

    /**
     * Get global application statistics (non-tenant)
     */
    protected function getGlobalStats(): array
    {
        $stats = [
            'users_count' => 0,
            'activity_count' => 0,
            'storage_usage' => $this->getStorageUsage()
        ];

        try {
            // Compter les utilisateurs globaux
            if (class_exists('\App\Models\User')) {
                $stats['users_count'] = \App\Models\User::count();
                $stats['last_activity'] = \App\Models\User::whereNotNull('last_login_at')
                    ->max('last_login_at');
            }

            // Stats spécifiques selon le type
            $satelliteType = config('satellite.satellite.type');
            switch ($satelliteType) {
                case 'logistik':
                    $stats['orders_count'] = $this->getGlobalOrders();
                    $stats['shipments_count'] = $this->getGlobalShipments();
                    break;
                case 'vinci':
                    $stats['brokers_count'] = $this->getGlobalBrokers();
                    $stats['jobs_count'] = $this->getGlobalJobs();
                    break;
                case 'pixel':
                    $stats['qr_codes_count'] = $this->getGlobalQRCodes();
                    $stats['scans_count'] = $this->getGlobalScans();
                    break;
            }

        } catch (\Exception $e) {
            logger()->warning('Error collecting global stats', [
                'error' => $e->getMessage()
            ]);
        }

        return $stats;
    }

    /**
     * Get system performance metrics
     */
    protected function getSystemPerformance(): array
    {
        return [
            'memory' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
                'usage_percentage' => round((memory_get_usage(true) / $this->parseMemoryLimit(ini_get('memory_limit'))) * 100, 2)
            ],
            'cpu' => [
                'load_average' => sys_getloadavg() ?: [0, 0, 0],
                'cores' => $this->getCpuCores()
            ],
            'disk' => [
                'free' => disk_free_space('/'),
                'total' => disk_total_space('/'),
                'used_percentage' => round((1 - disk_free_space('/') / disk_total_space('/')) * 100, 2)
            ],
            'uptime' => $this->getUptime()
        ];
    }

    /**
     * Get application performance metrics
     */
    protected function getApplicationPerformance(): array
    {
        return [
            'response_time' => [
                'average' => Cache::get('app.response_time.average', 0),
                'min' => Cache::get('app.response_time.min', 0),
                'max' => Cache::get('app.response_time.max', 0)
            ],
            'requests' => [
                'total' => Cache::get('app.requests.total', 0),
                'per_minute' => Cache::get('app.requests.per_minute', 0),
                'errors' => Cache::get('app.requests.errors', 0)
            ],
            'sessions' => [
                'active' => $this->getActiveSessions(),
                'total' => Cache::get('app.sessions.total', 0)
            ]
        ];
    }

    /**
     * Get database performance metrics
     */
    protected function getDatabasePerformance(): array
    {
        try {
            $queryLog = \DB::getQueryLog();
            $queryCount = count($queryLog);
            $slowQueries = collect($queryLog)->where('time', '>', 1000)->count();

            return [
                'queries' => [
                    'total' => $queryCount,
                    'slow' => $slowQueries,
                    'average_time' => $queryCount > 0 ? collect($queryLog)->avg('time') : 0
                ],
                'connections' => [
                    'active' => count(\DB::getConnections()),
                    'max' => config('database.connections.mysql.pool.max_connections', 100)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'queries' => ['total' => 0, 'slow' => 0, 'average_time' => 0],
                'connections' => ['active' => 0, 'max' => 0],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get cache performance metrics
     */
    protected function getCachePerformance(): array
    {
        try {
            return [
                'hit_rate' => Cache::get('cache.hit_rate', 0),
                'miss_rate' => Cache::get('cache.miss_rate', 0),
                'memory_usage' => Cache::get('cache.memory_usage', 0),
                'keys_count' => Cache::get('cache.keys_count', 0)
            ];
        } catch (\Exception $e) {
            return [
                'hit_rate' => 0,
                'miss_rate' => 0,
                'memory_usage' => 0,
                'keys_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get queue performance metrics
     */
    protected function getQueuePerformance(): array
    {
        try {
            return [
                'pending' => Cache::get('queue.pending', 0),
                'processing' => Cache::get('queue.processing', 0),
                'failed' => Cache::get('queue.failed', 0),
                'completed' => Cache::get('queue.completed', 0)
            ];
        } catch (\Exception $e) {
            return [
                'pending' => 0,
                'processing' => 0,
                'failed' => 0,
                'completed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get network performance metrics
     */
    protected function getNetworkPerformance(): array
    {
        return [
            'latency' => Cache::get('network.latency', 0),
            'throughput' => Cache::get('network.throughput', 0),
            'external_apis' => [
                'unicyhub' => [
                    'status' => Cache::get('api.unicyhub.status', 'unknown'),
                    'response_time' => Cache::get('api.unicyhub.response_time', 0)
                ]
            ]
        ];
    }

    // Helper methods pour les stats spécifiques par type d'application
    protected function getTenantOrders($tenant): int { return 0; }
    protected function getTenantShipments($tenant): int { return 0; }
    protected function getTenantBrokers($tenant): int { return 0; }
    protected function getTenantJobs($tenant): int { return 0; }
    protected function getTenantQRCodes($tenant): int { return 0; }
    protected function getTenantScans($tenant): int { return 0; }
    
    protected function getGlobalOrders(): int { return 0; }
    protected function getGlobalShipments(): int { return 0; }
    protected function getGlobalBrokers(): int { return 0; }
    protected function getGlobalJobs(): int { return 0; }
    protected function getGlobalQRCodes(): int { return 0; }
    protected function getGlobalScans(): int { return 0; }

    protected function getStorageUsage(): int
    {
        try {
            return disk_total_space(storage_path()) - disk_free_space(storage_path());
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int) $limit;
        
        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }

    protected function getCpuCores(): int
    {
        return (int) shell_exec('nproc 2>/dev/null') ?: 1;
    }

    protected function getActiveSessions(): int
    {
        try {
            // Approximation basée sur les sessions Laravel
            $sessionFiles = glob(storage_path('framework/sessions/*'));
            return count($sessionFiles ?: []);
        } catch (\Exception $e) {
            return 0;
        }
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