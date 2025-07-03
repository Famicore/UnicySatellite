<?php

namespace UnicySatellite\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class RegisterSatelliteRequest extends Request
{
    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::POST;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * The endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/register';
    }

    /**
     * The body of the request
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
} 