<?php

namespace Tests\Unit;

use App\Helpers\KmlHelper;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\DataProviders\TestData;

class KmlHelperTest extends TestCase
{
    private KmlHelper $kml_helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kml_helper = new KmlHelper;
    }

    /**
     * Helper to quickly build a DOMXPath object from a KML string fragment.
     */
    private function createXPath(string $kmlContent): DOMXPath
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        // Prevent warnings on custom or legacy KML tags. Any error message
        // or warning will be suppressed.
        @$dom->loadXML($kmlContent);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('kml', 'http://www.opengis.net/kml/2.2');

        return $xpath;
    }

    #[DataProvider('kmlVariations')]
    public function test_it_filters_when_flag_set(
        string|bool $direction,
        bool $coordinates,
        string $kml_content,
        string $expected_type,
        int $expected_tag_count,
        string $expected_tag_name,
        string $expected_node_value,
        string $xpath_query
    ): void {
        // Arrange: Create KML structure similar to the actual KML file
        $xpath = $this->createXPath($kml_content);
        // Act: Execute the helper method
        $result = $this->kml_helper->filter($direction, $coordinates, $xpath);
        if (is_array($result)) {
            // Assert: An empty array is returned if the filters fail
            $this->assertEmpty($result);
        } else {
            // Assert: KML contents are filtered correctly based on the parameters passed
            $this->assertInstanceOf($expected_type, $result);
            $this->assertTrue($result->length > 0, 'No placemarks returned from the helper.');
            $this->assertEquals($expected_tag_count, $result->length);
            $matchedPlacemark = $result->item(0);
            $this->assertEquals($expected_tag_name, $matchedPlacemark->nodeName);
            $valueQuery = $xpath_query;
            $valueNodes = $xpath->query($valueQuery, $matchedPlacemark);
            $this->assertEquals($expected_node_value, trim($valueNodes->item(0)->nodeValue));
        }
        /*// Check if the root element itself can still be found. The commented code is used for debugging KML structure
        // $nodes = $xpath->query("//kml:Placemark[kml:ExtendedData/kml:Data[@name='direction_id']/kml:value=".$direction.']');
        $nodes = $xpath->query('//kml:coordinates');
        echo 'Placemarks with direction_id found: '.$nodes->length."\n";
        echo "==================================================\n";
        exit;
        $result = $this->kml_helper->filter($direction, false, $xpath);
        $matchedPlacemark = $result->item(0);
        echo "\n================= PLACEMARK CHILD DEEP DIVE =================\n";
        echo 'Target Node: <'.$matchedPlacemark->nodeName.">\n\n";
        // 2. Query ALL immediate child elements of this placemark using the dot '.'
        $children = $xpath->query('./*', $matchedPlacemark);
        foreach ($children as $child) {
            echo 'Found Child Tag: <'.$child->nodeName.">\n";
            // If it's ExtendedData, drill down one layer deeper into its <Data> tags
            if ($child->nodeName === 'ExtendedData') {
                $dataNodes = $xpath->query('./kml:Data', $child);
                foreach ($dataNodes as $data) {
                    echo ' <Data> name attribute: '.$data->getAttribute('name')."\n";
                    $valueNodes = $xpath->query('./kml:value', $data);
                    if ($valueNodes->length > 0) {
                        echo '        └── <value>: '.$valueNodes->item(0)->nodeValue."\n";
                    }
                }
            }
        }
        echo "=============================================================\n";
        exit;*/
    }

    #[DataProvider('deleteVariations')]
    public function test_removes_nodes_from_their_parent_elements(
        string $kml_content,
        string $delete_xpath_query,
        string $remaining_xpath_query,
        string $delete_node_value,
        ?string $element_value,
        int $number_of_nodes_to_delete,
        int $remaining_node_count,
        int $element_count
    ): void {
        // Arrange: Create KML structure similar to the actual KML file and select the nodes to be deleted
        $xpath = $this->createXPath($kml_content);
        $nodesToDelete = $xpath->query($delete_xpath_query);
        $this->assertEquals($number_of_nodes_to_delete, $nodesToDelete->length);
        // Act: Call the helper method
        $this->kml_helper->removeUnwantedElements($nodesToDelete);
        $deletedNodeCheck = $xpath->query($delete_xpath_query);
        // Assert: Verify the node is deleted
        $this->assertEquals(0, $deletedNodeCheck->length, 'The Placemark with value '.$delete_node_value.' was not removed.');
        // Assert: Verify the remaining node still exists safely in the document
        $remainingNodes = $xpath->query($remaining_xpath_query);
        // Assert: Ensure we actually got a match
        $this->assertEquals($remaining_node_count, $remainingNodes->length);
        // Grab the first DOMElement item from the list
        $node_tag_name = $remainingNodes->item(0)->nodeName;
        if ($node_tag_name == 'Placemark') {
            // Look specifically for the <value> element inside this Placemark
            $valueElements = $remainingNodes->item(0)->getElementsByTagName('value');
            // Assert: The <value> element exists
            $this->assertEquals($element_count, $valueElements->length);
            // Assert: Pulled text content from the element matches what is expected
            $this->assertEquals($element_value, $valueElements->item(0)->nodeValue);
        }
        // Run this when all child elements are removed
        if ($node_tag_name == 'Document') {
            $documentChildren = $xpath->query('./*', $remainingNodes->item(0));
            // Assert: Verify the KML does not have any tag after 'Document'
            $this->assertEquals(0, $documentChildren->length, 'The Document tag is not completely empty.');
        }
    }

    /**
     * Edge cases for the filter method of KML helper
     */
    public static function kmlVariations(): array
    {
        $sample_kml_content = TestData::getValidKml();

        return [
            'Filtered by inbound direction' => [
                'direction' => '1',
                'coordinates' => false,
                'kml_content' => $sample_kml_content,
                'expected_type' => \DOMNodeList::class,
                'expected_tag_count' => 1,
                'expected_tag_name' => 'Placemark',
                'expected_node_value' => '0', // The helper method should return tags with opposite direction
                'xpath_query' => ".//kml:ExtendedData/kml:Data[@name='direction_id']/kml:value",
            ],
            'Filtered by outbound direction' => [
                'direction' => '0',
                'coordinates' => false,
                'kml_content' => $sample_kml_content,
                'expected_type' => \DOMNodeList::class,
                'expected_tag_count' => 1,
                'expected_tag_name' => 'Placemark',
                'expected_node_value' => '1',
                'xpath_query' => ".//kml:ExtendedData/kml:Data[@name='direction_id']/kml:value",
            ],
            'Invalid direction' => [
                'direction' => '77',
                'coordinates' => false,
                'kml_content' => $sample_kml_content,
                'expected_type' => '',
                'expected_tag_count' => 0,
                'expected_tag_name' => '',
                'expected_node_value' => '',
                'xpath_query' => '',
            ],
            'Filter by coordinates' => [
                'direction' => false,
                'coordinates' => true,
                'kml_content' => $sample_kml_content,
                'expected_type' => \DOMNodeList::class,
                'expected_tag_count' => 2,
                'expected_tag_name' => 'coordinates',
                'expected_node_value' => '76.351869,9.950794,0.0 76.350592,9.953449,0.0 76.350228,9.954199,0.0',
                'xpath_query' => '//kml:coordinates',
            ],
        ];
    }

    /**
     * Edge cases for the removeUnwantedElements method of KML helper
     */
    public static function deleteVariations(): array
    {
        $sample_kml_content = TestData::getValidKml();

        return [
            'Remove node with value 1' => [
                'kml_content' => $sample_kml_content,
                'delete_xpath_query' => "//kml:Placemark[kml:ExtendedData/kml:Data/kml:value = '1']",
                'remaining_xpath_query' => "//kml:Placemark[kml:ExtendedData/kml:Data/kml:value = '0']",
                'delete_node_value' => '1',
                'element_value' => '0',
                'number_of_nodes_to_delete' => 1,
                'remaining_node_count' => 1,
                'element_count' => 1,
            ],
            'Remove all nodes' => [
                'kml_content' => $sample_kml_content,
                'delete_xpath_query' => "//kml:Placemark[kml:ExtendedData/kml:Data/kml:value = '1' or kml:ExtendedData/kml:Data/kml:value = '0']",
                'remaining_xpath_query' => '//kml:Document',
                'delete_node_value' => '1 or 0',
                'element_value' => null,
                'number_of_nodes_to_delete' => 2,
                'remaining_node_count' => 1,
                'element_count' => 0,
            ],
        ];
    }
}
