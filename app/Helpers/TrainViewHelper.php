<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
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
