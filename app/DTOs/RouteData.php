<?php

namespace App\DTOs;

readonly class RouteData
{
    /**
     * Create a new class instance. Every property inside
     * a readonly class must have an explicit type declaration
     */
    public function __construct(
        public string $releaseName,
        public string $routeId,
        public string $routeShortName,
        public string $routeLongName,
        public string $routeType,
    ) {}

    /**
     * Seperating how the object is build from the constructor is
     * industry standard.
     */
    public static function fromArray(array $data): self
    {
        // Typecast every property to avoid errors when null is present
        return new self(
            releaseName: (string) ($data['release_name'] ?? ''),
            routeId: (string) ($data['route_id'] ?? ''),
            routeShortName: (string) ($data['route_short_name'] ?? ''),
            routeLongName: (string) ($data['route_long_name'] ?? ''),
            routeType: (string) ($data['route_type'] ?? ''),
        );
    }
}
