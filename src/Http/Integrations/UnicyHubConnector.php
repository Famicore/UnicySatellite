<?php

namespace UnicySatellite\Http\Integrations;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowsOnErrors;

class UnicyHubConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowsOnErrors;

    protected string $hubUrl;
    protected string $apiKey;

    public function __construct(string $hubUrl, string $apiKey)
    {
        $this->hubUrl = rtrim($hubUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return $this->hubUrl . '/api/satellites';
    }

    /**
     * Default headers for every request
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
            'User-Agent' => 'UnicySatellite/1.0',
            'X-Satellite-Name' => config('satellite.satellite.name'),
            'X-Satellite-Type' => config('satellite.satellite.type'),
        ];
    }

    /**
     * Default HTTP client options
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => config('satellite.hub.timeout', 30),
            'verify' => config('satellite.security.verify_ssl', true),
        ];
    }
} 