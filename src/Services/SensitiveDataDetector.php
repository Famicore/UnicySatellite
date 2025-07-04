<?php

namespace UnicySatellite\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Service de détection automatique des données sensibles dans le fichier .env
 * 
 * Ce service scanne le fichier .env et détecte automatiquement toutes les variables
 * qui contiennent des données sensibles (mots de passe, clés API, tokens, etc.)
 * 
 * @package UnicySatellite\Services
 * @version 1.0.0
 * @author Unicy Development Team
 */
class SensitiveDataDetector
{
    /**
     * Patterns de détection des variables sensibles
     * 
     * @var array
     */
    protected array $sensitivePatterns = [
        // Mots de passe
        'password', 'passwd', 'pwd', 'pass',
        
        // Clés API
        'api_key', 'apikey', 'api_secret', 'apisecret',
        'client_key', 'client_secret', 'client_id',
        'consumer_key', 'consumer_secret',
        
        // Tokens
        'token', 'access_token', 'refresh_token', 'bearer_token',
        'auth_token', 'session_token', 'csrf_token',
        
        // Secrets
        'secret', 'private_key', 'private', 'key',
        'encryption_key', 'app_key', 'cipher_key',
        
        // JWT
        'jwt_secret', 'jwt_key', 'jwt_private', 'jwt_public',
        
        // OAuth
        'oauth_token', 'oauth_secret', 'oauth_key',
        
        // Base de données
        'db_password', 'database_password', 'mysql_password',
        'postgres_password', 'mongo_password', 'redis_password',
        
        // Services externes
        'stripe_secret', 'stripe_key', 'paypal_secret',
        'aws_secret', 'aws_key', 'aws_access_key',
        'google_secret', 'google_key', 'facebook_secret',
        'twitter_secret', 'github_secret', 'gitlab_secret',
        
        // Mail
        'mail_password', 'smtp_password', 'email_password',
        
        // Webhooks
        'webhook_secret', 'webhook_key', 'webhook_token',
        
        // Licence
        'license_key', 'licence_key', 'activation_key',
        
        // Encryption
        'encryption_key', 'cipher_key', 'hash_key',
        'salt', 'pepper', 'nonce',
        
        // HMAC
        'hmac_key', 'hmac_secret', 'signature_key',
        
        // Certificats
        'certificate', 'cert_key', 'ssl_key', 'tls_key',
        'public_key', 'private_key', 'ca_key',
    ];

    /**
     * Extensions de valeurs qui indiquent des données sensibles
     * 
     * @var array
     */
    protected array $sensitiveValuePatterns = [
        '/^sk_/',           // Stripe secret keys
        '/^pk_/',           // Stripe publishable keys (parfois sensibles)
        '/^rk_/',           // Stripe restricted keys
        '/^whsec_/',        // Stripe webhook secrets
        '/^xoxb-/',         // Slack bot tokens
        '/^xoxp-/',         // Slack user tokens
        '/^ghp_/',          // GitHub personal access tokens
        '/^gho_/',          // GitHub OAuth tokens
        '/^glpat-/',        // GitLab personal access tokens
        '/^base64:/',       // Laravel app keys
        '/^-----BEGIN/',    // Certificates/Private keys
        '/^ey[A-Za-z0-9]/', // JWT tokens
        '/^[A-Za-z0-9+\/]+=*$/', // Base64 encoded (si long)
    ];

    /**
     * Variables à ignorer même si elles matchent les patterns
     * 
     * @var array
     */
    protected array $ignoredVariables = [
        'app_debug',
        'app_env',
        'app_name',
        'app_url',
        'log_level',
        'broadcast_driver',
        'cache_driver',
        'filesystem_driver',
        'queue_connection',
        'session_driver',
        'session_lifetime',
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'redis_host',
        'redis_port',
        'memcached_host',
    ];

