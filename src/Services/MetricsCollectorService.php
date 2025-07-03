<?php

namespace UnicySatellite\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MetricsCollectorService
{
    /**
     * Collecte toutes les métriques configurées
     */
    public function collectAll(): array
    {
        $metrics = [];
        $config = config('satellite.metrics.include', []);

        if ($config['users_count'] ?? false) {
            $metrics['users_count'] = $this->getUsersCount();
        }

        if ($config['tenants_count'] ?? false) {
            $metrics['tenants_count'] = $this->getTenantsCount();
        }

        if ($config['active_sessions'] ?? false) {
            $metrics['active_sessions'] = $this->getActiveSessionsCount();
        }

        if ($config['memory_usage'] ?? false) {
            $metrics['memory_usage'] = $this->getMemoryUsage();
        }

        if ($config['cpu_usage'] ?? false) {
            $metrics['cpu_usage'] = $this->getCpuUsage();
        }

        if ($config['disk_usage'] ?? false) {
            $metrics['disk_usage'] = $this->getDiskUsage();
        }

        // Ajouter des métriques spécifiques selon le type de satellite
        $metrics = array_merge($metrics, $this->getTypeSpecificMetrics());

        // Ajouter des métadonnées
        $metrics['timestamp'] = now()->toISOString();
        $metrics['satellite_name'] = config('satellite.satellite.name');
        $metrics['satellite_type'] = config('satellite.satellite.type');

        return $metrics;
    }

