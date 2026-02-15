<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
/**
 * A custom helper class for stuff related to Redis cache like get, set
 */
class CacheHelper
{
    private $redis = null;
    public function __construct()
    {
        $this->redis = Redis::connection();
    }
    /**
     * Pinging the redis connection
     * 
     * @return bool $response True if there is a connection else false
     */
    public function connect()
    {
        $response = false;
        try {
            if (isset($this->redis)) {
                $response = $this->redis->ping();
                if (!$response) {
                    Log::error('Error message: No PONG from redis');
                    $response = false;
                }
            } else {
                Log::error('Error message: Unable to set redis indtance');
                $response = false;
            }
        } catch (\Exception $e) {
            Log::error('Error message: '.$e->getMessage());
            $response = false;
        }
        return $response;
    }
    /**
     * Set key-value to Redis
     * @param string $key Redis key
     * @param string $data Redis value
     * @param int $expiry Redis data expiry in seconds
     * @return bool $response True if successfully able to set key-value in Redis
     */
    public function set($key, $data, $expiry)
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
            Log::error('Error message: '.$e->getMessage());
            $response = false;
        }
        return $response;
    }
    /**
     * Get value from Redis based on key
     * @param string Redis key
     * @return mixed The actual data from redis else false
     */
    public function get($key)
    {
        $response = false;
        try {
            $response = $this->redis->get($key);
        } catch (\Exception $e) {
            Log::error('Error message: '.$e->getMessage());
            $response = false;
        }
        return $response;
    }
}