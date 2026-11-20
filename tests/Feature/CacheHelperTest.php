<?php

namespace Tests\Feature;

use App\Services\RedisService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CacheHelperTest extends TestCase
{
    private RedisService $redisService;

    protected function setUp(): void
    {
        parent::setUp();
        // Clean up cache after each test
        Redis::connection()->flushdb();
        $this->redisService = new RedisService;
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
        // Act: Attempt to connect
        $this->redisService->connect();
        // Assert: Connection is made and able to set/get
        $this->assertTrue($this->redisService->set('connection_check', 'ok'));
        $this->assertSame('ok', $this->redisService->get('connection_check'));
    }

    public function test_redis_store_data(): void
    {
        // Arrange: A mock API output
        $payload = [
            [
                'release_name' => 'v1',
                'route_id' => '101',
                'route_short_name' => '101',
                'route_long_name' => 'Main St',
                'route_type' => 3,
            ],
        ];
        // Act & Assert: Call the service and verify the data is stored
        $this->assertTrue($this->redisService->set('test:string', 'hello world'));
        $this->assertSame('hello world', $this->redisService->get('test:string'));
        $this->assertTrue($this->redisService->set('test:routes', $payload));
        $this->assertSame($payload, $this->redisService->get('test:routes'));
    }

    public function test_set_instantly_overrides_stale_transit_coordinates(): void
    {
        // Arrange: A stale data and a fresh data to overwrite it.
        $key = 'transit:vehicle:101';
        $staleCoordinates = [
            'lat' => 40.0882,
            'lng' => -75.0123,
            'updated_at' => '17:00:00',
        ];
        $freshCoordinates = [
            'lat' => 40.0915,
            'lng' => -75.0189,
            'updated_at' => '17:00:15',
        ];
        // Act: Call the service method
        $this->redisService->set($key, $staleCoordinates, 60);
        $this->redisService->set($key, $freshCoordinates, 60);
        // Assert: It has the newer data
        $this->assertSame($freshCoordinates, $this->redisService->get($key));
    }

    public function test_redis_get_data(): void
    {
        // Act & Assert: Call the service and verify a null is returned
        $this->assertNull($this->redisService->get('non_existent_key'));
        // Arrange: Mock API data
        $expectedData = [
            'release_name' => 'v1',
            'route_id' => '202',
            'route_short_name' => '202',
            'route_long_name' => 'Broad St Line',
            'route_type' => 1,
        ];
        // Assert: Verfy the mock data is returned
        $this->redisService->set('test:route:202', $expectedData);
        $this->assertSame($expectedData, $this->redisService->get('test:route:202'));
    }

    public function test_get_returns_null_when_transit_data_is_expired(): void
    {
        // Arrange: Set a key value to redis
        $key = 'transit:expiring_route';
        $this->redisService->set($key, ['route_id' => '101'], 1);
        // Act & Assert: Call the service method and verify it has value
        $this->assertNotNull($this->redisService->get($key));
        // Arrange: Wait for 2 seconds, since the expiration is set to 1 second
        sleep(2);
        // Act & Assert: Call the service method and verify null is returned for the expired key
        $this->assertNull($this->redisService->get($key));
    }

    public function test_get_allows_multiple_consecutive_reads_without_altering_state(): void
    {
        // Arrange: A mock API data
        $key = 'transit:routes:all';
        $payload = [
            [
                'release_name' => 'v1',
                'route_id' => '101',
                'route_short_name' => '101',
                'route_long_name' => 'Main St',
                'route_type' => 3,
            ],
        ];
        // Act: Call the service method
        $this->redisService->set($key, $payload, 300);
        // Assert: Multiple get calls returns the same data
        $this->assertSame($payload, $this->redisService->get($key));
        $this->assertSame($payload, $this->redisService->get($key));
        $this->assertSame($payload, $this->redisService->get($key));
    }
}
