<?php

namespace Tests\Feature;

use App\Services\RedisService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RouteStopsTest extends TestCase
{
    private const ROUTE_ID = '101';

    private array $sampleRawStops = [
        // Original index 0 (sequence 10)
        [
            'route_id' => self::ROUTE_ID,
            'stop_id' => '5003',
            'stop_name' => 'Third Stop',
            'stop_sequence' => '10',
            'release_name' => 'v1',
            'direction_id' => '0',
        ],
        // Original index 1 (sequence 1)
        [
            'route_id' => self::ROUTE_ID,
            'stop_id' => '5001',
            'stop_name' => 'First Stop',
            'stop_sequence' => '1',
            'release_name' => 'v1',
            'direction_id' => '0',
        ],
        // Original index 2 (sequence 2)
        [
            'route_id' => self::ROUTE_ID,
            'stop_id' => '5002',
            'stop_name' => 'Second Stop',
            'stop_sequence' => '2',
            'release_name' => 'v1',
            'direction_id' => '0',
        ],
        // Original index 3 (direction 1 -> filtered out)
        [
            'route_id' => self::ROUTE_ID,
            'stop_id' => '6001',
            'stop_name' => 'Opposite Direction Stop',
            'stop_sequence' => '1',
            'release_name' => 'v1',
            'direction_id' => '1',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Redis::connection()->flushdb();
    }

    public function test_it_fetches_stops_filters_direction_sorts_numerically_and_caches_on_miss(): void
    {
        Storage::disk('public')->put('stops/'.self::ROUTE_ID.'/stops.json', json_encode($this->sampleRawStops));
        $response = $this->getJson('/api/'.self::ROUTE_ID.'/stops');
        $response->assertOk()
            ->assertJsonCount(3)
            ->assertExactJson([
                '1' => [
                    'route_id' => self::ROUTE_ID,
                    'stop_id' => '5001',
                    'stop_name' => 'First Stop',
                    'stop_sequence' => '1',
                    'release_name' => 'v1',
                    'direction_id' => '0',
                ],
                '2' => [
                    'route_id' => self::ROUTE_ID,
                    'stop_id' => '5002',
                    'stop_name' => 'Second Stop',
                    'stop_sequence' => '2',
                    'release_name' => 'v1',
                    'direction_id' => '0',
                ],
                '0' => [
                    'route_id' => self::ROUTE_ID,
                    'stop_id' => '5003',
                    'stop_name' => 'Third Stop',
                    'stop_sequence' => '10',
                    'release_name' => 'v1',
                    'direction_id' => '0',
                ],
            ]);
        $cached = app(RedisService::class)->get(self::ROUTE_ID.'_stops');
        $this->assertSame($this->sampleRawStops, $cached);
    }

    public function test_it_returns_stops_from_redis_cache_on_cache_hit(): void
    {
        app(RedisService::class)->set(self::ROUTE_ID.'_stops', $this->sampleRawStops, 3600);
        $response = $this->getJson('/api/'.self::ROUTE_ID.'/stops');
        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('1.stop_id', '5001')
            ->assertJsonPath('2.stop_id', '5002')
            ->assertJsonPath('0.stop_id', '5003');
    }

    public function test_it_works_when_route_only_has_direction_one(): void
    {
        $one_dir_route_id = '202';
        $directionOneOnly = [
            // Original index 0 (sequence 2)
            [
                'route_id' => $one_dir_route_id,
                'stop_id' => '7002',
                'stop_name' => 'Stop B',
                'stop_sequence' => '2',
                'release_name' => 'v1',
                'direction_id' => '1',
            ],
            // Original index 1 (sequence 1)
            [
                'route_id' => $one_dir_route_id,
                'stop_id' => '7001',
                'stop_name' => 'Stop A',
                'stop_sequence' => '1',
                'release_name' => 'v1',
                'direction_id' => '1',
            ],
        ];
        Storage::disk('public')->put('stops/'.$one_dir_route_id.'/stops.json', json_encode($directionOneOnly));
        $response = $this->getJson('/api/'.$one_dir_route_id.'/stops');
        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('1.stop_id', '7001')
            ->assertJsonPath('0.stop_id', '7002');
    }

    public function test_it_returns_404_when_stops_file_does_not_exist(): void
    {
        $response = $this->getJson('/api/routes/999/stops');
        $response->assertNotFound();
    }

    public function test_it_rejects_invalid_route_parameter_formats(): void
    {
        $this->getJson('/api/invalid$route/stops')
            ->assertNotFound();
        $this->getJson('/api/routes/..%2F..%2F.env/stops')
            ->assertNotFound();
    }
}
