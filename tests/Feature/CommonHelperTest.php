<?php

namespace Tests\Feature;

use App\Helpers\CommonHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommonHelperTest extends TestCase
{
    protected CommonHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        Storage::fake('public');
        $this->helper = new CommonHelper;
    }

    public function test_get_calendar_data_gets_data_from_redis_first(): void
    {
        // Arrange: Seed the hardcoded key using the native Redis facade
        $cachedData = ['status' => 'cached_in_redis'];
        Redis::set('calendar_data', json_encode($cachedData));
        // Act: Run the helper service method
        $result = $this->helper->getCalendarData();
        // Assert: It prefers the Redis data
        $this->assertEquals($cachedData, $result);
    }

    public function test_get_calendar_data_falls_back_to_file_when_redis_is_empty(): void
    {
        // 1. Arrange: Put a fake file on the virtualized storage disk
        $mockData = ['status' => 'fresh_from_json'];
        Storage::disk('public')->put('calendar.json', json_encode($mockData));
        // 2. Act: Call your helper method
        $result = $this->helper->getCalendarData();
        // 3. Assert: Verify the method returned the correct data
        $this->assertEquals($mockData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get('calendar_data');
        $this->assertEquals($mockData, json_decode($savedRedisData, true));
    }

    public function test_get_last_day_of_year(): void
    {
        $last_sunday = $this->helper->getLastDaysOfYear('2025', 'Sunday');
        $this->assertEquals('20251228', $last_sunday, 'The last Sunday of the year 2025 was 2025-12-28');
    }

    public function test_if_date_is_adjusted_returns_string(): void
    {
        $calendar_data = $this->helper->getCalendarData();
        $today = Carbon::today('Asia/Kolkata');
        $result = $this->helper->adjustedDate($today->format('Ymd'), $calendar_data, $today);
        $this->assertIsString($result);
    }
}
