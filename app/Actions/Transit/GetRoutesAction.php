<?php

namespace App\Actions\Transit;

use App\DTOs\RouteData;
use App\Services\RedisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GetRoutesAction
{
    private const CACHE_KEY = 'routes';

    private const CACHE_TTL_SECONDS = 86400;

    private const FILE_PATH = 'routes.json';

    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RedisService $redis
    ) {}

    /**
     * @return Collection<int, RouteData>
     */
    public function execute(): Collection
    {
        $rawRoutes = $this->fetchFromCacheOrStorage();

        /*
          Convert plain PHP array of raw route data into a strongly-typed Laravel Collection of
          RouteData DTO objects
        */
        return collect($rawRoutes)
            ->map(fn (array $route): RouteData => RouteData::fromArray($route));
    }

    private function fetchFromCacheOrStorage(): array
    {
        $cached = $this->redis->get(self::CACHE_KEY);
        if ($cached !== null) {
            return is_string($cached) ? json_decode($cached, true, 512, JSON_THROW_ON_ERROR) : $cached;
        }
        $contents = Storage::disk('public')->get(self::FILE_PATH);
        // Using JSON_THROW_ON_ERROR & checking $contents prevents silent null bugs if JSON file missing
        if ($contents === null) {
            throw new RuntimeException('Transit routes JSON file not found.');
        }
        $routes = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->redis->set(self::CACHE_KEY, json_encode($routes, JSON_THROW_ON_ERROR), self::CACHE_TTL_SECONDS);

        return $routes;
    }
}
