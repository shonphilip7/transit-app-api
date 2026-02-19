<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use DOMDocument;
/**
 * A custom helper class for stuff related to the KML API
 */
class KmlHelper
{
    /**
     * Read contents of the KML file
     * 
     * @param string $release_version The version of the GTFS
     * @param string $route The transit agency route
     * @return string $file_contents The KML contents
     */
    public function getFile($release_version, $route)
    {
        $file_contents = '';
        $file_contents = Storage::disk('public')->get('KML/'.$release_version.'/'.$route.'.kml');
        return $file_contents;
    }
    /**
     * Filter the contents based on the param passed. A direction param selects the placemarks
     * with the given direction. A coords param parses the contents to get value of the co-
     * ordinates node.
     * 
     * @param bool $direction Flag to filter by direction
     * @param bool $coords Flag to extract coorinates
     * @param object $xpath An instance of the DOMXPath class
     * @return object $filtered_placemarks Filter contents based on the flags passed  
     */
    public function filter($direction = false, $coords = false, $xpath)
    {
        $remove_direction = false;
        if ($direction !== false) {
            if ($direction == '1') {
                $remove_direction = '0';
            }
            if ($direction == '0') {
                $remove_direction = '1';
            }
        }
        if ($remove_direction !== false) {
            $filtered_placemarks = $xpath->query("//kml:Placemark[kml:ExtendedData/kml:Data[@name='direction_id']/kml:value =".$remove_direction."]");
        }
        if ($coords !== false) {
            $filtered_placemarks = $xpath->query("//kml:coordinates");
        } 
        return $filtered_placemarks;
    }
    /**
     * Remove placemarks from the KML content that do not meet the requirement.
     * @param object $filteredPlacemarks Child elements that need to be deleted from the KML file
     */
    public function removeUnwantedElements($filteredPlacemarks)
	{
		foreach ($filteredPlacemarks as $placemark) {
            $placemark->parentNode->removeChild($placemark);
		}
	}
    /**
     * Exctract coordinates from the coordinates node of the KML file.
     * 
     * @param object $node The coordinate node extracted from the KML file
     * @return array $list A sample structure: [["lat": "9.950794","lng": "76.351869","alt": "0.0"]]
     */
    public function getCoordinates($nodes)
    {
        $list = array();
        // Coordinates are space-separated points, and each point is comma-separated (longitude, latitude, altitude
        foreach ($nodes as $node) {
            $points = explode(' ', trim($node->nodeValue));
            foreach ($points as $point_str) {
                $coords = explode(',', $point_str);
                if (count($coords) >= 2) {
                    $longitude = trim($coords[0]);
                    $latitude = trim($coords[1]);
                    $altitude = isset($coords[2]) ? trim($coords[2]) : 0;
                    $list[] = array(
                        'lat' => $latitude,
                        'lng' => $longitude,
                        'alt' => $altitude
                    );
                }
            }
        }
        return $list;
    }
}