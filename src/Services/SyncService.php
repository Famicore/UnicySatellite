<?php

namespace UnicySatellite\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class SyncService
{
    protected SatelliteHubService $hubService;

    public function __construct(SatelliteHubService $hubService)
    {
        $this->hubService = $hubService;
    }

    /**
     * Synchronise toutes les données configurées
     */
    public function syncAll(): array
    {
        $results = [];

        try {
            // Synchroniser les tenants
            $results['tenants'] = $this->syncTenants();

            // Synchroniser les utilisateurs
            $results['users'] = $this->syncUsers();

            // Synchroniser les données spécifiques selon le type
            $results['type_specific'] = $this->syncTypeSpecificData();

            Log::info('Full sync completed', [
                'satellite' => config('satellite.satellite.name'),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Full sync failed', [
                'satellite' => config('satellite.satellite.name'),
                'error' => $e->getMessage()
            ]);

            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Synchronise les tenants
     */
    public function syncTenants(): array
    {
        try {
            $tenants = $this->collectTenants();
            
            if ($tenants->isEmpty()) {
                return ['status' => 'skipped', 'reason' => 'No tenants found'];
            }

            $success = $this->hubService->syncData($tenants->toArray(), 'tenants');

            return [
                'status' => $success ? 'success' : 'failed',
                'count' => $tenants->count()
            ];

        } catch (\Exception $e) {
            Log::error('Tenant sync failed', [
                'satellite' => config('satellite.satellite.name'),
                'error' => $e->getMessage()
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Synchronise les utilisateurs
     */
    public function syncUsers(): array
    {
        try {
            $users = $this->collectUsers();
            
            if ($users->isEmpty()) {
                return ['status' => 'skipped', 'reason' => 'No users found'];
            }

            $success = $this->hubService->syncData($users->toArray(), 'users');

            return [
                'status' => $success ? 'success' : 'failed',
                'count' => $users->count()
            ];

        } catch (\Exception $e) {
            Log::error('User sync failed', [
                'satellite' => config('satellite.satellite.name'),
                'error' => $e->getMessage()
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Synchronise les données spécifiques selon le type
     */
    public function syncTypeSpecificData(): array
    {
        $type = config('satellite.satellite.type');
        
        switch ($type) {
            case 'logistik':
                return $this->syncLogistikData();
            case 'vinci':
                return $this->syncVinciData();
            case 'pixel':
                return $this->syncPixelData();
            default:
                return ['status' => 'skipped', 'reason' => 'No type-specific data configured'];
        }
    }

    /**
     * Collecte les tenants pour synchronisation
     */
    protected function collectTenants(): Collection
    {
        try {
            if (class_exists(\App\Models\Tenant::class)) {
                return \App\Models\Tenant::select([
                    'id', 'name', 'slug', 'domain', 'status', 
                    'created_at', 'updated_at'
                ])->get();
            }
            
            if (DB::getSchemaBuilder()->hasTable('tenants')) {
                return collect(DB::table('tenants')->get());
            }
            
            return collect();

        } catch (\Exception $e) {
            Log::warning('Failed to collect tenants', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Collecte les utilisateurs pour synchronisation
     */
    protected function collectUsers(): Collection
    {
        try {
            if (class_exists(\App\Models\User::class)) {
                return \App\Models\User::select([
                    'id', 'name', 'email', 'email_verified_at',
                    'created_at', 'updated_at'
                ])->get();
            }
            
            return collect(DB::table('users')
                ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'])
                ->get());

        } catch (\Exception $e) {
            Log::warning('Failed to collect users', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Synchronise les données spécifiques UnicyLogistik
     */
    protected function syncLogistikData(): array
    {
        $results = [];

        try {
            // Synchroniser les commandes récentes
            if (DB::getSchemaBuilder()->hasTable('orders')) {
                $orders = collect(DB::table('orders')
                    ->where('updated_at', '>=', now()->subDays(7))
                    ->select(['id', 'status', 'total', 'customer_id', 'created_at', 'updated_at'])
                    ->get());

                if ($orders->isNotEmpty()) {
                    $success = $this->hubService->syncData($orders->toArray(), 'orders');
                    $results['orders'] = [
                        'status' => $success ? 'success' : 'failed',
                        'count' => $orders->count()
                    ];
                }
            }

            // Synchroniser les expéditions actives
            if (DB::getSchemaBuilder()->hasTable('shipments')) {
                $shipments = collect(DB::table('shipments')
                    ->whereIn('status', ['processing', 'in_transit', 'delivered'])
                    ->where('updated_at', '>=', now()->subDays(7))
                    ->select(['id', 'order_id', 'status', 'tracking_number', 'created_at', 'updated_at'])
                    ->get());

                if ($shipments->isNotEmpty()) {
                    $success = $this->hubService->syncData($shipments->toArray(), 'shipments');
                    $results['shipments'] = [
                        'status' => $success ? 'success' : 'failed',
                        'count' => $shipments->count()
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Logistik data sync failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Synchronise les données spécifiques UnicyVinci
     */
    protected function syncVinciData(): array
    {
        $results = [];

        try {
            // Synchroniser les courtiers
            if (DB::getSchemaBuilder()->hasTable('brokers')) {
                $brokers = collect(DB::table('brokers')
                    ->where('status', 'active')
                    ->select(['id', 'name', 'email', 'status', 'commission_rate', 'created_at', 'updated_at'])
                    ->get());

                if ($brokers->isNotEmpty()) {
                    $success = $this->hubService->syncData($brokers->toArray(), 'brokers');
                    $results['brokers'] = [
                        'status' => $success ? 'success' : 'failed',
                        'count' => $brokers->count()
                    ];
                }
            }

            // Synchroniser les jobs récents
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $jobs = collect(DB::table('jobs')
                    ->where('updated_at', '>=', now()->subDays(7))
                    ->select(['id', 'title', 'status', 'broker_id', 'value', 'created_at', 'updated_at'])
                    ->get());

                if ($jobs->isNotEmpty()) {
                    $success = $this->hubService->syncData($jobs->toArray(), 'jobs');
                    $results['jobs'] = [
                        'status' => $success ? 'success' : 'failed',
                        'count' => $jobs->count()
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Vinci data sync failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Synchronise les données spécifiques UnicyPixel
     */
    protected function syncPixelData(): array
    {
        $results = [];

        try {
            // Synchroniser les QR codes récents
            if (DB::getSchemaBuilder()->hasTable('qr_codes')) {
                $qrCodes = collect(DB::table('qr_codes')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->select(['id', 'code', 'type', 'data', 'scan_count', 'created_at', 'updated_at'])
                    ->get());

                if ($qrCodes->isNotEmpty()) {
                    $success = $this->hubService->syncData($qrCodes->toArray(), 'qr_codes');
                    $results['qr_codes'] = [
                        'status' => $success ? 'success' : 'failed',
                        'count' => $qrCodes->count()
                    ];
                }
            }

            // Synchroniser les scans récents
            if (DB::getSchemaBuilder()->hasTable('qr_scans')) {
                $scans = collect(DB::table('qr_scans')
                    ->where('created_at', '>=', now()->subDays(1))
                    ->select(['id', 'qr_code_id', 'user_id', 'ip_address', 'user_agent', 'created_at'])
                    ->get());

                if ($scans->isNotEmpty()) {
                    $success = $this->hubService->syncData($scans->toArray(), 'qr_scans');
                    $results['qr_scans'] = [
                        'status' => $success ? 'success' : 'failed',
                        'count' => $scans->count()
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Pixel data sync failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
} 