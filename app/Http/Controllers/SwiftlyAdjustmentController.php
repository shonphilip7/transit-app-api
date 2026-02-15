<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Helpers\AdjustmentHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
//use App\Models\Adjustment;

class SwiftlyAdjustmentController extends Controller
{
    /**
     * Creating/updating/deleting Swiftly service adjustments.
     * https://docs.goswift.ly/docs/swiftly-docs/6zpcgvbu5wbb3-swiftly-api-reference/operations/create-a-adjustment
     */
    public function crudAdjustments()
    {
        /*$posts = Adjustment::all();
        foreach ($posts as $post) {
            echo $post->detour_id.' '.$post->payload.' '.$post->response.'</br>';
        }
        dd();*/
        $response = array();
        $request_protocol = env('DETOUR_API_DOMAIN_PROTOCOL');
        $request_domain = env('DETOUR_API_DOMAIN_NAME');
        $swiftly_api_header = array(
            "Authorization" => env('SWIFTLY_API_KEY'),
            "Content-Type" => "application/json",
            "Accept" => "application/json"
        );
        $detours_with_kml = array();
        $adjustment_helper = new AdjustmentHelper();
        /**
         * Parse detours API to get all detours with parsed KML
         */
        $detours_with_kml = $adjustment_helper->getDetoursWithKML($request_protocol, $request_domain);
        if (count($detours_with_kml) >= 1) {
            $alerts = collect([]);
            $adjustment_action = array();
            $is_recorded = false;
            /**
             * Call the alerts API to get detour reason
             */
            $alert_response = Http::get($request_protocol.'://'.$request_domain.'/api/v2/alerts/');
            if ($alert_response->ok()) {
                $alerts = $alert_response->collect();
            }
            foreach ($detours_with_kml as $detour_id => $detour) {
                foreach ($detour as $route_id => $route_detour) {
                    /**
                     * Get the frequency of detours.(Ex: Mon-Fri or weekends only)
                     */
                    $detour_recurrence_rule = $adjustment_helper->generateRecurrenceRule($route_detour['end'], $route_detour['active_days']);
                    if ($detour_recurrence_rule) {
                        $detours_with_kml[$detour_id][$route_id]['recurrence_rule'] = $detour_recurrence_rule;
                    }
                    if ($alerts->firstWhere('alert_id', $detour_id)) {
                        /**
                         * Get the detour reason from alerts API
                         */
                        $detours_with_kml[$detour_id][$route_id]['reason'] = $alerts->firstWhere('alert_id', $detour_id)['cause'];
                    }
                }
            }
            foreach ($detours_with_kml as $detour_id => $detour_item) {
                /**
                 * Check if detour has a DB entry
                 */
                $is_recorded = $adjustment_helper->checkDetourDBRecords($detour_id);
                if(!$is_recorded) {
                    /**
                     * Populate an array of detours that require an adjustment call
                     */
                    $adjustment_action['create'][] = $detour_id;
                }
            }
            if (count($adjustment_action['create']) >= 1) {
                /**
                 * Get only the first detour from the list as we do not want to overload the Swiftly server.
                 */
                $detour_id = $adjustment_action['create'][array_key_first($adjustment_action['create'])];
                if (count($detours_with_kml[$detour_id]) >= 1) {
                    $insert = false;
                    /**
                     * If there are two or more routes associated with a detour_id then get the first route
                     * as detour details are same for all routes. 
                     */
                    $route_id = array_key_first($detours_with_kml[$detour_id]);
                    /**
                     * Get the detour shape.
                     */
                    $detour_shape = $adjustment_helper->generateShape($route_id, $detour_id);
                    if (count($detour_shape) >= 1) {
                        $payload = array();
                        /**
                         * Get the payload for the API
                         */
                        $payload = $adjustment_helper->generatePayload($detours_with_kml[$detour_id], $detour_shape);
                        if (count($payload) >= 1) {
                            $api_response = false;
                            /**
                             * Call the Swiftly adjustment API
                             */
                            $api_response = $adjustment_helper->createAdjustmentAPICall($payload, $swiftly_api_header);
                            if ($api_response) {
                                $response = array('error' => false, 'reason' => 'Created adjustment for detour '.$detour_id);
                                /**
                                 * Insert the payload and API response to the database.
                                 */
                                $insert = $adjustment_helper->insertRecord($detour_id, $payload, $api_response);
                                if ($insert) {
                                    Log::info('Created adjustment for detour '.$detour_id.' and successfully inserted to DB');
                                    $response = array('error' => false, 'reason' => 'Created adjustment for detour '.$detour_id.' and successfully inserted to DB');
                                } else {
                                    Log::info('Created adjustment for detour '.$detour_id.' and but there was an issue inserting to DB');
                                    $response = array('error' => true, 'reason' => 'Created adjustment for detour '.$detour_id.' but there was an issue inserting to DB');
                                }
                            }
                        } else {
                            Log::info('No payload generated for '.$detour_id);
                            $response = array('error' => true, 'reason' => 'No payload generated for '.$detour_id);
                            $insert = $adjustment_helper->insertRecord($detour_id, array('error' => true, 'reason' => 'No payload'), '');
                            if ($insert) {
                                Log::info('Successfully inserted into DB. Detour '.$detour_id);
                                $response = array('error' => true, 'reason' => 'No payload generated for '.$detour_id.' but successfully inserted into DB');
                            } else {
                                Log::info('Issue inserting into DB. Detour '.$detour_id);
                                $response = array('error' => true, 'reason' => 'No payload generated for '.$detour_id.' and issue inserting into DB');
                            }
                        }
                    } else {
                        Log::info('No shape file generated for '.$detour_id);
                        $response = array('error' => true, 'reason' => 'No shape file generated for '.$detour_id);
                        $insert = $adjustment_helper->insertRecord($detour_id, array('error' => true, 'reason' => 'No shape'), '');
                        if ($insert) {
                            Log::info('Successfully inserted into DB. Detour '.$detour_id);
                            $response = array('error' => true, 'reason' => 'No shape file generated for '.$detour_id.' but successfully inserted into DB');
                        } else {
                            Log::info('Issue inserting into DB. Detour '.$detour_id);
                            $response = array('error' => true, 'reason' => 'No shape file generated for '.$detour_id.' and issue inserting into DB');
                        }
                    }
                } else {
                    $response = array('error' => true, 'reason' => 'No detour details');
                }
            } else {
                $response = array('error' => true, 'reason' => 'No new detours to call');
            } 
        } else {
            $response = array('error' => true, 'reason' => 'No detours with parsed kml');
        }
        return response()->json($response);
    }
}
