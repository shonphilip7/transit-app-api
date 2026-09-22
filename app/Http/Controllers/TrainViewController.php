<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Helpers\TrainViewHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrainViewController extends Controller
{
    public function index(string $rr_route, string $stop_id): array
    {
        $trainview = [];
        try {
            $trainview_helper = new TrainViewHelper;
            $common_helper = new CommonHelper;
            /**
             * Get calendar data from redis or the JSON file. The calendar data
             * has the release name and service ids for the current day whic is required
             * for getting arrival times.
             */
            $calendar_data = $common_helper->getCalendarData();
            if (count($calendar_data) >= 1) {
                $release = null;
                $services = [];
                $today = Carbon::today('Asia/Kolkata');
                $formattedDate = $today->format('Ymd'); // Get current date
                /**
                 * The GTFS file from kochi metro only has entries till 20251231
                 * so need to improvise.
                 */
                $formattedDate = $common_helper->adjustedDate($formattedDate, $calendar_data, $today);
                $release = $calendar_data[$formattedDate]['release_name'];
                $services = $calendar_data[$formattedDate]['service_id'];
                if (($release !== null) && (count($services) > 0)) {
                    $rr_schedule = $trainview_helper->getSchedules($rr_route, $stop_id);
                    $rr_trips = $trainview_helper->getTrips($rr_schedule, $release, $services);
                    $rr_response = $trainview_helper->buildResponse($rr_trips);
                    $trainview[$rr_route]['Inbound'] = $rr_response['Inbound'];
                    $trainview[$rr_route]['Outbound'] = $rr_response['Outbound'];
                } else {
                    Log::error('Error message: Unable to get release or service');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error message: Caught TrainView API exception '.$e->getMessage());
            $trainview = [];
        }

        return $trainview;
    }

    /**
     * Do not confuse with getRoutes from the TrainViewHelper class.
     * This function is for generating the routes API
     */
    public function getRoutes(): array
    {
        $routes = [];
        try {
            $trainview_helper = new TrainViewHelper;
            $routes = $trainview_helper->getRoutes();
        } catch (\Exception $e) {
            Log::error('Error message getting routes in API: Caught exception '.$e->getMessage());
            $routes = [];
        }

        return $routes;
    }

    /**
     * Do not confuse with getStops from the TrainViewHelper class.
     * This function is for generating the stops API
     */
    public function getStops(string $line): array
    {
        $stops = [];
        try {
            $trainview_helper = new TrainViewHelper;
            $stops = $trainview_helper->getStops($line);
        } catch (\Exception $e) {
            Log::error('Error message getting stops in API: Caught exception '.$e->getMessage());
            $stops = [];
        }

        return $stops;
    }
}
