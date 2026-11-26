<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class TrainViewResultData
{
    /**
     * @param  Collection<int, ScheduleTripData>  $inbound
     * @param  Collection<int, ScheduleTripData>  $outbound
     */
    public function __construct(
        public string $routeId,
        public Collection $inbound,
        public Collection $outbound,
    ) {}
}
