<?php

namespace UnicySatellite\Commands;

use Illuminate\Console\Command;
use UnicySatellite\Services\SatelliteHubService;
use UnicySatellite\Exceptions\SatelliteException;

class RegisterSatelliteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'satellite:register 
                            {--force : Force re-registration even if already registered}
                            {--test : Test registration without actually registering}';

    /**
     * The console command description.
     */
    protected $description = 'Enregistre ce satellite auprès d\'UnicyHub central';

    /**
     * Execute the console command.
     */
    public function handle(SatelliteHubService $hubService): int
    {
        $this->info('🛰️  Enregistrement du satellite auprès d\'UnicyHub...');
        $this->newLine();

        // Vérifier la configuration
        if (!$this->validateConfiguration()) {
            return self::FAILURE;
        }

        // Afficher les informations du satellite
        $this->displaySatelliteInfo();

        // Mode test
        if ($this->option('test')) {
            $this->warn('⚠️  Mode test activé - aucune donnée ne sera envoyée');
            $this->info('✅ Configuration valide pour l\'enregistrement');
            return self::SUCCESS;
        }

        // Vérifier si déjà enregistré
        if (!$this->option('force') && !$hubService->shouldRegister()) {
            $this->warn('ℹ️  Satellite déjà enregistré récemment');
            $this->line('   Utilisez --force pour forcer le re-enregistrement');
            return self::SUCCESS;
        }

        try {
            // Enregistrement
            $this->info('📡 Envoi des données d\'enregistrement...');
            
            $result = $hubService->registerSatellite();

            $this->newLine();
            $this->info('✅ Satellite enregistré avec succès !');
            
            if (isset($result['satellite_id'])) {
                $this->line("   📋 ID Satellite: {$result['satellite_id']}");
            }
            
            if (isset($result['status'])) {
                $this->line("   📊 Statut: {$result['status']}");
            }

            $this->newLine();
            $this->info('🔄 Le satellite va maintenant synchroniser automatiquement avec UnicyHub');

            return self::SUCCESS;

        } catch (SatelliteException $e) {
            $this->error('❌ Échec de l\'enregistrement:');
            $this->error("   {$e->getMessage()}");
            
            $this->newLine();
            $this->warn('💡 Vérifiez:');
            $this->line('   • La configuration UNICYHUB_URL');
            $this->line('   • La clé API UNICYHUB_API_KEY');
            $this->line('   • La connectivité réseau vers UnicyHub');

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('❌ Erreur inattendue:');
            $this->error("   {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Valide la configuration nécessaire
     */
    protected function validateConfiguration(): bool
    {
        $errors = [];

        if (!config('satellite.hub.url')) {
            $errors[] = 'UNICYHUB_URL non configuré';
        }

        if (!config('satellite.hub.api_key')) {
            $errors[] = 'UNICYHUB_API_KEY non configuré';
        }

        if (!config('satellite.satellite.name')) {
            $errors[] = 'SATELLITE_NAME non configuré';
        }

        if (!config('satellite.satellite.type')) {
            $errors[] = 'SATELLITE_TYPE non configuré';
        }

        if (!empty($errors)) {
            $this->error('❌ Configuration manquante:');
            foreach ($errors as $error) {
                $this->error("   • {$error}");
            }
            $this->newLine();
            $this->warn('💡 Configurez ces variables dans votre fichier .env');
            return false;
        }

        return true;
    }

    /**
     * Affiche les informations du satellite
     */
    protected function displaySatelliteInfo(): void
    {
        $this->info('📋 Informations du satellite:');
        $this->table(
            ['Propriété', 'Valeur'],
            [
                ['Nom', config('satellite.satellite.name')],
                ['Type', config('satellite.satellite.type')],
                ['Version', config('satellite.satellite.version')],
                ['URL', config('satellite.satellite.url')],
                ['Hub URL', config('satellite.hub.url')],
                ['Sync activé', config('satellite.sync.enabled') ? '✅ Oui' : '❌ Non'],
                ['Métriques activées', config('satellite.metrics.enabled') ? '✅ Oui' : '❌ Non'],
            ]
        );
        $this->newLine();
    }
} 