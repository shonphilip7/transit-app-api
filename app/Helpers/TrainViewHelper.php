<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TrainViewHelper
{
    private ?CacheHelper $cache_helper = null;

    public function __construct()
    {
        $this->cache_helper = new CacheHelper;
    }

    public function getSchedules(string $line, string $stop_id): array
    {
        $schedules_data = [];
        $scheduleJsonData = null;
        try {
            if ($this->cache_helper->connect()) {
                $scheduleJsonData = $this->cache_helper->get($line.'_'.$stop_id.'_schedules');
            }
            if ($scheduleJsonData !== null) {
                $schedules_data = json_decode($scheduleJsonData, true);
            } else {
                $schedules_data = json_decode(Storage::disk('public')->get('schedules/stops/'.$line.'/'.$stop_id.'/schedule.json'), true);
                if ($this->cache_helper->connect()) {
                    $this->cache_helper->set($line.'_'.$stop_id.'_schedules', json_encode($schedules_data), 86400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error message: Issue with getting schedule.'.$e->getMessage());
            $schedules_data = [];
        }

        return $schedules_data;
    }

    public function getTrips(array $schedule, string $release, array $services): Collection
    {
        // Convert array to collection for simplicity
        $trips = collect();
        $filtered_by_release = collect($schedule)->where('release_name', $release);
        if ($filtered_by_release->isNotEmpty()) {
            $filtered_by_service = $filtered_by_release->filter(function ($items) use ($services) {
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
     * @param  object  $remaining_trips  Scheduled trips
     * @param  object  $train_view  The TrainView API results
     * @return object $result Trips with real-time info added to it
     */
    public function addTrainViewData($remaining_trips, $train_view)
    {
        $result = collect();
        $status = false;
        $service = '';
        $track = '';
        $remaining_trips->each(function ($item, $key) use ($train_view, $status, $service, $track, $result) {
            $arrival_time = Carbon::createFromFormat('H:i:s', $item['arrival_time'], 'America/New_York');
            $eta = $arrival_time;
            $rr_train = $train_view->where('trainno', $item['block_id']);
            if ($rr_train->count() >= 1) {
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
            $result->put($item['block_id'], [
                'arrival_time' => $item['arrival_time'],
                'status' => ($status === false) ? 'SCHEDULED' : $status,
                'headsign' => $item['trip_headsign'],
                'service' => $service,
                'track' => $track,
                'eta' => $eta->timestamp,
                'train_no' => $item['block_id'],
            ]);
        });

        return $result;
    }

    public function getNextFourTrips(Collection $trips): Collection
    {
        $current_time = Carbon::now('Asia/Kolkata');
        $filtered_by_time = $trips->filter(function ($items) use ($current_time) {
            $scheduled_arrival_time = Carbon::parse($items['arrival_time'], 'Asia/Kolkata');

            // $eta = Carbon::createFromTimestamp($items['eta'], 'America/New_York');
            return $current_time->lessThanOrEqualTo($scheduled_arrival_time);
        })->values();

        return $filtered_by_time->sortBy('eta')->take(4);
    }

    public function buildResponse(Collection $trip): array
    {
        $response = [];
        $next_inbound_trips = collect();
        $next_outbound_trips = collect();
        if ($trip->has(1)) {
            $inbound_trips = $trip[1];
        }
        if ($trip->has(0)) {
            $outbound_trips = $trip[0];
        }
        if ($inbound_trips->count() >= 1) {
            $next_inbound_trips = $this->getNextFourTrips($inbound_trips);
        }
        if ($outbound_trips->count() >= 1) {
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
     * @param  string  $api_url  Alerts API
     * @return object $service_message Alert message for regional rail
     */
    public function getAlerts($api_url)
    {
        $api_data = collect();
        $rr_routes = ['AIR', 'CHE', 'CHW', 'FOX', 'LAN', 'MED', 'PAO', 'TRE', 'WIL', 'WTR', 'NOR', 'WAR', 'CYN'];
        $service_message = collect();
        $api_response = Http::get($api_url);
        if ($api_response->successful()) {
            $api_data = $api_response->collect();
        }
        if ($api_data->count() >= 1) {
            $api_data->each(function ($item, $key) use ($rr_routes, $service_message) {
                if (count(array_diff($rr_routes, $item['routes'])) == 0) {
                    $clean_string = strip_tags($item['message']);
                    $clean_string = str_replace('&nbsp;', ' ', $clean_string);
                    $service_message->put($item['alert_id'], $clean_string);
                }
            });
        }

        return $service_message;
    }

    public function getRoutes(): array
    {
        $routes = [];
        $routesJsonData = null;
        try {
            if ($this->cache_helper->connect()) {
                $routesJsonData = $this->cache_helper->get('routes');
            }
            if ($routesJsonData !== null) {
                $routes = json_decode($routesJsonData, true);
            } else {
                $routes = json_decode(Storage::disk('public')->get('routes.json'), true);
                if ($this->cache_helper->connect()) {
                    $this->cache_helper->set('routes', json_encode($routes), 86400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error message getting routes: '.$e->getMessage());
            $routesJsonData = null;
        }

        return $routes;
    }

    public function getStops(string $line): array
    {
        $stops = [];
        $stopJsonData = null;
        try {
            if ($this->cache_helper->connect()) {
                $stopJsonData = $this->cache_helper->get($line.'_stops');
            }
            if ($stopJsonData !== null) {
                $stops = json_decode($stopJsonData, true);
            } else {
                $stops = json_decode(Storage::disk('public')->get('stops/'.$line.'/stops.json'), true);
                if ($this->cache_helper->connect()) {
                    $this->cache_helper->set($line.'_stops', json_encode($stops), 86400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error message getting stops: '.$e->getMessage());
            $stopJsonData = null;
        }
        if (count($stops) >= 1) {
            /*
            Convert array to collection for easier filtering and sorting. Filter the values based on direction
            else there will be duplicates as stops are same in either direction. Finally, sort by stop_sequence
            for better asthetic.
            */
            $collection = collect($stops);
            $stops = $collection->where('direction_id', '0')->sortBy('stop_sequence', SORT_NUMERIC)->toArray();
        }

        return $stops;
    }
}
