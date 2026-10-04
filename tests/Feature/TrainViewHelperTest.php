<?php

namespace Tests\Feature;

use App\Helpers\TrainViewHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
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
        $cachedData = ['status' => 'cached_in_redis'];
        Redis::set('routes', json_encode($cachedData));
        // Act: Run the helper service method
        $result = $this->trainview_helper->getRoutes();
        // Assert: It prefers the Redis data
        $this->assertEquals($cachedData, $result);
    }

    public function test_get_routes_falls_back_to_file_when_redis_is_empty(): void
    {
        // Arrange: Put a fake file on the virtualized storage disk
        $mockData = ['status' => 'fresh_from_json'];
        Storage::disk('public')->put('routes.json', json_encode($mockData));
        // Act: Call your helper method
        $result = $this->trainview_helper->getRoutes();
        // Assert: Verify the method returned the correct data
        $this->assertEquals($mockData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get('routes');
        $this->assertEquals($mockData, json_decode($savedRedisData, true));
    }

    public function test_get_schedules_success(): void
    {
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $this->assertIsArray($schedules_data, 'Schedule data not an array');
        $this->assertNotEmpty($schedules_data, 'Schedule file is empty for KVTR');
        $this->assertEquals('R1', $schedules_data[0]['route_id'], 'Route id should be R1');
        $this->assertEquals('KVTR', $schedules_data[0]['stop_id'], 'Stop id should be KVTR');
        $this->assertEquals('Kadavanthra', $schedules_data[0]['stop_name'], 'Stop id should be Kadavanthra');
    }

    public function test_get_schedules_fail(): void
    {
        $schedules_data = $this->trainview_helper->getSchedules('test', 'abc');
        $this->assertNull($schedules_data, 'The value should be null');
    }

    public function test_get_trips_success(): void
    {
        $data = collect();
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $release = $schedules_data[0]['release_name'];
        $service = [$schedules_data[0]['service_id']];
        $trips = $this->trainview_helper->getTrips($schedules_data, $release, $service);
        $this->assertInstanceOf(Collection::class, $trips);
        $this->assertGreaterThanOrEqual(1, $trips->count(), 'trips is empty');
        if ($trips->has('1')) {
            $data = $trips['1'];
        }
        if ($trips->has('0')) {
            $data = $trips['0'];
        }
        $this->assertEquals('R1', $data[0]['route_id'], 'Route id should be R1');
        $this->assertEquals('KVTR', $data[0]['stop_id'], 'Stop id should be KVTR');
        $this->assertEquals('Kadavanthra', $data[0]['stop_name'], 'Stop id should be Kadavanthra');
    }

    public function test_get_trips_fail(): void
    {
        $trips = $this->trainview_helper->getTrips('x', 'y', ['z']);
        $this->assertEquals(0, $trips->count());
    }

    public function test_get_next_four_trips(): void
    {
        $data = collect();
        $spliced_trips = collect();
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $release = $schedules_data[0]['release_name'];
        $service = [$schedules_data[0]['service_id']];
        $trips = $this->trainview_helper->getTrips($schedules_data, $release, $service);
        if ($trips->has('1')) {
            $data = $trips['1'];
        }
        if ($trips->has('0')) {
            $data = $trips['0'];
        }
        $spliced_trips = $this->trainview_helper->getNextFourTrips($data);
        $this->assertLessThanOrEqual(4, $spliced_trips->count(), 'Maximum allowed value is 4');
        $this->assertEquals('R1', $data[0]['route_id'], 'Route id should be R1');
        $this->assertEquals('KVTR', $data[0]['stop_id'], 'Stop id should be KVTR');
        $this->assertEquals('Kadavanthra', $data[0]['stop_name'], 'Stop id should be Kadavanthra');
    }

    public function test_build_response(): void
    {
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $release = $schedules_data[0]['release_name'];
        $service = [$schedules_data[0]['service_id']];
        $trips = $this->trainview_helper->getTrips($schedules_data, $release, $service);
        $response = $this->trainview_helper->buildResponse($trips);
        $this->assertIsArray($response, 'Response data not an array');
        $this->assertNotEmpty($response, 'Response data is empty for KVTR');
        $this->assertEquals(array_keys($response), ['Inbound', 'Outbound'], 'Response should have both inbound & outbound');
        $this->assertLessThanOrEqual(4, count($response['Inbound']), 'Maximum allowed inbound value is 4');
        $this->assertLessThanOrEqual(4, count($response['Outbound']), 'Maximum allowed outbound value is 4');
        $this->assertEquals('R1', $response['Inbound'][0]['route_id'], 'Route id should be R1');
        $this->assertEquals('KVTR', $response['Inbound'][0]['stop_id'], 'Stop id should be KVTR');
        $this->assertEquals('Kadavanthra', $response['Inbound'][0]['stop_name'], 'Stop id should be Kadavanthra');
    }
}
