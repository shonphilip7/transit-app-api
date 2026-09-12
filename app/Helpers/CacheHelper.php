<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CacheHelper
{
    /** @var \Illuminate\Redis\Connections\Connection
     * Declaring the exact type clears the Intelephense warning!
     */
    private $redis = null;

    public function __construct()
    {
        $this->redis = Redis::connection();
    }

    public function connect(): bool
    {
        $response = false;
        try {
            if (isset($this->redis)) {
                $response = $this->redis->ping();
                if (! $response) {
                    Log::error('Error message: No PONG from redis');
                    $response = false;
                }
            } else {
                Log::error('Error message: Unable to set redis instance');
                $response = false;
            }
        } catch (\Exception $e) {
            Log::error('Error in redis connection. Error message: '.$e->getMessage());
            $response = false;
        }

        return $response;
    }

    public function set(string $key, string $data, int $expiry)
    {
        $response = false;
        try {
            if ($this->redis->set($key, $data, 'EX', $expiry)) {
                $response = true;
            } else {
                $response = false;
                Log::error('Error message: Unable to set redis data');
            }
        } catch (\Exception $e) {
            Log::error('Error in redis set operation: '.$e->getMessage());
            $response = false;
        }

        return $response;
    }

    public function get(string $key)
    {
        $response = false;
        try {
            $response = $this->redis->get($key);
        } catch (\Exception $e) {
            Log::error('Error in redis get operation: '.$e->getMessage());
            $response = false;
        }

        return $response;
    }
}
