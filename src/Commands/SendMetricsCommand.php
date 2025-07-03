<?php

namespace UnicySatellite\Commands;

use Illuminate\Console\Command;
use UnicySatellite\Services\SatelliteHubService;
use UnicySatellite\Services\MetricsCollectorService;

class SendMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'satellite:metrics 
                            {--show : Afficher les métriques sans les envoyer}
                            {--detailed : Afficher les métriques détaillées}';

    /**
     * The console command description.
     */
    protected $description = 'Collecte et envoie les métriques vers UnicyHub';

    /**
     * Execute the console command.
     */
    public function handle(
        MetricsCollectorService $metricsCollector,
        SatelliteHubService $hubService
    ): int {
        if (!config('satellite.metrics.enabled', true)) {
            $this->warn('⚠️  Envoi de métriques désactivé dans la configuration');
            return self::SUCCESS;
        }

        $this->info('📊 Collecte des métriques...');

        try {
            $metrics = $metricsCollector->collectAll();

            if ($this->option('show') || $this->option('detailed')) {
                $this->displayMetrics($metrics);
                
                if ($this->option('show')) {
                    return self::SUCCESS;
                }
            }

            $this->info('📡 Envoi des métriques vers UnicyHub...');

            $success = $hubService->sendMetrics($metrics);

            if ($success) {
                $this->info('✅ Métriques envoyées avec succès');
                
                if (!$this->option('detailed')) {
                    $this->displaySummary($metrics);
                }
            } else {
                $this->error('❌ Échec de l\'envoi des métriques');
                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la collecte/envoi des métriques:');
            $this->error("   {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Affiche les métriques collectées
     */
    protected function displayMetrics(array $metrics): void
    {
        $this->newLine();
        $this->info('📋 Métriques collectées:');
        
        // Métriques générales
        $generalMetrics = [];
        
        if (isset($metrics['users_count'])) {
            $generalMetrics[] = ['Utilisateurs', number_format($metrics['users_count'])];
        }
        
        if (isset($metrics['tenants_count'])) {
            $generalMetrics[] = ['Tenants', number_format($metrics['tenants_count'])];
        }
        
        if (isset($metrics['active_sessions'])) {
            $generalMetrics[] = ['Sessions actives', number_format($metrics['active_sessions'])];
        }

        if (!empty($generalMetrics)) {
            $this->table(['Métrique', 'Valeur'], $generalMetrics);
        }

        // Métriques système
        if ($this->option('detailed')) {
            $this->displaySystemMetrics($metrics);
            $this->displayTypeSpecificMetrics($metrics);
        }
    }

    /**
     * Affiche les métriques système détaillées
     */
    protected function displaySystemMetrics(array $metrics): void
    {
        $systemMetrics = [];

        if (isset($metrics['memory_usage'])) {
            $memory = $metrics['memory_usage'];
            $systemMetrics[] = [
                'Mémoire', 
                $this->formatBytes($memory['current']) . ' / ' . $this->formatBytes($memory['limit']) . 
                ' (' . $memory['percent'] . '%)'
            ];
        }

        if (isset($metrics['cpu_usage']['percent'])) {
            $systemMetrics[] = ['CPU', $metrics['cpu_usage']['percent'] . '%'];
        }

        if (isset($metrics['disk_usage'])) {
            $disk = $metrics['disk_usage'];
            $systemMetrics[] = [
                'Disque', 
                $this->formatBytes($disk['used']) . ' / ' . $this->formatBytes($disk['total']) . 
                ' (' . $disk['percent'] . '%)'
            ];
        }

        if (!empty($systemMetrics)) {
            $this->newLine();
            $this->info('🖥️  Métriques système:');
            $this->table(['Ressource', 'Utilisation'], $systemMetrics);
        }
    }

    /**
     * Affiche les métriques spécifiques au type
     */
    protected function displayTypeSpecificMetrics(array $metrics): void
    {
        $type = config('satellite.satellite.type');
        $typeMetrics = [];

        switch ($type) {
            case 'logistik':
                if (isset($metrics['orders_today'])) {
                    $typeMetrics[] = ['Commandes du jour', number_format($metrics['orders_today'])];
                }
                if (isset($metrics['orders_pending'])) {
                    $typeMetrics[] = ['Commandes en attente', number_format($metrics['orders_pending'])];
                }
                if (isset($metrics['shipments_active'])) {
                    $typeMetrics[] = ['Expéditions actives', number_format($metrics['shipments_active'])];
                }
                break;

            case 'vinci':
                if (isset($metrics['brokers_active'])) {
                    $typeMetrics[] = ['Courtiers actifs', number_format($metrics['brokers_active'])];
                }
                if (isset($metrics['jobs_today'])) {
                    $typeMetrics[] = ['Jobs du jour', number_format($metrics['jobs_today'])];
                }
                if (isset($metrics['jobs_pending'])) {
                    $typeMetrics[] = ['Jobs en attente', number_format($metrics['jobs_pending'])];
                }
                break;

            case 'pixel':
                if (isset($metrics['qr_codes_today'])) {
                    $typeMetrics[] = ['QR codes du jour', number_format($metrics['qr_codes_today'])];
                }
                if (isset($metrics['scans_today'])) {
                    $typeMetrics[] = ['Scans du jour', number_format($metrics['scans_today'])];
                }
                break;
        }

        if (!empty($typeMetrics)) {
            $this->newLine();
            $this->info("📱 Métriques {$type}:");
            $this->table(['Métrique', 'Valeur'], $typeMetrics);
        }
    }

    /**
     * Affiche un résumé des métriques
     */
    protected function displaySummary(array $metrics): void
    {
        $this->newLine();
        $this->line("📈 Résumé: " . count($metrics) . " métriques envoyées pour " . 
                   config('satellite.satellite.name'));
    }

    /**
     * Formate les bytes en unités lisibles
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
} 