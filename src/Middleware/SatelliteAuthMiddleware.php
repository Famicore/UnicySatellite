<?php

namespace UnicySatellite\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class SatelliteAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): ResponseAlias
    {
        // Vérifier si les endpoints satellites sont activés
        if (!config('satellite.satellite.enabled', true)) {
            return response()->json([
                'error' => 'Satellite endpoints disabled',
                'message' => 'Les endpoints satellites sont désactivés'
            ], 503);
        }

        // Vérifier la clé API
        $apiKey = $this->extractApiKey($request);
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'Missing API key',
                'message' => 'Clé API manquante'
            ], 401);
        }

        if (!$this->isValidApiKey($apiKey)) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'Clé API invalide'
            ], 401);
        }

        // Vérifier les limites de taux
        if ($this->isRateLimited($request)) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Limite de taux dépassée'
            ], 429);
        }

        // Vérifier la liste blanche IP si configurée
        if (!$this->isIpAllowed($request)) {
            return response()->json([
                'error' => 'IP not allowed',
                'message' => 'Adresse IP non autorisée'
            ], 403);
        }

        // Ajouter des informations au request pour les contrôleurs
        $request->merge([
            'satellite_authenticated' => true,
            'satellite_api_key' => $apiKey
        ]);

        return $next($request);
    }

    /**
     * Extrait la clé API de la requête
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Bearer token dans Authorization
        if ($request->hasHeader('Authorization')) {
            $authorization = $request->header('Authorization');
            if (str_starts_with($authorization, 'Bearer ')) {
                return substr($authorization, 7);
            }
        }

        // Header X-API-Key
        if ($request->hasHeader('X-API-Key')) {
            return $request->header('X-API-Key');
        }

        // Paramètre de requête (moins sécurisé)
        return $request->query('api_key');
    }

    /**
     * Vérifie si la clé API est valide
     */
    protected function isValidApiKey(string $apiKey): bool
    {
        $validApiKey = config('satellite.hub.api_key');
        
        if (!$validApiKey) {
            return false;
        }

        // Comparaison sécurisée pour éviter les attaques de timing
        return hash_equals($validApiKey, $apiKey);
    }

    /**
     * Vérifie les limites de taux
     */
    protected function isRateLimited(Request $request): bool
    {
        $rateLimit = config('satellite.security.rate_limit', 100);
        
        if ($rateLimit <= 0) {
            return false; // Pas de limite
        }

        $key = 'satellite_rate_limit:' . $request->ip();
        $cache = cache();
        
        $attempts = $cache->get($key, 0);
        
        if ($attempts >= $rateLimit) {
            return true;
        }

        // Incrémenter le compteur avec expiration d'1 minute
        $cache->put($key, $attempts + 1, now()->addMinute());
        
        return false;
    }

    /**
     * Vérifie si l'IP est autorisée
     */
    protected function isIpAllowed(Request $request): bool
    {
        $whitelist = config('satellite.security.ip_whitelist');
        
        if (!$whitelist) {
            return true; // Pas de restriction IP
        }

        $allowedIps = array_map('trim', explode(',', $whitelist));
        $clientIp = $request->ip();

        foreach ($allowedIps as $allowedIp) {
            if (empty($allowedIp)) continue;
            
            // Support pour les CIDR
            if (str_contains($allowedIp, '/')) {
                if ($this->ipInRange($clientIp, $allowedIp)) {
                    return true;
                }
            } else {
                if ($clientIp === $allowedIp) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Vérifie si une IP est dans une plage CIDR
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);
        
        if ($bits === null) {
            $bits = 32;
        }

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;

        return ($ip & $mask) == $subnet;
    }
} 