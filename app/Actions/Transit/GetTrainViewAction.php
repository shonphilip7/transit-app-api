<?php

namespace App\Actions\Transit;

use App\DTOs\ScheduleTripData;
use App\DTOs\TrainViewResultData;
use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GetTrainViewAction
{
    private const CACHE_TTL_SECONDS = 3600;

    private const TIMEZONE = 'Asia/Kolkata';

    private const MAX_TRIPS_PER_DIRECTION = 4;

    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RedisService $redis,
        private readonly GetActiveCalendarEntryAction $getActiveCalendarEntry,
    ) {}

    public function execute(string $routeId, string $stopId, ?Carbon $now = null): TrainViewResultData
    {
        $currentTime = ($now ?? Carbon::now(self::TIMEZONE))->copy()->setTimezone(self::TIMEZONE);
        $currentTimeString = $currentTime->format('H:i:s');
        $calendarEntry = $this->getActiveCalendarEntry->execute($currentTime);
        $rawSchedules = $this->fetchSchedules($routeId, $stopId);
        $groupedUpcomingTrips = collect($rawSchedules)
            ->map(fn (array $item): ScheduleTripData => ScheduleTripData::fromArray($item))
            ->filter(
                fn (ScheduleTripData $trip): bool => $trip->releaseName === $calendarEntry->releaseName
                    && in_array($trip->serviceId, $calendarEntry->serviceIds, true)
                    && $trip->arrivalTime >= $currentTimeString
            )
            ->sortBy(fn (ScheduleTripData $trip): string => $trip->arrivalTime)
            ->groupBy(fn (ScheduleTripData $trip): string => $trip->directionId);
        $inbound = $groupedUpcomingTrips
            ->get('1', collect())
            ->take(self::MAX_TRIPS_PER_DIRECTION)
            ->values();
        $outbound = $groupedUpcomingTrips
            ->get('0', collect())
            ->take(self::MAX_TRIPS_PER_DIRECTION)
            ->values();

        return new TrainViewResultData(
            routeId: $routeId,
            inbound: $inbound,
            outbound: $outbound,
        );
    }

    private function fetchSchedules(string $routeId, string $stopId): array
    {
        $cacheKey = "{$routeId}_{$stopId}_schedules";
        $cached = $this->redis->get($cacheKey);
        if ($cached !== null) {
            return is_string($cached)
                ? json_decode($cached, true, 512, JSON_THROW_ON_ERROR)
                : $cached;
        }
        $filePath = "schedules/stops/{$routeId}/{$stopId}/schedule.json";
        $contents = Storage::disk('public')->get($filePath);
        if ($contents === null) {
            throw new RuntimeException("Schedule not found for route [{$routeId}] and stop [{$stopId}].");
        }
        $schedules = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->redis->set($cacheKey, $schedules, self::CACHE_TTL_SECONDS);

        return $schedules;
    }
}
