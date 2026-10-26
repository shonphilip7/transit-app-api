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
        $key = 'framework';
        // Act: Attempt to store data in Redis
        $stored_data = $this->redis_service->set($key, 'Laravel', 300);
        $ttl = Redis::connection()->ttl($key);
        // Assert: Data suceesfully stored in Redis with correct expiry
        $this->assertTrue($stored_data, 'Not able to add data to redis');
        $this->assertEquals('Laravel', Redis::get($key));
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(300, $ttl);
    }

    public function test_set_instantly_overrides_stale_transit_coordinates(): void
    {
        $key = 'vehicle:bus_route_45:coords';
        // Set initial stale position (e.g., 60s freshness window)
        $this->redis_service->set($key, json_encode(['lat' => 19.076, 'lng' => 72.877, 'status' => 'delayed']), 60);
        // Act: Instantly update with a fresh ping from the GPS tracker
        $newPayload = json_encode(['lat' => 19.082, 'lng' => 72.883, 'status' => 'moving']);
        $this->redis_service->set($key, $newPayload, 30); // Narrower safety window for moving status
        // Assert: Verify the old data is wiped and the new data rules
        $stored = Redis::connection()->get($key);
        $this->assertEquals($newPayload, $stored);
        $ttl = Redis::connection()->ttl($key);
        $this->assertLessThanOrEqual(30, $ttl);
    }

    public function test_redis_get_data(): void
    {
        // Arrange: Manually seed the data using Redis instance
        Redis::set('tool', 'Sail');
        // Act: Execute the custom get method
        $retrived_data = $this->redis_service->get('tool');
        // Assert: Ensure it matches the raw string exactly
        $this->assertEquals('Sail', $retrived_data, 'Not able to get data from redis');
    }

    public function test_get_returns_null_when_transit_data_is_missing_or_expired(): void
    {
        $key = 'route:101:expired_eta';
        // Arrange: Ensure the key is genuinely absent from the engine
        Redis::connection()->del($key);
        // Act: Execute the custom get method
        $result = $this->redis_service->get($key);
        // Assert: A cache miss must return null so your app knows to fetch fresh API data
        $this->assertNull($result);
    }

    public function test_get_allows_multiple_consecutive_reads_without_altering_state(): void
    {
        $key = 'stop:7721:arrivals';
        $payload = json_encode(['next_train_mins' => 12]);
        Redis::connection()->set($key, $payload, 'EX', 30);
        // Act & Assert: Read multiple times to simulate concurrent user lookups
        $this->assertSame($payload, $this->redis_service->get($key));
        $this->assertSame($payload, $this->redis_service->get($key));
        $this->assertSame($payload, $this->redis_service->get($key));
    }
}
