<?php

namespace Tests\Unit;

use App\Helpers\TrainViewHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrainViewHelperTest extends TestCase
{
    /**
     * Mock the result of getTrips data. This is used as param for
     * getNextFourTrips method. Since the method depends on current
     * time, make sure the arrival_time is not in the past.
     */
    private function seedGetTripsData(): Collection
    {
        $time_zone = 'Asia/Kolkata';
        $mockData = collect([
            [
                'route_id' => 'R1',
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
                'trip_id' => 'Wk_1',
                'service_id' => 'WK',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(30)->format('H:i:s'),
            ],
            [
                'route_id' => 'R1',
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
                'trip_id' => 'Wk_2',
                'service_id' => 'WK',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(15)->format('H:i:s'),
            ],
            [
                'route_id' => 'R1',
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
                'trip_id' => 'Wk_3',
                'service_id' => 'WK',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(60)->format('H:i:s'),
            ],
            [
                'route_id' => 'R1',
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
                'trip_id' => 'Wk_4',
                'service_id' => 'WK',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(75)->format('H:i:s'),
            ],
            [
                'route_id' => 'R1',
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
                'trip_id' => 'Wk_5',
                'service_id' => 'WK',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(45)->format('H:i:s'),
            ],
        ]);

        return $mockData;
    }

    public function test_it_gets_next_four_trips(): void
    {
        // Arrange: Mock the data returned by getTrips menthod
        $mock_trips_data = $this->seedGetTripsData();
        $trainview_helper = new TrainViewHelper;
        // Act: Call the helper method
        $result = $trainview_helper->getNextFourTrips($mock_trips_data);
        // Assert: Verify the method returns a max of four results
        $this->assertLessThanOrEqual(4, $result->count());
        // Assert: Verify the arrival times of the first is less than the last one
        $firstArrivalTime = $result->first()['arrival_time'];
        $lastArrivalTime = $result->last()['arrival_time'];
        $firstTime = Carbon::createFromFormat('H:i:s', $firstArrivalTime, 'Asia/Kolkata');
        $lastTime = Carbon::createFromFormat('H:i:s', $lastArrivalTime, 'Asia/Kolkata');
        $this->assertTrue($firstTime->lt($lastTime));
    }
}