    /**
     * Scanne le fichier .env et détecte automatiquement les données sensibles
     * 
     * @param bool $showRawValues Afficher les vraies valeurs ou les masquer
     * @return array Tableau des variables sensibles détectées
     */
    public function scanEnvironmentFile(bool $showRawValues = false): array
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            Log::warning('[SensitiveDataDetector] Fichier .env introuvable', ['path' => $envPath]);
            return [];
        }

        try {
            $envContent = File::get($envPath);
            $variables = $this->parseEnvContent($envContent);
            $sensitiveVars = $this->detectSensitiveVariables($variables);
            
            Log::info('[SensitiveDataDetector] Scan terminé', [
                'total_variables' => count($variables),
                'sensitive_variables' => count($sensitiveVars),
                'variables_found' => array_keys($sensitiveVars)
            ]);
            
            if ($showRawValues) {
                return $sensitiveVars;
            } else {
                return $this->maskSensitiveValues($sensitiveVars);
            }
            
        } catch (\Exception $e) {
            Log::error('[SensitiveDataDetector] Erreur lors du scan', [
                'error' => $e->getMessage(),
                'file' => $envPath
            ]);
            
            return [];
        }
    }

    /**
     * Parse le contenu du fichier .env
     * 
     * @param string $content Contenu du fichier .env
     * @return array Variables extraites
     */
    protected function parseEnvContent(string $content): array
    {
        $variables = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorer les commentaires et lignes vides
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Extraire la variable
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Nettoyer les guillemets
                $value = trim($value, '"\'');
                
                if (!empty($key) && !empty($value)) {
                    $variables[strtolower($key)] = $value;
                }
            }
        }
        
        return $variables;
    }

    /**
     * Détecte les variables sensibles basées sur les patterns
     * 
     * @param array $variables Variables à analyser
     * @return array Variables sensibles détectées
     */
    protected function detectSensitiveVariables(array $variables): array
    {
        $sensitiveVars = [];
        
        foreach ($variables as $key => $value) {
            $originalKey = $key;
            $key = strtolower($key);
            
            // Ignorer les variables dans la liste d'ignore
            if (in_array($key, array_map('strtolower', $this->ignoredVariables))) {
                continue;
            }
            
            $isSensitive = false;
            
            // Vérifier les patterns de noms
            foreach ($this->sensitivePatterns as $pattern) {
                if (Str::contains($key, strtolower($pattern))) {
                    $isSensitive = true;
                    break;
                }
            }
            
            // Vérifier les patterns de valeurs
            if (!$isSensitive) {
                foreach ($this->sensitiveValuePatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $isSensitive = true;
                        break;
                    }
                }
            }
            
            // Vérifier la longueur et complexité (possibles clés/tokens)
            if (!$isSensitive && $this->looksLikeSensitiveValue($value)) {
                $isSensitive = true;
            }
            
            if ($isSensitive) {
                $sensitiveVars[$originalKey] = $value;
            }
        }
        
        return $sensitiveVars;
    }

    /**
     * Vérifie si une valeur ressemble à des données sensibles
     * 
     * @param string $value Valeur à analyser
     * @return bool True si la valeur semble sensible
     */
    protected function looksLikeSensitiveValue(string $value): bool
    {
        // Valeurs longues avec caractères spéciaux (possibles tokens/clés)
        if (strlen($value) >= 32 && preg_match('/[A-Za-z0-9+\/=_-]{32,}/', $value)) {
            return true;
        }
        
        // Valeurs avec format base64
        if (strlen($value) >= 20 && preg_match('/^[A-Za-z0-9+\/]+=*$/', $value)) {
            return true;
        }
        
        // Valeurs hexadécimales longues
        if (strlen($value) >= 32 && preg_match('/^[a-fA-F0-9]{32,}$/', $value)) {
            return true;
        }
        
        return false;
    }

    /**
     * Masque les valeurs sensibles pour la sécurité
     * 
     * @param array $sensitiveVars Variables sensibles
     * @return array Variables avec valeurs masquées
     */
    protected function maskSensitiveValues(array $sensitiveVars): array
    {
        $maskedVars = [];
        
        foreach ($sensitiveVars as $key => $value) {
            $maskedVars[$key] = $this->maskValue($value);
        }
        
        return $maskedVars;
    }

    /**
     * Masque une valeur sensible
     * 
     * @param string|null $value Valeur à masquer
     * @return string Valeur masquée
     */
    protected function maskValue(?string $value): string
    {
        if (empty($value)) {
            return 'Not set';
        }
        
        $length = strlen($value);
        
        // Valeurs courtes : masquer complètement
        if ($length <= 8) {
            return str_repeat('•', $length);
        }
        
        // Valeurs longues : afficher début et fin
        return substr($value, 0, 4) . str_repeat('•', $length - 8) . substr($value, -4);
    }

    /**
     * Ajoute un pattern personnalisé pour la détection
     * 
     * @param string|array $patterns Pattern(s) à ajouter
     * @return void
     */
    public function addSensitivePattern($patterns): void
    {
        if (is_string($patterns)) {
            $patterns = [$patterns];
        }
        
        $this->sensitivePatterns = array_merge($this->sensitivePatterns, $patterns);
    }

    /**
     * Ajoute une variable à ignorer
     * 
     * @param string|array $variables Variable(s) à ignorer
     * @return void
     */
    public function addIgnoredVariable($variables): void
    {
        if (is_string($variables)) {
            $variables = [$variables];
        }
        
        $this->ignoredVariables = array_merge($this->ignoredVariables, $variables);
    }

    /**
     * Obtient la liste des patterns sensibles
     * 
     * @return array Liste des patterns
     */
    public function getSensitivePatterns(): array
    {
        return $this->sensitivePatterns;
    }

    /**
     * Obtient la liste des variables ignorées
     * 
     * @return array Liste des variables ignorées
     */
    public function getIgnoredVariables(): array
    {
        return $this->ignoredVariables;
    }

    /**
     * Analyse et retourne des statistiques sur la sécurité
     * 
     * @return array Statistiques de sécurité
     */
    public function getSecurityStats(): array
    {
        $allVars = $this->parseEnvContent(File::get(base_path('.env')));
        $sensitiveVars = $this->detectSensitiveVariables($allVars);
        
        return [
            'total_variables' => count($allVars),
            'sensitive_variables' => count($sensitiveVars),
            'security_ratio' => count($allVars) > 0 ? round((count($sensitiveVars) / count($allVars)) * 100, 2) : 0,
            'sensitive_variable_names' => array_keys($sensitiveVars),
            'scan_timestamp' => now()->toISOString(),
        ];
    }
} 