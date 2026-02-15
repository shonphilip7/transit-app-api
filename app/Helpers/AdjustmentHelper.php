<?php
namespace App\Helpers;

use Carbon\Carbon;
use App\Models\Adjustment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdjustmentHelper
{
    public function __construct()
    {}
    /**
     * Call detours API
     * 
     * Iterate through the results and only store detours that have a detour KML
     * as these KML's are required for Swiftly Adjustment API.
     * 
     * @param string $request_protocol HTTP or HTTPS
     * @param string $request_domain Dev/QA/Prod API domain
     * @return array $is_kml_detours Detours with parsed KML
     */
    public function getDetoursWithKML($request_protocol, $request_domain)
    {
        $detour_response = false;
        $is_kml_detours = array();
        $detour_response = Http::get($request_protocol.'://'.$request_domain.'/api/v2/detours/?debug=true');
        if ($detour_response->ok()) {
            if ($detour_response->collect()->count() >= 1) {
                foreach ($detour_response->object() as $detour_data) {
                    if ($detour_data->is_kml) {
                        $is_kml_detours[$detour_data->detour_id][$detour_data->route_id] = array(
                            'message' => $detour_data->detour_id.':'. $detour_data->message_hash,
                            'start' =>  Carbon::parse($detour_data->start,'America/New_York')->toIso8601String(),
                            'end' => Carbon::parse($detour_data->end,'America/New_York')->toIso8601String(),
                            'direction_id' => $detour_data->direction_id,
                            'detour_id' => $detour_data->detour_id,
                            'route_id' => $detour_data->route_id,
                            'active_days' => $detour_data->day_time_active_info
                        );
                    }
                }
            }
        }
        return $is_kml_detours;
    }
    /**
     * Swiftly adjustment recurrence rule
     * 
     * Recurrence rule created by parsing the active days and the end time of a detour.
     * A sample recurrence rule would look like this: FREQ=WEEKLY;COUNT=10;BYDAY=TU,TH
     * 
     * @param string $detour_end_date_time The date and time detour ends
     * @param array $day_time_active_info All the days the detours is active in a week
     * @return string|bool $detour_recurrences An adjustment recurrence rule or false
     */
    public function generateRecurrenceRule($detour_end_date_time, $day_time_active_info)
    {
        $detour_recurrences = false;
        $days = '';
        $recurrence_rule = 'FREQ=WEEKLY;UNTIL='.Carbon::parse($detour_end_date_time,'America/New_York')->toDateString().';BYDAY=';
        foreach ($day_time_active_info as $day => $frequency) {
            if ($frequency != 'None') {
                $days .= substr(strtoupper($day), 0, 2).','; //Mon -> MO, Tue -> TU ....
            }
        }
        $detour_recurrences = $recurrence_rule.''.rtrim($days, ',');
        return $detour_recurrences;
    }
    /**
     * Check detour has DB entry
     * 
     * @param string $detour_id SEPTA bus/trolley detour ID
     * @return bool $record Record exists or not
     */
    public function checkDetourDBRecords($detour_id)
    {
        $record = false;
        if(Adjustment::where('detour_id', $detour_id)->exists()) {
            $record = true;
        }
        return $record;
    }
    /**
     * Get the Detour shape line
     *
     * Get the detour linestring coordinates from the KML API. The result is part of
     * the payload passed to the Adjustment API's.
     *
     * @param string $route_id SEPTA bus/trolley Route ID
     * @param string $detour_id SEPTA bus/trolley detour ID
     * @return array $shapes SEPTA bus/trolley detour linestring coordinates
     */
     public function generateShape($route_id, $detour_id)
     {
        $shapes = array();
        $kml_url = env('DETOUR_API_DOMAIN_PROTOCOL').'://'.env('DETOUR_API_DOMAIN_NAME').'/api/v2/kml/?route_id='.urlencode($route_id);
        $kml_data = file_get_contents($kml_url);
        $detour_kml = collect();
        $line_string = '';
        $detour_latlons = array();
        if ($kml_data) {
            $kml_xml = simplexml_load_string($kml_data);
            if ($kml_xml) {
                $kml_parsed = json_decode(json_encode($kml_xml), true);
                if (is_array($kml_parsed)) {
                    $searchValue = env('DETOUR_KML_KEY').''. $detour_id;
                    if (isset($kml_parsed['Document']['Placemark'])) {
                        $detour_kml = collect($kml_parsed['Document']['Placemark'])->filter(function($item) use ($searchValue) {
                            $kml_tags = data_get($item, 'name');
                            if ($kml_tags == $searchValue) {
                                return true;
                            }
                        });
                        $line_string = null;
                        $line_string = implode(',', $detour_kml->pluck('LineString.coordinates')->toArray());
                        if(!is_null($line_string)) {
                            $detour_latlons = array();
                            $detour_latlons = explode(',0.0',$line_string);
                            if (count($detour_latlons) >= 1) {
                                foreach($detour_latlons as $latlon) {
                                    if (strlen($latlon) >= 1) {
                                        $detour_line_array = explode(',', $latlon);
                                        $detour_line_adjusted = [(float)$detour_line_array[1], (float)$detour_line_array[0]];
                                        $shapes[] = $detour_line_adjusted;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $shapes;
     }
     /**
     * Swiftly API payload
     *
     * Generate the payload for Create/Update API as per swiftly documentation.
     * Adjustments is created one detour at a time cause of the Swiftly API limitaions. This function also parses 
     * the KML API to get the detour KML.
     *
     * @param array $adjustment_data A single detour with all info required for payload
     * @return array $shapes SEPTA bus/trolley detour linestring coordinates
     * @return array $payload The payload to be fed to the Create/Update API
     */
     public function generatePayload($adjustment_data, $shapes)
     {
        $payload = array();
        $route_id = null;
        $route_id = array_key_first($adjustment_data);
        $detour_data = $adjustment_data[$route_id];
        $payload['payload']['feedId'] = env('SWIFTLY_API_FEED');
        $payload['payload']['feedName'] = 'detour-adjustment';
        $payload['payload']['notes'] = $detour_data['message'];
        if (isset($detour_data['reason'])) {
            $payload['payload']['reason'] = $detour_data['reason'];
        }
        $payload['payload']['details']['adjustmentType'] = 'DETOUR_V0';
        $payload['payload']['details']['beginTime'] = $detour_data['start'];
        $payload['payload']['details']['endTime'] = $detour_data['end'];
        $payload['payload']['details']['recurrenceProperties']['firstOccurrenceStartTime'] = $detour_data['start'];
        $payload['payload']['details']['recurrenceProperties']['firstOccurrenceEndTime'] = Carbon::parse (Carbon::parse($detour_data['start'])->toDateString().' '.Carbon::parse($detour_data['end'])->toTimeString(), 'America/New_York')->toIso8601String();
        $payload['payload']['details']['recurrenceProperties']['recurrenceRule'] = $detour_data['recurrence_rule'];
        $payload['payload']['details']['detourRouteDirectionDetails'] = array_values(collect($adjustment_data)->map(function($item) use ($shapes) {
            return [
                'routeShortName' => $item['route_id'],
                'direction' => $item['direction_id'],
                'shape' => $shapes
            ];
        })->toArray());
        return $payload;
     }
     /**
      * Call the create Swiftly Adjustment API
      * 
      * More details: https://docs.goswift.ly/docs/swiftly-docs/6zpcgvbu5wbb3-swiftly-api-reference/operations/create-a-adjustment
      *
      * @param array $payload The payload to be fed to the Create/Update API
      * @param array $api_header Header for the API
      * @return bool|string $api_response JSON string if API is successful or false if there was an error 
      */
     public function createAdjustmentAPICall($payload, $api_header)
     {
        $api_response = false;
        $create_adjustment_response = Http::withHeaders($api_header)->post(env('SWIFTLY_API_URL').'/adjustments?agency='.env('SWIFTLY_API_FEED'), $payload['payload']);
        if ($create_adjustment_response->status() == 200) {
            $api_response = $create_adjustment_response->json();
        }
        return $api_response;
     }
     /**
      * Insert record to DB
      *
      * Insert the payload and API response into the database.
      *
      * @param string $detour_id The detour id which acts as a primary key for the table entry
      * @param array $payload The payload for the API call
      * @param string $response The response from the API call
      * @return bool True if data successfully inserted else false.
      */
     public function insertRecord($detour_id, $payload, $response)
     {
        $adjustment_table = new Adjustment();
        $adjustment_table->detour_id = $detour_id;
        $adjustment_table->payload = json_encode($payload);
        $adjustment_table->response = json_encode($response);
        if ($adjustment_table->save()) {
            return true;
        } else {
            return false;
        }
     }
}