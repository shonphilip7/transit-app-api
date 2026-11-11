<?php

use App\Helpers\TrainViewHelper;
use Carbon\Carbon;

uses(Tests\TestCase::class);

test('it gets next four trips', function ($trip_variations) {
    $trainview_helper = new TrainViewHelper;
    // Arrange & Act: Call the helper method on the mock trips
    $result = $trainview_helper->getNextFourTrips($trip_variations);
    // Assert: Verify the method returns a max of four results
    expect($result->count())->toBeLessThanOrEqual(4);
    // Assert: Verify the past time is not included in the result
    $unwantedTripId = 'Wk_6';
    $tripIds = $result->pluck('trip_id')->all();
    expect($tripIds)->not->toContain($unwantedTripId);
    // Assert: Verify the arrival times of the first is less than the last one
    $firstArrivalTime = $result->first()['arrival_time'];
    $lastArrivalTime = $result->last()['arrival_time'];
    $firstTime = Carbon::createFromFormat('H:i:s', $firstArrivalTime, 'Asia/Kolkata');
    $lastTime = Carbon::createFromFormat('H:i:s', $lastArrivalTime, 'Asia/Kolkata');
    expect($firstTime)->toBeLessThanOrEqual($lastTime);
})->with(function () {
    $time_zone = 'Asia/Kolkata';

    return [
        'multiple trips' => [
            collect([
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
                [
                    'route_id' => 'R1',
                    'stop_id' => 'ALVA',
                    'stop_name' => 'Aluva',
                    'stop_sequence' => '1',
                    'release_name' => 'KMRLOpenData',
                    'direction_id' => '0',
                    'trip_id' => 'Wk_6',
                    'service_id' => 'WK',
                    'arrival_time' => Carbon::now($time_zone)->subMinutes(15)->format('H:i:s'), // an edge case
                ],
            ]),
        ],
    ];
});
