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
        // Arrange: Put a fake file on the virtualized storage disk
        $mockData = ['status' => 'fresh_from_json'];
        Storage::disk('public')->put('calendar.json', json_encode($mockData));
        // Act: Call your helper method
        $result = $this->helper->getCalendarData();
        // Assert: Verify the method returned the correct data
        $this->assertEquals($mockData, $result);
        // Verify it actually saved to the real test Redis instance
        $savedRedisData = Redis::get('calendar_data');
        $this->assertEquals($mockData, json_decode($savedRedisData, true));
    }

    public function test_if_date_gets_adjusted(): void
    {
        /*
        Arrange: Put a fake file on the virtualized storage disk. Make sure all seven
        days of the week are covered.
        */
        $mockCalendarData = [
            '20251225' => [ // Thursday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
            '20251226' => [ // Friday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
            '20251227' => [ // Saturday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
            '20251228' => [ // Sunday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
            '20251229' => [ // Monday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
            '20251230' => [ // Tuesday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
            '20251231' => [ // Wednesday
                'release_name' => 'KMRLOpenData',
                'service_id' => ['WK'],
            ],
        ];
        Storage::disk('public')->put('calendar.json', json_encode($mockCalendarData));
        // Arrange: Get the current datetime and format it in YYYYmmdd
        $today = Carbon::today('Asia/Kolkata');
        $formattedToday = $today->format('Ymd');
        // Arrange: Get contents of the fake calendar.json
        $calendarFileContents = $this->helper->getCalendarData();
        // Assert: Verify the method returned the adjusted date
        $result = $this->helper->adjustedDate($formattedToday, $calendarFileContents, $today);
        // Verify the result returned is one of the keys. Need to typecast the result since it is a string.
        $this->assertContains((int) $result, array_keys($mockCalendarData));
    }
}
