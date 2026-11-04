<?php

namespace Tests\Unit;

use App\Helpers\TrainViewHelper;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrainViewHelperTest extends TestCase
{
    #[DataProvider('tripVariationProvider')]
    public function test_it_gets_next_four_trips(\Closure $anonymousSetup): void
    {
        // Arrange: Mock the data returned by getTrips method.
        $trips_cases = $anonymousSetup();
        $trainview_helper = new TrainViewHelper;
        // Act: Call the helper method
        $result = $trainview_helper->getNextFourTrips($trips_cases);
        // Assert: Verify the method returns a max of four results
        $this->assertLessThanOrEqual(4, $result->count());
        // Assert: Verify the past time is not included in the result
        $unwantedTripId = 'Wk_6';
        $tripIds = $result->pluck('trip_id')->all();
        $this->assertNotContains($unwantedTripId, $tripIds);
        // Assert: Verify the arrival times of the first is less than the last one
        $firstArrivalTime = $result->first()['arrival_time'];
        $lastArrivalTime = $result->last()['arrival_time'];
        $firstTime = Carbon::createFromFormat('H:i:s', $firstArrivalTime, 'Asia/Kolkata');
        $lastTime = Carbon::createFromFormat('H:i:s', $lastArrivalTime, 'Asia/Kolkata');
        $this->assertTrue($firstTime->lte($lastTime));
    }

    #[DataProvider('getTripsIterations')]
    public function test_build_response_for_trainview_api(
        \Closure $anonymousSetup,
        string $top_key_count,
        array $expected_top_keys,
        array $expected_internal_keys,
        bool|string $single_key
    ): void {
        // Arrange: Mock the data returned by getTrips method.
        $trips_cases = $anonymousSetup();
        $trainview_helper = new TrainViewHelper;
        // Act: Call the helper method
        $result = $trainview_helper->buildResponse($trips_cases);
        // Assert: The result is an array
        $this->assertIsArray($result);
        // Assert: Verify the top key count
        $this->assertCount($top_key_count, $result);
        // Assert: Verify top keys exists
        foreach ($expected_top_keys as $key_item) {
            $this->assertArrayHasKey($key_item, $result);
        }
        // Assert: Verify structure of each top item
        foreach (['Inbound', 'Outbound'] as $direction) {
            $this->assertIsArray($result[$direction]);
            if ($direction === $single_key) {
                $this->assertEmpty($result[$single_key]);
            }
            if (! empty($result[$direction])) {
                foreach ($result[$direction] as $item) {
                    $this->assertIsArray($item);
                    foreach ($expected_internal_keys as $key) {
                        $this->assertArrayHasKey($key, $item);
                    }
                }
            }
        }
    }

    /**
     * Data provider supplying variations of trips. Mock the result
     * of getTrips data. This is used as param for getNextFourTrips
     * method. Since the method depends on current time, make sure the
     * arrival_time is not in the past.
     */
    public static function tripVariationProvider(): array
    {
        $time_zone = 'Asia/Kolkata';

        return [
            'multiple trips' => [
                fn () => collect([
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
            'single trip' => [
                fn () => collect([
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
                ]),
            ],
        ];
    }

    /**
     * Data provider for testing buildResponse method. This is a variation of
     * all the results from the getTrips method and has different structure to
     * the tripVariationProvider data provider.
     */
    public static function getTripsIterations(): array
    {
        $time_zone = 'Asia/Kolkata';
        $expected_top_key_count = '2';
        $expected_top_keys = ['Inbound', 'Outbound'];
        $expected_internal_keys = ['route_id', 'release_name', 'trip_id', 'service_id', 'direction_id', 'arrival_time', 'stop_id', 'stop_name', 'stop_sequence'];

        return [
            'Inbound and outbound trips' => [
                fn () => collect([
                    0 => collect([
                        [
                            'route_id' => 'R1',
                            'release_name' => 'KMRLOpenData',
                            'trip_id' => 'Wk_1',
                            'service_id' => 'WK',
                            'direction_id' => '0',
                            'arrival_time' => Carbon::now($time_zone)->addMinutes(30)->format('H:i:s'),
                            'stop_id' => 'ALVA',
                            'stop_name' => 'Aluva',
                            'stop_sequence' => '1',
                        ],
                    ]),
                    1 => collect([
                        [
                            'route_id' => 'R1',
                            'release_name' => 'KMRLOpenData',
                            'trip_id' => 'Wk_34',
                            'service_id' => 'WK',
                            'direction_id' => '1',
                            'arrival_time' => Carbon::now($time_zone)->addMinutes(15)->format('H:i:s'),
                            'stop_id' => 'ALVA',
                            'stop_name' => 'Aluva',
                            'stop_sequence' => '25',
                        ],
                    ]),
                ]),
                $expected_top_key_count,
                $expected_top_keys,
                $expected_internal_keys,
                false,
            ],
            'Inbound trips only' => [
                fn () => collect([
                    1 => collect([
                        [
                            'route_id' => 'R1',
                            'release_name' => 'KMRLOpenData',
                            'trip_id' => 'Wk_34',
                            'service_id' => 'WK',
                            'direction_id' => '1',
                            'arrival_time' => Carbon::now($time_zone)->addMinutes(15)->format('H:i:s'),
                            'stop_id' => 'ALVA',
                            'stop_name' => 'Aluva',
                            'stop_sequence' => '25',
                        ],
                    ]),
                ]),
                $expected_top_key_count,
                $expected_top_keys,
                $expected_internal_keys,
                'Outbound',
            ],
            'Outbound trips' => [
                fn () => collect([
                    0 => collect([
                        [
                            'route_id' => 'R1',
                            'release_name' => 'KMRLOpenData',
                            'trip_id' => 'Wk_1',
                            'service_id' => 'WK',
                            'direction_id' => '0',
                            'arrival_time' => Carbon::now($time_zone)->addMinutes(30)->format('H:i:s'),
                            'stop_id' => 'ALVA',
                            'stop_name' => 'Aluva',
                            'stop_sequence' => '1',
                        ],
                    ]),
                ]),
                $expected_top_key_count,
                $expected_top_keys,
                $expected_internal_keys,
                'Inbound',
            ],
            'Empty trips' => [
                fn () => collect([]),
                $expected_top_key_count = '2',
                $expected_top_keys,
                [],
                false,
            ],
        ];
    }
}
