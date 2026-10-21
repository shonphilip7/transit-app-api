<?php

namespace Tests\Feature;

use App\Helpers\TrainViewHelper;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\DataProviders\TestData;
use Tests\TestCase;

class TrainViewHelperTest extends TestCase
{
    protected TrainViewHelper $trainview_helper;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        Storage::fake('public');
        $this->trainview_helper = new TrainViewHelper;
    }

    public function test_get_routes_gets_data_from_redis_first(): void
    {
        // Arrange: Seed the hardcoded key using the native Redis facade
        $cachedData = TestData::seedRoutesData();
        Redis::set('routes', json_encode($cachedData));
        // Act: Run the helper service method
        $result = $this->trainview_helper->getRoutes();
        // Assert: It prefers the Redis data
        $this->assertEquals($cachedData, $result);
    }

    public function test_get_routes_falls_back_to_file_when_redis_is_empty(): void
    {
        // Arrange: Put a fake file on the virtualized storage disk
        $mockData = TestData::seedRoutesData();
        Storage::disk('public')->put('routes.json', json_encode($mockData));
        // Act: Call your helper method
        $result = $this->trainview_helper->getRoutes();
        // Assert: Verify the method returned the correct data
        $this->assertEquals($mockData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get('routes');
        $this->assertEquals($mockData, json_decode($savedRedisData, true));
    }

    public function test_get_stops_gets_data_from_redis_first(): void
    {
        // Arrange: Seed the hardcoded key using the native Redis facade
        $cachedData = TestData::seedStopsData();
        Redis::set('R1_stops', json_encode($cachedData));
        // Act: Run the helper service method
        $result = $this->trainview_helper->getStops('R1');
        // Assert: It prefers the Redis data
        $this->assertEquals($cachedData, $result);
    }

    public function test_get_stops_falls_back_to_file_when_redis_is_empty(): void
    {
        // Arrange: Put a fake file on the virtualized storage disk
        $mockData = TestData::seedStopsData();
        Storage::disk('public')->put('stops/R1/stops.json', json_encode($mockData));
        // Act: Call your helper method
        $result = $this->trainview_helper->getStops('R1');
        // Assert: Verify the method returned the correct data
        $this->assertEquals($mockData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get('R1_stops');
        $this->assertEquals($mockData, json_decode($savedRedisData, true));
    }

    public function test_get_schedules_gets_data_from_redis_first(): void
    {
        // Arrange: Seed the hardcoded key using the native Redis facade
        $cachedData = TestData::seedSchedulesData();
        Redis::set('R1_ALVA_schedules', json_encode($cachedData));
        // Act: Run the helper service method
        $result = $this->trainview_helper->getSchedules('R1', 'ALVA');
        // Assert: It prefers the Redis data
        $this->assertEquals($cachedData, $result);
    }

    public function test_get_schedules_falls_back_to_file_when_redis_is_empty(): void
    {
        // Arrange: Put a fake file on the virtualized storage disk
        $mockData = TestData::seedSchedulesData();
        Storage::disk('public')->put('schedules/stops/R1/ALVA/schedule.json', json_encode($mockData));
        // Act: Call your helper method
        $result = $this->trainview_helper->getSchedules('R1', 'ALVA');
        // Assert: Verify the method returned the correct data
        $this->assertEquals($mockData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get('R1_ALVA_schedules');
        $this->assertEquals($mockData, json_decode($savedRedisData, true));
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
}
