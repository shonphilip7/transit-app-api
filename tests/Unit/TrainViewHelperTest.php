<?php

namespace Tests\Unit;

use App\Helpers\TrainViewHelper;
use Carbon\Carbon;
use Tests\DataProviders\TestData;
use Tests\TestCase;

class TrainViewHelperTest extends TestCase
{
    public function test_it_gets_next_four_trips(): void
    {
        // Arrange: Mock the data returned by getTrips method. This is similar to contents of schedule.json
        $mock_trips_data = collect(TestData::seedSchedulesData());
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
