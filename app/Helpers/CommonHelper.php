<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Common methods used in more than one controllers
 */
class CommonHelper
{
    private ?CacheHelper $cache_helper = null;

    public function __construct()
    {
        $this->cache_helper = new CacheHelper;
    }

    public function getCalendarData()
    {
        $calendar_data = [];
        $calendarJsonData = null;
        try {
            /**
             * Try the redis for an entry else get it from the actual file
             */
            if ($this->cache_helper->connect()) {
                $calendarJsonData = $this->cache_helper->get('calendar_data');
            }
            if ($calendarJsonData !== null) {
                $calendar_data = json_decode($calendarJsonData, true);
            } else {
                $calendar_data = json_decode(Storage::disk('public')->get('calendar.json'), true);
                if ($this->cache_helper->connect()) {
                    $this->cache_helper->set('calendar_data', json_encode($calendar_data), 86400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error message: '.$e->getMessage());
            $calendar_data = null;
        }

        return $calendar_data;
    }

    /**
     * This function was added due to the limitation in the KMRL open data.
     * (Last entry provided in GTFS is for date 2025-12-31). If the current
     * date is greater than 20251231 which it would be since we are in 2026
     * at the time of writing this code then select a date from the last week
     * of 2025 based on the current day name(Mon, Tue, Wed ...). So if today
     * is Wednesday then get the date for the last Wednesday of the year 2025.
     *
     * @param  string  $year  The year to search the dates
     * @param  string  $day_of_week  Sunday, Monday, Tuesday.....
     * @return string Date in Ymd format
     */
    public function getLastDaysOfYear($year, $day_of_week)
    {
        $calendar_week = [
            'Sunday' => Carbon::SUNDAY,
            'Monday' => Carbon::MONDAY,
            'Tuesday' => CARBON::TUESDAY,
            'Wednesday' => CARBON::WEDNESDAY,
            'Thursday' => CARBON::THURSDAY,
            'Friday' => CARBON::FRIDAY,
            'Saturday' => CARBON::SATURDAY,
        ];
        /**
         * Create a Carbon instance for the first day of the year following
         * the target year. For example, for the year 2025, this creates an
         * instance for 2026-01-01.
         */
        $firstDayOfNextYear = Carbon::create($year + 1, 1, 1, 0, 0, 0);
        // Subtract one second to get the very last moment of the target year (2025-12-31 23:59:59)
        $lastMomentOfYear = $firstDayOfNextYear->subSecond();
        // Now, use the last moment of the year instance and go to the day provided in the param.
        $lastGivenDay = $lastMomentOfYear->lastOfMonth($calendar_week[$day_of_week]);

        return $lastGivenDay->format('Ymd');
    }

    /**
     * This function was added due to the limitation in the KMRL open data. The last entry provided
     * in the GTFS is 2025-12-31. If the current date is greater than the last entry which it would
     * be since at the time of writing this code we are in 2026 then get the latest date from 2025
     * that best matches the current day. Ex: If the current day is Saturday the get the last date
     * of Saturday in 2025 which would be the 2025-12-27.
     */
    public function adjustedDate(string $given_date, array $calendar, Carbon $current_day): string
    {
        $result = '';
        $calendar_data_last_key = array_key_last(array_keys($calendar));
        $calendar_last_date = array_keys($calendar)[$calendar_data_last_key];
        $date1 = Carbon::parse($given_date);
        $date2 = Carbon::parse($calendar_last_date);
        if ($date1->gt($date2)) {
            $result = $this->getLastDaysOfYear('2025', $current_day->englishDayOfWeek);
        } else {
            $result = $given_date;
        }

        return $result;
    }
}
