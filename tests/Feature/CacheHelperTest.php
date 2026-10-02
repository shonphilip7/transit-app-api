<?php

namespace Tests\Feature;

use App\Helpers\CacheHelper;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CacheHelperTest extends TestCase
{
    protected CacheHelper $redis_service;

    protected function setUp(): void
    {
        parent::setUp();
        // Clean up cache after each test
        Redis::connection()->flushdb();
        $this->redis_service = new CacheHelper;
    }

    public function test_redis_can_connect(): void
    {
        $connection = false;
        $connection = $this->redis_service->connect();
        $this->assertTrue($connection, 'Not able to connect to redis');
    }

    public function test_redis_store_data(): void
    {
        $stored_data = false;
        $stored_data = $this->redis_service->set('framework', 'Laravel', 300);
        $this->assertTrue($stored_data, 'Not able to add data to redis');
        $this->assertEquals('Laravel', Redis::get('framework'));
    }

    public function test_redis_get_data(): void
    {
        Redis::set('tool', 'Sail');
        $retrived_data = $this->redis_service->get('tool');
        $this->assertEquals('Sail', $retrived_data, 'Not able to get data from redis');
    }
}
