<?php

namespace App\Actions\Transit;

use App\DTOs\StopData;
use App\Services\RedisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GetRouteStopsAction
{
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RedisService $redis
    ) {}

    /**
     * @return Collection<int, StopData>
     */
    public function execute(string $routeId): Collection
    {
        $rawStops = $this->fetchFromCacheOrStorage($routeId);

        $stops = collect($rawStops)
            ->map(fn (array $stop): StopData => StopData::fromArray($stop));
        // Dynamically pick the first direction, else default to '0'
        $targetDirection = $stops->first()?->directionId ?? '0';

        /*
         Filter the values based on direction else there will be duplicates as stops are
         same in either direction. Finally, sort by stop_sequence for better asthetic.
        */
        return $stops
            ->filter(fn (StopData $stop): bool => $stop->directionId === $targetDirection)
            ->sortBy(fn (StopData $stop): int => (int) $stop->stopSequence); // add ->values() at the end when ready to change the API structure to array
    }

    private function fetchFromCacheOrStorage(string $routeId): array
    {
        $cacheKey = "{$routeId}_stops";
        $cached = $this->redis->get($cacheKey);
        if ($cached !== null) {
            return is_string($cached)
                ? json_decode($cached, true, 512, JSON_THROW_ON_ERROR)
                : $cached;
        }
        $filePath = "stops/{$routeId}/stops.json";
        $contents = Storage::disk('public')->get($filePath);
        if ($contents === null) {
            throw new RuntimeException("Stops not found for route [{$routeId}].");
        }
        $stops = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->redis->set($cacheKey, $stops, self::CACHE_TTL_SECONDS);

        return $stops;
    }
}
