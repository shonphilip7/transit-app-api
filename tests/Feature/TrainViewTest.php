<?php

namespace Tests\Feature;

use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TrainViewTest extends TestCase
{
    private array $sampleCalendar = [
        // Last Saturday of 2025 (2025-12-27)
        '20251227' => [
            'release_name' => 'KMRLOpenData',
            'service_id' => ['WE'],
        ],
        // Last Wednesday of 2025 (2025-12-31)
        '20251231' => [
            'release_name' => 'KMRLOpenData',
            'service_id' => ['WK'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Redis::connection()->flushdb();
        Storage::disk('public')->put('calendar.json', json_encode($this->sampleCalendar));
    }

    public function test_it_returns_next_four_upcoming_trips_per_direction_and_adjusts_2026_date_to_2025_weekday(): void
    {
        // Saturday, Nov 28, 2026 at 13:00:00 IST -> maps to Saturday 20251227 ('WE' service)
        $this->travelTo(Carbon::parse('2026-11-28 13:00:00', 'Asia/Kolkata'));
        $schedules = [
            // Past trip (before 13:00:00 -> excluded)
            $this->makeTrip('WE_1', 'WE', '0', '12:59:00', 'KMRLOpenData'),
            // Wrong service_id ('WK' instead of 'WE' -> excluded)
            $this->makeTrip('WK_1', 'WK', '0', '13:05:00', 'KMRLOpenData'),
            // Wrong release_name -> excluded
            $this->makeTrip('OLD_1', 'WE', '0', '13:06:00', 'OldRelease'),
            // 5 valid Outbound ('0') trips unsorted -> should sort and keep first 4
            $this->makeTrip('WE_OUT_5', 'WE', '0', '13:50:00'),
            $this->makeTrip('WE_OUT_2', 'WE', '0', '13:15:00'),
            $this->makeTrip('WE_OUT_1', 'WE', '0', '13:00:00'), // Exact boundary time -> included
            $this->makeTrip('WE_OUT_4', 'WE', '0', '13:40:00'),
            $this->makeTrip('WE_OUT_3', 'WE', '0', '13:30:00'),
            // 2 valid Inbound ('1') trips
            $this->makeTrip('WE_IN_2', 'WE', '1', '13:25:00'),
            $this->makeTrip('WE_IN_1', 'WE', '1', '13:10:00'),
        ];
        Storage::disk('public')->put('schedules/stops/R1/ALVA/schedule.json', json_encode($schedules));
        $response = $this->getJson('/api/trainview/R1/ALVA');
        $response->assertOk()
            ->assertJsonMissingPath('data')
            ->assertJsonCount(4, 'R1.Outbound')
            ->assertJsonCount(2, 'R1.Inbound')
            ->assertJsonPath('R1.Outbound.0.trip_id', 'WE_OUT_1')
            ->assertJsonPath('R1.Outbound.1.trip_id', 'WE_OUT_2')
            ->assertJsonPath('R1.Outbound.2.trip_id', 'WE_OUT_3')
            ->assertJsonPath('R1.Outbound.3.trip_id', 'WE_OUT_4')
            ->assertJsonPath('R1.Inbound.0.trip_id', 'WE_IN_1')
            ->assertJsonPath('R1.Inbound.1.trip_id', 'WE_IN_2');
        // Confirm both calendar_data and R1_ALVA_schedules were cached in Redis
        $this->assertNotNull(app(RedisService::class)->get('calendar_data'));
        $this->assertSame($schedules, app(RedisService::class)->get('R1_ALVA_schedules'));
    }

    public function test_it_returns_empty_arrays_when_no_upcoming_trips_remain_for_the_day(): void
    {
        // Wednesday, Nov 25, 2026 at 23:45:00 IST -> maps to Wednesday 20251231 ('WK' service)
        $this->travelTo(Carbon::parse('2026-11-25 23:45:00', 'Asia/Kolkata'));
        $schedules = [
            $this->makeTrip('WK_MORNING', 'WK', '0', '08:00:00'),
            $this->makeTrip('WK_EVENING', 'WK', '1', '22:30:00'),
        ];
        Storage::disk('public')->put('schedules/stops/R1/ALVA/schedule.json', json_encode($schedules));
        $response = $this->getJson('/api/trainview/R1/ALVA');
        $response->assertOk()
            ->assertExactJson([
                'R1' => [
                    'Inbound' => [],
                    'Outbound' => [],
                ],
            ]);
    }

    public function test_it_serves_from_redis_cache_on_cache_hit(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 09:00:00', 'Asia/Kolkata'));
        $cachedSchedules = [
            $this->makeTrip('WK_10', 'WK', '1', '09:15:00'),
        ];
        app(RedisService::class)->set('calendar_data', $this->sampleCalendar, 3600);
        app(RedisService::class)->set('R1_ALVA_schedules', $cachedSchedules, 3600);
        // Delete calendar.json and leave schedule.json absent to prove Redis is used
        Storage::disk('public')->delete('calendar.json');
        $response = $this->getJson('/api/trainview/R1/ALVA');
        $response->assertOk()
            ->assertJsonCount(1, 'R1.Inbound')
            ->assertJsonCount(0, 'R1.Outbound')
            ->assertJsonPath('R1.Inbound.0.trip_id', 'WK_10');
    }

    public function test_it_returns_404_when_schedule_file_is_missing(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 09:00:00', 'Asia/Kolkata'));
        // Without this line the PHPUnit throws error without completing the test
        $this->withoutExceptionHandling();
        // Registers a listner on the test to expect an exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schedule not found for route [R1] and stop [UNKNOWN].');
        $response = $this->getJson('/api/trainview/R1/UNKNOWN');
    }

    private function makeTrip(
        string $tripId,
        string $serviceId,
        string $directionId,
        string $arrivalTime,
        string $releaseName = 'KMRLOpenData',
    ): array {
        return [
            'route_id' => 'R1',
            'release_name' => $releaseName,
            'trip_id' => $tripId,
            'service_id' => $serviceId,
            'direction_id' => $directionId,
            'arrival_time' => $arrivalTime,
            'stop_id' => 'ALVA',
            'stop_name' => 'Aluva',
            'stop_sequence' => '1',
        ];
    }
}
