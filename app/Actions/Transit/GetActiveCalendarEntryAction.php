<?php

namespace App\Actions\Transit;

use App\DTOs\CalendarEntryData;
use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GetActiveCalendarEntryAction
{
    private const CACHE_KEY = 'calendar_data';

    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    private const FILE_PATH = 'calendar.json';

    private const TIMEZONE = 'Asia/Kolkata';

    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RedisService $redis
    ) {}

    public function execute(?Carbon $date = null): CalendarEntryData
    {
        $calendarData = $this->fetchCalendarData();
        if ($calendarData === []) {
            throw new RuntimeException('Calendar data is empty.');
        }
        $targetDate = ($date ?? Carbon::now(self::TIMEZONE))->copy()->setTimezone(self::TIMEZONE);
        $adjustedKey = $this->resolveAdjustedDateKey($targetDate, $calendarData);
        if (! array_key_exists($adjustedKey, $calendarData)) {
            throw new RuntimeException("No calendar entry found for date [{$adjustedKey}].");
        }

        return CalendarEntryData::fromArray($calendarData[$adjustedKey]);
    }

    private function resolveAdjustedDateKey(Carbon $targetDate, array $calendarData): string
    {
        $todayKey = $targetDate->format('Ymd');
        if (array_key_exists($todayKey, $calendarData)) {
            return $todayKey;
        }
        $lastAvailableKey = (string) max(array_keys($calendarData));
        $lastAvailableDate = Carbon::createFromFormat('Ymd', $lastAvailableKey, self::TIMEZONE)->startOfDay();
        if ($targetDate->startOfDay()->lte($lastAvailableDate)) {
            return $todayKey;
        }
        // If lastAvailableDate already matches today's day of week, use it; otherwise step back to the most recent matching weekday
        $adjustedDate = $lastAvailableDate->dayOfWeek === $targetDate->dayOfWeek
            ? $lastAvailableDate
            : $lastAvailableDate->copy()->previous($targetDate->dayOfWeek);

        return $adjustedDate->format('Ymd');
    }

    private function fetchCalendarData(): array
    {
        $cached = $this->redis->get(self::CACHE_KEY);
        if ($cached !== null) {
            return is_string($cached)
                ? json_decode($cached, true, 512, JSON_THROW_ON_ERROR)
                : $cached;
        }
        $contents = Storage::disk('public')->get(self::FILE_PATH);
        if ($contents === null) {
            throw new RuntimeException('Calendar JSON file not found.');
        }
        $calendarData = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->redis->set(self::CACHE_KEY, $calendarData, self::CACHE_TTL_SECONDS);

        return $calendarData;
    }
}
