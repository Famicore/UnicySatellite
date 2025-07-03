<?php

namespace UnicySatellite\Commands;

use Illuminate\Console\Command;
use UnicySatellite\Services\SyncService;

class SyncWithHubCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'satellite:sync 
                            {--type=all : Type de données à synchroniser (all, tenants, users)}
                            {--batch=100 : Taille des lots pour la synchronisation}
                            {--dry-run : Simulation sans envoi réel}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronise les données avec UnicyHub central';

    /**
     * Execute the console command.
     */
    public function handle(SyncService $syncService): int
    {
        if (!config('satellite.sync.enabled', true)) {
            $this->warn('⚠️  Synchronisation désactivée dans la configuration');
            return self::SUCCESS;
        }

        $this->info('🔄 Synchronisation avec UnicyHub...');
        $this->newLine();

        $type = $this->option('type');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('⚠️  Mode simulation activé - aucune donnée ne sera envoyée');
            $this->newLine();
        }

        try {
            $results = match($type) {
                'tenants' => ['tenants' => $syncService->syncTenants()],
                'users' => ['users' => $syncService->syncUsers()],
                'all' => $syncService->syncAll(),
                default => $syncService->syncAll()
            };

            $this->displayResults($results);

            // Vérifier s'il y a eu des erreurs
            $hasErrors = collect($results)->contains(function ($result) {
                return is_array($result) && ($result['status'] ?? '') === 'error';
            });

            if ($hasErrors) {
                $this->newLine();
                $this->error('⚠️  Certaines synchronisations ont échoué');
                return self::FAILURE;
            }

            $this->newLine();
            $this->info('✅ Synchronisation terminée avec succès');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la synchronisation:');
            $this->error("   {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Affiche les résultats de synchronisation
     */
    protected function displayResults(array $results): void
    {
        $this->info('📊 Résultats de synchronisation:');
        $this->newLine();

        $tableData = [];

        foreach ($results as $type => $result) {
            if (is_array($result)) {
                $status = $this->formatStatus($result['status'] ?? 'unknown');
                $count = $result['count'] ?? 'N/A';
                $message = $result['reason'] ?? $result['message'] ?? '';

                $tableData[] = [
                    ucfirst($type),
                    $status,
                    $count,
                    $message
                ];
            }
        }

        if (!empty($tableData)) {
            $this->table(
                ['Type', 'Statut', 'Éléments', 'Message'],
                $tableData
            );
        }
    }

    /**
     * Formate le statut avec des émojis
     */
    protected function formatStatus(string $status): string
    {
        return match($status) {
            'success' => '✅ Succès',
            'failed' => '❌ Échec',
            'error' => '🚨 Erreur',
            'skipped' => '⏭️  Ignoré',
            default => "❓ {$status}"
        };
    }
} 