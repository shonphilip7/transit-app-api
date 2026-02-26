<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Helpers\TrainViewHelper;
use Illuminate\Support\Collection;

class TrainViewHelperTest extends TestCase
{
    private $trainview_helper;
    protected function setUp(): void
    {
        parent::setUp();
        $this->trainview_helper = new TrainViewHelper();
    }
    public function test_getSchedules_success(): void
    {
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $this->assertIsArray($schedules_data, 'Schedule data not an array');
        $this->assertNotEmpty($schedules_data, 'Schedule file is empty for KVTR');
        $this->assertEquals('R1', $schedules_data[0]['route_id'], 'Route id should be R1');
        $this->assertEquals('KVTR', $schedules_data[0]['stop_id'], 'Stop id should be KVTR');
        $this->assertEquals('Kadavanthra', $schedules_data[0]['stop_name'], 'Stop id should be Kadavanthra');
    }
    public function test_getSchedules_fail(): void
    {
        $schedules_data = $this->trainview_helper->getSchedules('test', 'abc');
        $this->assertNull($schedules_data, 'The value should be null');
    }
    public function test_getTrips_success(): void
    {
        $data = collect();
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $release = $schedules_data[0]['release_name'];
        $service = array($schedules_data[0]['service_id']);
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
    public function test_getTrips_fail(): void
    {
        $trips = $this->trainview_helper->getTrips('x', 'y', array('z'));
        $this->assertEquals(0, $trips->count());
    } 
    public function test_getNextFourTrips(): void
    {
        $data = collect();
        $spliced_trips = collect();
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $release = $schedules_data[0]['release_name'];
        $service = array($schedules_data[0]['service_id']);
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
    public function test_buildResponse(): void
    {
        $schedules_data = $this->trainview_helper->getSchedules('R1', 'KVTR');
        $release = $schedules_data[0]['release_name'];
        $service = array($schedules_data[0]['service_id']);
        $trips = $this->trainview_helper->getTrips($schedules_data, $release, $service);
        $response = $this->trainview_helper->buildResponse($trips);
        $this->assertIsArray($response, 'Response data not an array');
        $this->assertNotEmpty($response, 'Response data is empty for KVTR');
        $this->assertEquals(array_keys($response), array('Inbound', 'Outbound'), 'Response should have both inbound & outbound');
        $this->assertLessThanOrEqual(4, count($response['Inbound']), 'Maximum allowed inbound value is 4');
        $this->assertLessThanOrEqual(4, count($response['Outbound']), 'Maximum allowed outbound value is 4');
        $this->assertEquals('R1', $response['Inbound'][0]['route_id'], 'Route id should be R1');
        $this->assertEquals('KVTR', $response['Inbound'][0]['stop_id'], 'Stop id should be KVTR');
        $this->assertEquals('Kadavanthra', $response['Inbound'][0]['stop_name'], 'Stop id should be Kadavanthra');
    }
    protected function tearDown(): void
    {
        // Clean up resources if necessary (though often optional in PHP)
        $this->trainview_helper = null;
        parent::tearDown();
    }
}
