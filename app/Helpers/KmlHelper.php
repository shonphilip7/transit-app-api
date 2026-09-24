<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

/**
 * A custom helper class for stuff related to the KML API
 */
class KmlHelper
{
    /**
     * Read contents of the KML file
     *
     * @param  string  $release_version  The version of the GTFS
     * @param  string  $route  The transit agency route
     * @return string $file_contents The KML contents
     */
    public function getFile(string $release_version, string $route): string
    {
        $file_contents = '';
        $file_contents = Storage::disk('public')->get('KML/'.$release_version.'/'.$route.'.kml');

        return $file_contents;
    }

    /**
     * Filter the contents based on the param passed. A direction param selects the placemarks
     * with the given direction. A coords param parses the contents to get value of the co-
     * ordinates node.
     */
    public function filter(string|bool $direction, bool $coords, \DOMXPath $xpath): \DOMNodeList|array
    {
        $filtered_placemarks = [];
        $remove_direction = false;
        if ($direction !== false) {
            // If the direction is set to '1' in the API call then remove all instances of direction '0' and vice versa.
            if ($direction == '1') {
                $remove_direction = '0';
            }
            if ($direction == '0') {
                $remove_direction = '1';
            }
        }
        if ($remove_direction !== false) {
            $filtered_placemarks = $xpath->query("//kml:Placemark[kml:ExtendedData/kml:Data[@name='direction_id']/kml:value =".$remove_direction.']');
        }
        if ($coords !== false) {
            $filtered_placemarks = $xpath->query('//kml:coordinates');
        }

        return $filtered_placemarks;
    }

    // Remove placemarks from the KML content that do not meet the requirement.
    public function removeUnwantedElements(\DOMNodeList $filteredPlacemarks): void
    {
        // Convert the live node list into a static array to prevent index tracking bugs.
        /*
        What is this bug?
        Imagine you have 4 coordinate tags in a row: [Tag0, Tag1, Tag2, Tag3].
        1) Loop Loop 1: PHP looks at index 0 (Tag0) and deletes it.
        2) The Shift: Because the list is live, the remaining items instantly shift down to fill the gap.
        Your list is now [Tag1, Tag2, Tag3].
        3) Loop Loop 2: PHP advances its internal counter to index 1. But index 1 is now Tag2! Tag1 was
        skipped entirely.
        This function dumps XML into a static PHP array
        */
        $nodesToDelete = iterator_to_array($filteredPlacemarks);
        /** @var object $placemark */
        foreach ($nodesToDelete as $placemark) {
            if ($placemark->parentNode) {
                $placemark->parentNode->removeChild($placemark);
            }
        }
    }

    /**
     * Exctract coordinates from the coordinates node of the KML file.
     */
    public function getCoordinates(\DOMNodeList $nodes): array
    {
        $list = [];
        // Coordinates are space-separated points, and each point is comma-separated (longitude, latitude, altitude
        foreach ($nodes as $node) {
            $points = explode(' ', trim($node->nodeValue));
            foreach ($points as $point_str) {
                $coords = explode(',', $point_str);
                if (count($coords) >= 2) {
                    $longitude = trim($coords[0]);
                    $latitude = trim($coords[1]);
                    $altitude = isset($coords[2]) ? trim($coords[2]) : 0;
                    $list[] = [
                        'lat' => $latitude,
                        'lng' => $longitude,
                        'alt' => $altitude,
                    ];
                }
            }
        }

        return $list;
    }
}
