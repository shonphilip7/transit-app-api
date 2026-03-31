<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\TrainViewHelper;
use App\Helpers\CommonHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TrainViewController extends Controller
{
    /**
     * The function to generate the trainview schedule API data
     *
     * @param string $stop_id Stop ID (ex:VYTA, THPT...)
     * @return array $trainview Inbound/outbound timings for the stop
     */
    public function index($stop_id)
    {
        $trainview = array();
        try {
            $trainview_helper = new TrainViewHelper();
            $common_helper = new CommonHelper();
            /**
             * Get calendar data from redis or the JSON file
             */
            $calendar_data = $common_helper->getCalendarData();
            if (count($calendar_data) >=1) {
                $release = null;
                $services = array();
                $today = Carbon::today('Asia/Kolkata');
                $formattedDate = $today->format('Ymd'); //Get current date
                /**
                 * The GTFS file from kochi metro only has entries till 20251231
                 * so need to improvise. 
                 */
                $formattedDate = $common_helper->adjustedDate($formattedDate, $calendar_data, $today);
                $release = $calendar_data[$formattedDate]['release_name'];
                $services = $calendar_data[$formattedDate]['service_id'];
                if (($release !== null) && (count($services) > 0)) {
                    $inbound_trips = collect();
                    $outbound_trips = collect();
                    $trainview_api_response = collect();
                    $trainview_api_data = collect();
                    $next_inbound_trips = collect();
                    $next_outbound_trips = collect();
                    $service_alerts = collect();
                    /**
                     * Get all routes of the transit agency. Ideally this would be from the routes.txt 
                     * file but since this is a test, hardcoding it for now. 
                     */
                    $rr_routes = array('R1');
                    foreach ($rr_routes as $rr_route) {
                        /**
                         * Get schedules for the given stop.
                         */
                        $rr_schedule = $trainview_helper->getSchedules($rr_route, $stop_id);
                        $rr_trips = $trainview_helper->getTrips($rr_schedule, $release, $services);
                        $rr_response = $trainview_helper->buildResponse($rr_trips);
                        $trainview[$rr_route]['Inbound'] = $rr_response['Inbound'];
                        $trainview[$rr_route]['Outbound'] = $rr_response['Outbound'];
                    }
                } else {
                    Log::error('Error message: Unable to get release or service');
                }
            }
        } catch (Exception $e) {
            Log::error('Error message: Caught exception '.$e->getMessage());
            $trainview = array();
        }
        return $trainview;
    }
    /**
     * API for getting all routes of the transit agency
     *
     * @return array $routes Stores all distinct routes of the transit agency
     */
    public function getRoutes()
    {
        $routes = array();
        try {
            $trainview_helper = new TrainViewHelper();
            $routes = $trainview_helper->getRoutes();
        } catch (Exception $e) {
            Log::error('Error message getting routes in API: Caught exception '.$e->getMessage());
            $routes = array();
        }
        return $routes;
    }
    /**
     * API for getting all stops of the given route
     *
     * @param string $line Transit agency route
     * @return array $stops Stores all stops of the given route
     */
    public function getStops($line)
    {
        $stops = array();
        try {
            $trainview_helper = new TrainViewHelper();
            $stops = $trainview_helper->getStops($line);
        } catch (Exception $e) {
            Log::error('Error message getting stops in API: Caught exception '.$e->getMessage());
            $stops = array();
        }
        return $stops;
    }
}
