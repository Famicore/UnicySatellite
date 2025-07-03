<?php

namespace UnicySatellite\Exceptions;

use Exception;

class SatelliteException extends Exception
{
    /**
     * Create a new satellite exception.
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for connection errors.
     */
    public static function connectionFailed(string $reason = ''): self
    {
        return new self("Échec de connexion à UnicyHub: {$reason}");
    }

    /**
     * Create an exception for authentication errors.
     */
    public static function authenticationFailed(): self
    {
        return new self('Échec d\'authentification avec UnicyHub - vérifiez votre clé API');
    }

    /**
     * Create an exception for registration errors.
     */
    public static function registrationFailed(string $reason = ''): self
    {
        return new self("Échec d'enregistrement du satellite: {$reason}");
    }

    /**
     * Create an exception for sync errors.
     */
    public static function syncFailed(string $type, string $reason = ''): self
    {
        return new self("Échec de synchronisation {$type}: {$reason}");
    }

    /**
     * Create an exception for configuration errors.
     */
    public static function configurationMissing(string $key): self
    {
        return new self("Configuration satellite manquante: {$key}");
    }
} 