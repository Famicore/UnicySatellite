<?php

namespace UnicySatellite\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool shouldRegister()
 * @method static array registerSatellite()
 * @method static bool sendMetrics(array $metrics)
 * @method static bool syncData(array $data, string $type = 'tenants')
 * @method static array healthCheck()
 */
class SatelliteHub extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'satellite-hub';
    }
} 