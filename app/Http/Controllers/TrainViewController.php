<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\TrainViewHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TrainViewController extends Controller
{
    //
    public function index($stop_id)
    {
        $trainview = array();
        try {
            $trainview_helper = new TrainViewHelper();
            /**
             * Get calendar data from redis or the JSON file
             */
            $calendar_data = $trainview_helper->getCalendarData();
            if (count($calendar_data) >=1) {
                $release = null;
                $services = array();
                $today = Carbon::today('Asia/Kolkata');
                $formattedDate = $today->format('Ymd'); //Get current date
                /**
                 * The GTFS file from kochi metro only has entries till 20251231
                 * so need to improvise.
                 */
                $calendar_data_last_key = array_key_last(array_keys($calendar_data));
                $calendar_last_date = array_keys($calendar_data)[$calendar_data_last_key];
                $date1 = Carbon::parse($formattedDate);
                $date2 = Carbon::parse($calendar_last_date);
                if ($date1->gt($date2)) {
                    $formattedDate = $trainview_helper->getLastDaysOfYear('2025', $today->englishDayOfWeek);; 
                }
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
                    /*$westbound_trains = array_merge(
                        $trainview['AIR']['Inbound'], 
                        $trainview['CHW']['Inbound'], 
                        $trainview['MED']['Inbound'], 
                        $trainview['PAO']['Inbound'],
                        $trainview['TRE']['Inbound'],
                        $trainview['WIL']['Inbound']
                    );
                    // Get top 4 results
                    $trainview['next_to_suburban'] = collect($westbound_trains)->sortBy('eta')->take(4);
                    $eastbound_trains = array_merge(
                        $trainview['WTR']['Outbound'],
                        $trainview['CHE']['Outbound'],
                        $trainview['FOX']['Outbound'],
                        $trainview['LAN']['Outbound'],
                        $trainview['NOR']['Outbound'],
                        $trainview['WAR']['Outbound']
                    );
                    // Get top 4 results
                    $trainview['next_to_temple'] = collect($eastbound_trains)->sortBy('eta')->take(4); 
                    // Get alerts to display along with result
                    $service_alerts = $trainview_helper->getAlerts('alerts API');
                    $trainview['alerts'] = $service_alerts->toArray();*/
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
}
