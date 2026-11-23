<?php

namespace App\DTOs;

readonly class StopData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $routeId,
        public string $stopId,
        public string $stopName,
        public string $stopSequence,
        public string $releaseName,
        public string $directionId,
    ) {}

    public static function fromArray(array $data): self
    {
        // Using camelCase inside DTOs follows PHP's PSR-12 coding
        return new self(
            routeId: (string) ($data['route_id'] ?? ''),
            stopId: (string) ($data['stop_id'] ?? ''),
            stopName: (string) ($data['stop_name'] ?? ''), // Old way: isset($data['stop_name']) ? $data['stop_name'] : '';
            stopSequence: (string) ($data['stop_sequence'] ?? '0'),
            releaseName: (string) ($data['release_name'] ?? ''),
            directionId: (string) ($data['direction_id'] ?? '0'),
        );
    }
}
