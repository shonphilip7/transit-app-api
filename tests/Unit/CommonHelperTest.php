<?php

namespace Tests\Unit;

use App\Helpers\CommonHelper;
use Tests\TestCase;

class CommonHelperTest extends TestCase
{
    protected CommonHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new CommonHelper;
    }

    public function test_it_finds_the_last_specific_day_of_the_week_for_a_given_year(): void
    {
        // Act: Run Sunday in 2025
        $result = $this->helper->getLastDaysOfYear('2025', 'Sunday');
        // Assert: Confirm it returns exactly December 28, 2025 in YYYYMMDD
        $this->assertEquals('20251228', $result);
    }
}
