<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Helpers\CommonHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Helpers\KmlHelper;

class KmlController extends Controller
{
    public function index($route_id, $direction)
    {
        try {
            $calendar_data = array();
            $common_helper = new CommonHelper();
            $calendar_data = $common_helper->getCalendarData();
            if (count($calendar_data) >= 1) {
                /**
                 * The GTFS file from kochi metro only has entries till 20251231
                 * so need to improvise. 
                 */
                $today = Carbon::today('Asia/Kolkata');
                $formattedDate = $today->format('Ymd'); //Get current date
                $formattedDate = $common_helper->adjustedDate($formattedDate, $calendar_data, $today);
                $release = $calendar_data[$formattedDate]['release_name'];
                $services = $calendar_data[$formattedDate]['service_id'];
                $kml_helper = new KmlHelper();
                /**
                 * Get KML file contents
                 */
                $kml_content = $kml_helper->getFile($release, $route_id);
                $dom = new \DOMDocument();
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($kml_content);
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('kml', 'http://www.opengis.net/kml/2.2');
                /**
                 * Filter KML content by direction
                 */
                $filtered_by_direction_query = $kml_helper->filter($direction, false, $xpath);
                $kml_helper->removeUnwantedElements($filtered_by_direction_query);
                $filtered_kml = $dom->saveXML();
                /**
                 * Extract coordinates from the KML file
                 */
                $coordinateNodes = $kml_helper->filter(false, true, $xpath);
                $coordinates_list = $kml_helper->getCoordinates($coordinateNodes);
                return $coordinates_list;
            }
        } catch (Exception $e) {
            Log::error('Error message: Caught exception '.$e->getMessage());
        }
    }
}
