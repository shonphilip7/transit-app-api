<?php

use App\Helpers\CommonHelper;

uses(Tests\TestCase::class);

test('it finds the last specific day of the week for a given year', function (string $year, string $dayOfWeek, string $expectedResult) {
    $helper = new CommonHelper;
    // Act: Call the helper method
    $result = $helper->getLastDaysOfYear($year, $dayOfWeek);
    // Assert: Confirm it returns the last date of the day passed
    expect($result)->toBe($expectedResult);
})->with([
    'Standard Sunday 2025' => ['2025', 'Sunday', '20251228'],
    'Last day of the year' => ['2025', 'Wednesday', '20251231'],
    'Leap Year Dec 31st Match' => ['2028', 'Sunday', '20281231'],
]);