    /**
     * Collecte le nombre d'utilisateurs
     */
    protected function getUsersCount(): int
    {
        try {
            if (class_exists(\App\Models\User::class)) {
                return \App\Models\User::count();
            }
            
            // Fallback: compter directement en base
            return DB::table('users')->count();
        } catch (\Exception $e) {
            logger()->warning('Failed to collect users count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Collecte le nombre de tenants
     */
    protected function getTenantsCount(): int
    {
        try {
            // Essayer d'utiliser le modèle Tenant s'il existe
            if (class_exists(\App\Models\Tenant::class)) {
                return \App\Models\Tenant::count();
            }
            
            // Fallback: chercher une table tenants
            if (DB::getSchemaBuilder()->hasTable('tenants')) {
                return DB::table('tenants')->count();
            }
            
            return 0;
        } catch (\Exception $e) {
            logger()->warning('Failed to collect tenants count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Collecte le nombre de sessions actives
     */
    protected function getActiveSessionsCount(): int
    {
        try {
            // Essayer Redis d'abord
            if (config('session.driver') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys(config('session.cookie') . '*');
                return count($keys);
            }
            
            // Fallback: sessions en base de données
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                return DB::table('sessions')
                    ->where('last_activity', '>', now()->subMinutes(5)->timestamp)
                    ->count();
            }
            
            return 0;
        } catch (\Exception $e) {
            logger()->warning('Failed to collect active sessions count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Collecte l'utilisation mémoire
     */
    protected function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->getMemoryLimit(),
            'percent' => round((memory_get_usage(true) / $this->getMemoryLimit()) * 100, 2)
        ];
    }

    /**
     * Récupère la limite mémoire
     */
    protected function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        // Convertir la notation PHP (128M, 1G, etc.) en bytes
        $unit = strtoupper(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int) $limit;
        }
    }

    /**
     * Collecte l'utilisation CPU (approximative)
     */
    protected function getCpuUsage(): array
    {
        // Sur Linux/Unix, on peut utiliser /proc/stat
        if (file_exists('/proc/stat')) {
            $stat1 = file_get_contents('/proc/stat');
            usleep(100000); // 0.1 seconde
            $stat2 = file_get_contents('/proc/stat');
            
            $info1 = explode(' ', preg_replace('!cpu +!', '', explode("\n", $stat1)[0]));
            $info2 = explode(' ', preg_replace('!cpu +!', '', explode("\n", $stat2)[0]));
            
            $dif = [];
            $dif['user'] = $info2[0] - $info1[0];
            $dif['nice'] = $info2[1] - $info1[1];
            $dif['sys'] = $info2[2] - $info1[2];
            $dif['idle'] = $info2[3] - $info1[3];
            $total = array_sum($dif);
            
            return [
                'percent' => round(100 - (($dif['idle'] / $total) * 100), 2),
                'user' => round(($dif['user'] / $total) * 100, 2),
                'system' => round(($dif['sys'] / $total) * 100, 2)
            ];
        }
        
        // Fallback basique
        return [
            'percent' => round(sys_getloadavg()[0] * 100, 2),
            'load_average' => sys_getloadavg()
        ];
    }

    /**
     * Collecte l'utilisation disque
     */
    protected function getDiskUsage(): array
    {
        $bytes = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $used = $total - $bytes;
        
        return [
            'free' => $bytes,
            'used' => $used,
            'total' => $total,
            'percent' => round(($used / $total) * 100, 2)
        ];
    }

    /**
     * Collecte des métriques spécifiques selon le type de satellite
     */
    protected function getTypeSpecificMetrics(): array
    {
        $type = config('satellite.satellite.type');
        
        switch ($type) {
            case 'logistik':
                return $this->getLogistikMetrics();
            case 'vinci':
                return $this->getVinciMetrics();
            case 'pixel':
                return $this->getPixelMetrics();
            default:
                return [];
        }
    }

    /**
     * Métriques spécifiques pour UnicyLogistik
     */
    protected function getLogistikMetrics(): array
    {
        $metrics = [];
        
        try {
            // Commandes du jour
            if (DB::getSchemaBuilder()->hasTable('orders')) {
                $metrics['orders_today'] = DB::table('orders')
                    ->whereDate('created_at', today())
                    ->count();
                    
                $metrics['orders_pending'] = DB::table('orders')
                    ->where('status', 'pending')
                    ->count();
            }
            
            // Expéditions actives
            if (DB::getSchemaBuilder()->hasTable('shipments')) {
                $metrics['shipments_active'] = DB::table('shipments')
                    ->whereIn('status', ['in_transit', 'processing'])
                    ->count();
            }
            
        } catch (\Exception $e) {
            logger()->warning('Failed to collect logistik metrics', ['error' => $e->getMessage()]);
        }
        
        return $metrics;
    }

    /**
     * Métriques spécifiques pour UnicyVinci
     */
    protected function getVinciMetrics(): array
    {
        $metrics = [];
        
        try {
            // Courtiers actifs
            if (DB::getSchemaBuilder()->hasTable('brokers')) {
                $metrics['brokers_active'] = DB::table('brokers')
                    ->where('status', 'active')
                    ->count();
            }
            
            // Jobs du jour
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $metrics['jobs_today'] = DB::table('jobs')
                    ->whereDate('created_at', today())
                    ->count();
                    
                $metrics['jobs_pending'] = DB::table('jobs')
                    ->where('status', 'pending')
                    ->count();
            }
            
        } catch (\Exception $e) {
            logger()->warning('Failed to collect vinci metrics', ['error' => $e->getMessage()]);
        }
        
        return $metrics;
    }

    /**
     * Métriques spécifiques pour UnicyPixel
     */
    protected function getPixelMetrics(): array
    {
        $metrics = [];
        
        try {
            // QR codes du jour
            if (DB::getSchemaBuilder()->hasTable('qr_codes')) {
                $metrics['qr_codes_today'] = DB::table('qr_codes')
                    ->whereDate('created_at', today())
                    ->count();
            }
            
            // Scans du jour
            if (DB::getSchemaBuilder()->hasTable('qr_scans')) {
                $metrics['scans_today'] = DB::table('qr_scans')
                    ->whereDate('created_at', today())
                    ->count();
            }
            
        } catch (\Exception $e) {
            logger()->warning('Failed to collect pixel metrics', ['error' => $e->getMessage()]);
        }
        
        return $metrics;
    }
} 