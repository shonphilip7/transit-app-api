<?php

namespace Tests\DataProviders;

use Carbon\Carbon;

class TestData
{
    /**
     * Mocks the contents of routes.json file
     */
    public static function seedRoutesData(): array
    {
        $mockData = [
            'release_name' => 'KMRLOpenData',
            'route_id' => 'R1',
            'route_short_name' => 'KMRL',
            'route_long_name' => 'Kochi Metro Route 1',
            'route_type' => 1,
        ];

        return $mockData;
    }

    /**
     * Mocks the contents of stops.json file
     */
    public static function seedStopsData(): array
    {
        $mockData = [
            [
                'route_id' => 'R1',
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
            ],
            [
                'route_id' => 'R1',
                'stop_id' => 'TNHL',
                'stop_name' => 'Town Hall',
                'stop_sequence' => '14',
                'release_name' => 'KMRLOpenData',
                'direction_id' => '0',
            ],
        ];

        return $mockData;
    }

    /**
     * Mocks the contents of schedule.json file
     */
    public static function seedSchedulesData(): array
    {
        $time_zone = 'Asia/Kolkata';
        $mockData = [
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
            [
                'route_id' => 'R1',
                'release_name' => 'KMRLOpenData',
                'trip_id' => 'Wk_23',
                'service_id' => 'WK',
                'direction_id' => '0',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(60)->format('H:i:s'),
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
            ],
            [
                'route_id' => 'R1',
                'release_name' => 'KMRLOpenData',
                'trip_id' => 'WE_184',
                'service_id' => 'WE',
                'direction_id' => '1',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(45)->format('H:i:s'),
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '25',
            ],
            [
                'route_id' => 'R1',
                'release_name' => 'KMRLOpenData',
                'trip_id' => 'Wk_65',
                'service_id' => 'WK',
                'direction_id' => '0',
                'arrival_time' => Carbon::now($time_zone)->addMinutes(75)->format('H:i:s'),
                'stop_id' => 'ALVA',
                'stop_name' => 'Aluva',
                'stop_sequence' => '1',
            ],
        ];

        return $mockData;
    }
}
