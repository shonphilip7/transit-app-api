<?php

namespace Tests\Feature;

use App\Services\RedisService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RouteTest extends TestCase
{
    private const CACHE_KEY = 'routes';

    private const FILE_PATH = 'routes.json';

    private array $sampleRoutes = [
        [
            'release_name' => 'v1',
            'route_id' => '101',
            'route_short_name' => '101',
            'route_long_name' => 'Main St',
            'route_type' => '3',
        ],
        [
            'release_name' => 'v1',
            'route_id' => '102',
            'route_short_name' => '102',
            'route_long_name' => 'Broadway',
            'route_type' => '3',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Redis::connection()->flushdb();
    }

    public function test_it_fetches_routes_from_storage_and_caches_them_on_cache_miss(): void
    {
        Storage::disk('public')->put(self::FILE_PATH, json_encode($this->sampleRoutes));
        $response = $this->getJson('/api/routes');
        // $this is used as sampleRoutes is a regular instance property not a CONSTANT
        $response->assertOk()
            ->assertExactJson($this->sampleRoutes);
        // Build and return an object instance of the class
        $cached = app(RedisService::class)->get(self::CACHE_KEY);
        $this->assertSame($this->sampleRoutes, $cached);
    }

    public function test_it_returns_routes_from_redis_cache_on_cache_hit(): void
    {
        app(RedisService::class)->set(self::CACHE_KEY, $this->sampleRoutes, 3600);
        // Storage intentionally left empty to prove it reads from Redis
        $response = $this->getJson('/api/routes');
        $response->assertOk()
            ->assertExactJson($this->sampleRoutes);
    }

    public function test_response_has_expected_unwrapped_json_structure(): void
    {
        Storage::disk('public')->put(self::FILE_PATH, json_encode($this->sampleRoutes));
        $response = $this->getJson('/api/routes');
        $response->assertOk()
            ->assertJsonMissingPath('data')
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'release_name',
                    'route_id',
                    'route_short_name',
                    'route_long_name',
                    'route_type',
                ],
            ]);
    }

    public function test_it_returns_empty_array_when_routes_file_is_empty_list(): void
    {
        Storage::disk('public')->put(self::FILE_PATH, json_encode([]));
        $response = $this->getJson('/api/routes');
        $response->assertOk()
            ->assertExactJson([]);
    }

    public function test_it_fails_when_cache_is_empty_and_routes_file_is_missing(): void
    {
        $response = $this->getJson('/api/routes');
        $response->assertStatus(500);
    }
}
