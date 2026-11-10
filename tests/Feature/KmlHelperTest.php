<?php

namespace Tests\Feature;

use App\Helpers\KmlHelper;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KmlHelperTest extends TestCase
{
    private KmlHelper $kml_helper;

    /** @var \Illuminate\Contracts\Filesystem\Filesystem */
    private $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = Storage::fake('public');
        $this->kml_helper = new KmlHelper;
    }

    public function test_it_successfully_retrieves_kml_file_when_it_exists(): void
    {
        // Arrange: Add a mock file in the fake storage
        $releaseId = 'KMRLOpenData';
        $routeId = 'R1';
        $expectedPath = "KML/{$releaseId}/{$routeId}.kml";
        $mockKmlContent = '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://opengis.net"><Document></Document></kml>';
        $this->disk->put($expectedPath, $mockKmlContent);
        // Act: Call the Helper method
        $result = $this->kml_helper->getFile($releaseId, $routeId);
        // Assert: Check te contents of the file are same as the mock content
        $this->assertSame($mockKmlContent, $result);
    }

    public function test_it_throws_exception_or_returns_null_when_file_is_missing(): void
    {
        // Arrange: Disk is explicitly empty
        $releaseId = 'v1.0.0';
        $routeId = 'non-existent-route';
        // Act: Call the helper method
        $result = $this->kml_helper->getFile($releaseId, $routeId);
        // Assert: Verify null is returned
        $this->assertNull($result);
    }

    public function test_it_handles_empty_parameters_gracefully(): void
    {
        // Assert: Prepare for throwing an exception
        $this->expectException(\InvalidArgumentException::class);
        // Act: Run the hepper method
        $this->kml_helper->getFile('', 'route-123');
    }
}
