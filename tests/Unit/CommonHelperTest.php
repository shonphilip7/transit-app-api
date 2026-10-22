<?php

namespace Tests\Unit;

use App\Helpers\CommonHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommonHelperTest extends TestCase
{
    #[DataProvider('dateScenarioProvider')]
    public function test_it_finds_the_last_specific_day_of_the_week_for_a_given_year(string $year, string $dayOfWeek, string $expectedResult): void
    {
        // Act: Run Sunday in 2025
        $helper = new CommonHelper;
        $result = $helper->getLastDaysOfYear($year, $dayOfWeek);
        // Assert: Confirm it returns the last date of the day passed
        $this->assertEquals($expectedResult, $result);
    }

    public static function dateScenarioProvider(): array
    {
        return [
            'Standard Sunday 2025' => ['2025', 'Sunday', '20251228'],
            'Last day of the year' => ['2025', 'Wednesday', '20251231'],
            'Leap Year Dec 31st Match' => ['2028', 'Sunday', '20281231'],
        ];
    }
}
