<?php

namespace App\DTOs;

readonly class CalendarEntryData
{
    /**
     * @param  array<int, string>  $serviceIds
     */
    public function __construct(
        public string $releaseName,
        public array $serviceIds,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            releaseName: (string) ($data['release_name'] ?? ''),
            // Guarantees every element inside is string and is sequential
            serviceIds: array_values(array_map('strval', $data['service_id'] ?? [])),
        );
    }
}
