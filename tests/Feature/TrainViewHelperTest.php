<?php

namespace Tests\Feature;

use App\Helpers\TrainViewHelper;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DataProviders\TestData;
use Tests\TestCase;

class TrainViewHelperTest extends TestCase
{
    private TrainViewHelper $trainview_helper;

    /** @var \Illuminate\Contracts\Filesystem\Filesystem */
    private $disk;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        $this->disk = Storage::fake('public');
        $this->trainview_helper = new TrainViewHelper;
    }

    #[DataProvider('configProvider')]
    public function test_getting_data_from_redis_first(string $method, array $params, string $redisKey, string $fileName, array $seedData): void
    {
        // Need to re-instantiate to use a static data provider
        $helper = new TrainViewHelper;
        // Arrange: Put test contents into Redis
        Redis::set($redisKey, json_encode($seedData));
        // Act: Run the helper service method
        $result = $helper->{$method}(...$params);
        // Assert: It prefers the Redis data
        $this->assertEquals($seedData, $result);
    }

    #[DataProvider('configProvider')]
    public function test_getting_from_file_when_redis_is_empty(string $method, array $params, string $redisKey, string $fileName, array $seedData): void
    {
        $helper = new TrainViewHelper;
        // Arrange: Put a fake file on the virtualized storage disk
        $this->disk->put($fileName, json_encode($seedData));
        // Act: Call your helper method
        $result = $helper->{$method}(...$params);
        // Assert: Verify the method returned the correct data
        $this->assertEquals($seedData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get($redisKey);
        $this->assertEquals($seedData, json_decode($savedRedisData, true));
        // Verify the expiry is one day
        $ttl = Redis::ttl($redisKey);
        $this->assertEquals(86400, $ttl);
    }

    #[DataProvider('configProvider')]
    public function test_trainview_helpers_handling_gracefully_when_cache_is_empty_and_file_is_missing(string $method, array $params, string $redisKey, string $fileName, array $seedData): void
    {
        $helper = new TrainViewHelper;
        // Arrange: Ensure Redis is empty (already handled by flushdb in setUp)
        // Arrange: Ensure the file explicitly does NOT exist on the fake disk
        $this->assertFalse($this->disk->exists($fileName));
        // Act: Call the helper method
        $result = $helper->{$method}(...$params);
        // Assert: Verify that it returns an empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        // Assert: Verify that nothing was accidentally written to Redis
        $cachedValue = Redis::get($redisKey);
        $this->assertNull($cachedValue);
    }

    #[DataProvider('configProvider')]
    public function test_trainview_helpers_return_empty_array_and_skips_redis_when_file_contains_corrupted_json(string $method, array $params, string $redisKey, string $fileName, array $seedData): void
    {
        $helper = new TrainViewHelper;
        // Arrange: Ensure Redis is empty (already handled by flushdb in setUp)
        // Arrange: Ensure the corrupted JSON is added to the file
        $corruptedJsonString = "{ 'invalid_json': true, missing_closing_bracket: ";
        $this->disk->put($fileName, $corruptedJsonString);
        // Act: Invoke your service method
        $result = $helper->{$method}(...$params);
        // Assert 1: The method caught the parsing error and safely returned an empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        // Assert 2: Verify that your system DID NOT cache the corrupted string or null into Redis
        $cachedValue = Redis::get($redisKey);
        $this->assertNull($cachedValue);
    }

    public function test_get_trips_returns_list_of_trips_in_correct_format(): void
    {
        // Arrange: Get schedule data from getSchedules and use it to get trips
        $mockData = TestData::seedSchedulesData();
        Redis::set('R1_ALVA_schedules', json_encode($mockData));
        $mockSchedule = $this->trainview_helper->getSchedules('R1', 'ALVA');
        $mockRelease = 'KMRLOpenData';
        $mockServices = ['WK'];
        // Act: Call your helper method
        $result = $this->trainview_helper->getTrips($mockSchedule, $mockRelease, $mockServices);
        // Assert: Ensure it's a collection and not empty
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertNotEmpty($result);
        // Assert: Verify the method returned the correct key(direction_id)
        $this->assertTrue($result->has(0));
        // Assert: Verify the method returns the result in the proper format
        $firstItem = $result->first();
        // First item has nested arrays so only need to check one of the key
        $this->assertIsArray($firstItem[0]);
        $this->assertArrayHasKey('route_id', $firstItem[0]);
        $this->assertArrayHasKey('stop_id', $firstItem[0]);
        $this->assertArrayHasKey('stop_name', $firstItem[0]);
        $this->assertArrayHasKey('release_name', $firstItem[0]);
        $this->assertArrayHasKey('direction_id', $firstItem[0]);
        $this->assertArrayHasKey('trip_id', $firstItem[0]);
        $this->assertArrayHasKey('service_id', $firstItem[0]);
        $this->assertArrayHasKey('arrival_time', $firstItem[0]);
    }

    public function test_response_is_build_for_trainview(): void
    {
        // Arrange: Get data for buildResponse method
        $mockData = TestData::seedSchedulesData();
        Redis::set('R1_ALVA_schedules', json_encode($mockData));
        $mockSchedule = $this->trainview_helper->getSchedules('R1', 'ALVA');
        $mockRelease = 'KMRLOpenData';
        $mockServices = ['WK'];
        $mockTrips = $this->trainview_helper->getTrips($mockSchedule, $mockRelease, $mockServices);
        // Act: Call the helper method
        $result = $this->trainview_helper->buildResponse($mockTrips);
        // Assert: Verify the method returns the result in the proper format
        $this->assertIsArray($result);
        $this->assertArrayHasKey('Inbound', $result);
        $this->assertArrayHasKey('Outbound', $result);
        // Inbound trips is an array so only need to check one
        $this->assertArrayHasKey('route_id', $result['Inbound'][0]);
        $this->assertArrayHasKey('stop_id', $result['Inbound'][0]);
        $this->assertArrayHasKey('stop_name', $result['Inbound'][0]);
        $this->assertArrayHasKey('release_name', $result['Inbound'][0]);
        $this->assertArrayHasKey('direction_id', $result['Inbound'][0]);
        $this->assertArrayHasKey('trip_id', $result['Inbound'][0]);
        $this->assertArrayHasKey('service_id', $result['Inbound'][0]);
        $this->assertArrayHasKey('arrival_time', $result['Inbound'][0]);
    }

    /**
     * Data provider for getRoutes and getStops methods since it uses the
     * same logic.
     */
    public static function configProvider(): array
    {
        $cachedRoutesData = TestData::seedRoutesData();
        $cachedStopsData = TestData::seedStopsData();
        $cachedSchedulesData = TestData::seedSchedulesData();
        $route = 'R1';
        $stop = 'ALVA';

        return [
            'routes_config' => [
                'method' => 'getRoutes',
                'params' => [],
                'redisKey' => 'routes',
                'fileName' => 'routes.json',
                'seedData' => $cachedRoutesData,
            ],
            'stops_config' => [
                'method' => 'getStops',
                'params' => [$route],
                'redisKey' => $route.'_stops',
                'fileName' => 'stops/'.$route.'/stops.json',
                'seedData' => $cachedStopsData,
            ],
            'schedules_config' => [
                'method' => 'getSchedules',
                'params' => [$route, $stop],
                'redisKey' => $route.'_'.$stop.'_schedules',
                'fileName' => 'schedules/stops/'.$route.'/'.$stop.'/schedule.json',
                'seedData' => $cachedSchedulesData,
            ],
        ];
    }
}
