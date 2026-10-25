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

    public function test_uses_the_isolated_testing_database_index()
    {
        // Act: Get the active configuration database index from the connection
        $client = Redis::connection()->client();
        // Pull the expected database index defined in .env.testing config
        $expectedDb = config('database.redis.default.database', 1);
        $activeDb = method_exists($client, 'getDbNum') ? $client->getDbNum() : config('database.redis.default.database');
        // Assert: Ensure it matches your expected .env.testing configuration (e.g., DB index 1)
        $this->assertEquals($expectedDb, $activeDb);
    }

    public function test_redis_can_connect(): void
    {
        $connection = false;
        // Act: Attempt to connect
        $connection = $this->redis_service->connect();
        // Assert: Successful connection check
        $this->assertTrue($connection, 'Not able to connect to redis');
        // Assert: Force a round-trip network check to ensure it's fully operational
        $pingResponse = Redis::connection()->ping();
        // Assert: phpredis returns true or '+PONG', Predis returns an object or string 'PONG'
        $this->assertTrue($pingResponse === true || str_contains((string) $pingResponse, 'PONG'));
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
