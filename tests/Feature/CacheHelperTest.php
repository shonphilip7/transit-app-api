<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Helpers\CacheHelper;

class CacheHelperTest extends TestCase
{
    private $redis_service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->redis_service = new CacheHelper();
    }
    protected function tearDown(): void
    {
        $this->redis_service = null;
        parent::tearDown();
    }
    public function test_redis_can_connect(): void
    {
        $connection = false;
        $connection = $this->redis_service->connect();
        $this->assertTrue($connection, 'Not able to connect to redis');
    }
    public function test_redis_store_data(): void
    {
        $stored_data = false;
        $stored_data = $this->redis_service->set('Test', 'Value', 300);
        $this->assertTrue($stored_data, 'Not able to add data to redis');
    }
    public function test_redis_get_data(): void
    {
        $retrived_data = false;
        $retrived_data = $this->redis_service->get('Test');
        $this->assertEquals('Value', $retrived_data, 'Not able to get data to redis');
    }
}
