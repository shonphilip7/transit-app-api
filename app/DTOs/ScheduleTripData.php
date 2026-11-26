<?php

namespace App\DTOs;

readonly class ScheduleTripData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $routeId,
        public string $releaseName,
        public string $tripId,
        public string $serviceId,
        public string $directionId,
        public string $arrivalTime,
        public string $stopId,
        public string $stopName,
        public string $stopSequence,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routeId: (string) ($data['route_id'] ?? ''),
            releaseName: (string) ($data['release_name'] ?? ''),
            tripId: (string) ($data['trip_id'] ?? ''),
            serviceId: (string) ($data['service_id'] ?? ''),
            directionId: (string) ($data['direction_id'] ?? ''),
            arrivalTime: (string) ($data['arrival_time'] ?? ''),
            stopId: (string) ($data['stop_id'] ?? ''),
            stopName: (string) ($data['stop_name'] ?? ''),
            stopSequence: (string) ($data['stop_sequence'] ?? ''),
        );
    }
}
