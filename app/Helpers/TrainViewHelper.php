<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\CacheHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
/**
 * A custom helper class for stuff related to the TrainView API
 */
class TrainViewHelper
{
    private $cache_helper = false;
    public function __construct()
    {
        $this->cache_helper = new CacheHelper();
    }
    /**
     * Call the calendar API
     * 
     * The function checks if the data is in redis else it calls the API and
     * stores data in redis for faster response time
     * 
     * @return array calendar_data The calendar data from the static JSON files
     */
    public function getCalendarData()
    {
        $calendar_data = array();
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
                $calendar_data = Storage::disk('public')->json('calendar.json');
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
     * Call the schedules API
     * 
     * The function checks if the data is in redis else it calls the API and stores data in redis 
     * for faster response time
     * 
     * @param string $line The transit route
     * @param string $stop_id The stop id
     * @return string $schedules_data Result from the schedules API
     */
    public function getSchedules($line, $stop_id)
    {
        $schedules_data = null;
        $scheduleJsonData = null;
        try {
            if ($this->cache_helper->connect()) {
                $scheduleJsonData = $this->cache_helper->get($line.'_'.$stop_id.'_schedules');
            }
            if ($scheduleJsonData !== null) {
                $schedules_data = json_decode($scheduleJsonData, true);
            } else {
                $schedules_data = Storage::disk('public')->json('schedules/stops/'.$line.'/'.$stop_id.'/schedule.json');
                if ($this->cache_helper->connect()) {
                    $this->cache_helper->set($line.'_'.$stop_id.'_schedules', json_encode($schedules_data), 86400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error message: '.$e->getMessage());
            $schedules_data = null;
        }
        return $schedules_data;
    }
    /**
     * Group schedule based on direction
     * 
     * Filter the schedule trips based on release name and service ids from 
     * the calendar API.
     * 
     * @param string $schedule Train line schedule
     * @param string $release Release name from the calendar API
     * @param string $services Service ids from the calendar API
     * @return object $trips Scheduled trips grouped by direction  
     */
    public function getTrips($schedule, $release, $services)
    {
        $trips = collect();
        $filtered_by_release = collect($schedule)->where('release_name', $release);
        if ($filtered_by_release->isNotEmpty()) {
            $filtered_by_service = $filtered_by_release->filter(function($items) use ($services) {
                return in_array($items['service_id'], $services);
            });
            if ($filtered_by_service->isNotEmpty()) {
                $trips = $filtered_by_service->groupBy('direction_id');
            }
        }
        return $trips;
    }
    /**
     * Add real-time data to active trips
     * 
     * Parse through trips and check if the trips are active. If so add the real-time
     * info to it.
     * 
     * @param object $remaining_trips Scheduled trips
     * @param object $train_view The TrainView API results
     * @return object $result Trips with real-time info added to it
     */
    public function addTrainViewData($remaining_trips, $train_view)
    {
        $result = collect();
        $status = false;
        $service = '';
        $track = '';
        $remaining_trips->each(function($item, $key) use ($train_view, $status, $service, $track, $result) {
            $arrival_time = Carbon::createFromFormat('H:i:s', $item['arrival_time'], 'America/New_York');
            $eta = $arrival_time;
            $rr_train = $train_view->where('trainno', $item['block_id']);
            if ($rr_train->count() >=1) {
                $lateness = $rr_train->first()['late'];
                if ($lateness <= 0 && $lateness < 1) {
                    $status = 'ON TIME';
                }
                if ($lateness >= 1) {
                    $status = $lateness.' LATE';
                    $eta = $arrival_time->addMinutes($lateness);
                }
                $service = $rr_train->first()['service'];
                if ($rr_train->first()['nextstop'] == 'Jefferson Station') {
                    $track = $rr_train->first()['TRACK'];
                }
            }
            $result->put($item['block_id'], array(
                'arrival_time' => $item['arrival_time'],
                'status' => ($status === false) ? 'SCHEDULED':$status,
                'headsign' => $item['trip_headsign'],
                'service' => $service,
                'track' => $track,
                'eta' => $eta->timestamp,
                'train_no' => $item['block_id']
            ));
        });
        return $result;
    }
    /**
     * Splice trip results
     * 
     * Filter out trips that have already passed and get the top four results in
     * ascending order
     * 
     * @param object $trips Scheduled trips
     * @return $object $filtered_by_time Upcoming four trips
     */
    public function getNextFourTrips($trips)
    {
        $current_time = Carbon::now('Asia/Kolkata');
        $filtered_by_time = $trips->filter(function($items) use ($current_time) {
            $scheduled_arrival_time = Carbon::parse($items['arrival_time'], 'Asia/Kolkata');
            //$eta = Carbon::createFromTimestamp($items['eta'], 'America/New_York');
            return $current_time->lessThanOrEqualTo($scheduled_arrival_time);
        })->values();
        return $filtered_by_time->sortBy('eta')->take(4);
    }
    /**
     * The trips are spliced to get the next four results.
     * 
     * @param object $trip Scheduled trips
     * @return array $response An array of trips grouped by inbound/outbound
     */
    public function buildResponse($trip)
    {
        $response = array();
        if ($trip->has(1)) {
            //$inbound_trips = $this->addTrainViewData($trip[1], $api_data);
            $inbound_trips = $trip[1];
        }
        if ($trip->has(0)) {
            //$outbound_trips = $this->addTrainViewData($trip[0], $api_data);
            $outbound_trips = $trip[0];
        }
        if ($inbound_trips->count() >=1) {
            $next_inbound_trips = $this->getNextFourTrips($inbound_trips);
        }
        if ($outbound_trips->count() >=1) {
            $next_outbound_trips = $this->getNextFourTrips($outbound_trips);
        }
        $response['Inbound'] = $next_inbound_trips->toArray();
        $response['Outbound'] = $next_outbound_trips->toArray();
        return $response;
    }
    /**
     * Call Alerts API
     * 
     * Get alerts that are applicable to all regional routes
     * 
     * @param string $api_url Alerts API
     * @return object $service_message Alert message for regional rail
     */
    public function getAlerts($api_url)
    {
        $api_data = collect();
        $rr_routes = array('AIR', 'CHE', 'CHW', 'FOX', 'LAN', 'MED', 'PAO', 'TRE', 'WIL', 'WTR', 'NOR', 'WAR', 'CYN');
        $service_message = collect();
        $api_response = Http::get($api_url);
        if ($api_response->successful()) {
            $api_data = $api_response->collect();
        }
        if ($api_data->count() >= 1) {
            $api_data->each(function($item, $key) use ($rr_routes, $service_message) {
                if (count(array_diff($rr_routes, $item['routes'])) == 0) {
                    $clean_string = strip_tags($item['message']);
                    $clean_string = str_replace('&nbsp;', ' ', $clean_string);
                    $service_message->put($item['alert_id'], $clean_string);
                }
            });
        }
        return $service_message;
    }
    /**
     * This class was added due to the limitation in the KMRL open data.
     * (Last entry provided in GTFS is for date 2025-12-31). In the year 2026 
     * if the current day is Sunday then this function would get the last date 
     * of that day for the previous year which in this case would be 2025-12-28. 
     * 
     * @param string $year The year to search the dates
     * @param string $day_of_week Sunday, Monday, Tuesday.....
     */
    public function getLastDaysOfYear($year, $day_of_week)
    {
        $calendar_week = array(
            'Sunday' => Carbon::SUNDAY, 
            'Monday' => Carbon::MONDAY, 
            'Tuesday' => CARBON::TUESDAY,
            'Wednesday' => CARBON::WEDNESDAY,
            'Thursday'=> CARBON::THURSDAY,
            'Friday' => CARBON::FRIDAY,
            'Saturday' => CARBON::SATURDAY
        );
        /**
         * Create a Carbon instance for the first day of the year following 
         * the target year. For example, for the year 2025, this creates an 
         * instance for 2026-01-01.
         */
        $firstDayOfNextYear = Carbon::create($year + 1, 1, 1, 0, 0, 0);
        // Subtract one second to get the very last moment of the target year (2025-12-31 23:59:59)
        $lastMomentOfYear = $firstDayOfNextYear->subSecond();
        // Now, use the last moment of the year instance and go to the day provided in the param.
        $lastSunday = $lastMomentOfYear->lastOfMonth($calendar_week[$day_of_week]);
        return $lastSunday->format('Ymd');
    }
}