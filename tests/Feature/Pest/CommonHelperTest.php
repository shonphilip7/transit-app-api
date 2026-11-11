<?php

use App\Helpers\CommonHelper;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Redis::connection()->flushdb();
    Storage::fake('public');
    $this->helper = new CommonHelper;
});

test('it gets calendar data from redis first', function () {
    // Arrange: Seed the hardcoded key using the native Redis facade
    $cachedData = ['status' => 'cached_in_redis'];
    Redis::set('calendar_data', json_encode($cachedData));
    // Act: Run the helper service method
    $result = $this->helper->getCalendarData();
    // Assert: It prefers the Redis data
    expect($result)->toBe($cachedData);
});
test('it gets calendar data from file if redis empty', function () {
    $day_in_seconds = 86400;
    // Arrange: Put a fake file on the virtualized storage disk
    $mockData = ['status' => 'fresh_from_json'];
    Storage::disk('public')->put('calendar.json', json_encode($mockData));
    // Act: Call your helper method
    $result = $this->helper->getCalendarData();
    // Assert: Verify the method returned the correct data
    expect($result)->toBe($mockData);
    // Verify it actually saved to the real test Redis instance
    $savedRedisData = Redis::get('calendar_data');
    expect(json_decode($savedRedisData, true))->toBe($mockData);
    // Verify the expiry is one day
    $ttl = Redis::ttl('calendar_data');
    expect($ttl)->toBe($day_in_seconds);
});
