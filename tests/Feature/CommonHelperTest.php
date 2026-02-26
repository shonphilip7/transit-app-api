<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Helpers\CommonHelper;
use Carbon\Carbon;

class CommonHelperTest extends TestCase
{
    private $helper;
    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new CommonHelper();
    }
    protected function tearDown(): void
    {
        $this->helper = null;
        parent::tearDown();
    }
    public function test_get_calendar_data(): void
    {
        $calendar_data = $this->helper->getCalendarData();
        $this->assertIsArray($calendar_data, 'Calendar data should be in array');
        $this->assertNotEmpty($calendar_data, 'Calendar data should not be empty');
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
