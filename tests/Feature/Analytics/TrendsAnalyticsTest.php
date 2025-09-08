<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsServiceInterface;
use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Group $group;
    private AnalyticsServiceInterface $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = $this->app->make(AnalyticsServiceInterface::class);
        
        $this->user = User::factory()->create();
        $this->group = Group::factory()->create(['creator_id' => $this->user->id]);
        
        // Add user as member
        $this->group->members()->attach($this->user->id, [
            'role' => 'admin',
            'joined_at' => now(),
            'is_active' => true,
            'turn_order' => 1,
        ]);
    }

    public function test_it_can_get_user_trends(): void
    {
        $this->createUserTurns();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'daily_activity' => [
                        '*' => [
                            'date',
                            'turns',
                            'completed',
                            'skipped',
                            'average_duration',
                        ],
                    ],
                    'weekly_trends' => [
                        '*' => [
                            'week_start',
                            'week_end',
                            'turns',
                            'completed',
                            'skipped',
                            'average_duration',
                        ],
                    ],
                    'completion_rates' => [
                        '*' => [
                            'week',
                            'completion_rate',
                            'total_turns',
                            'completed_turns',
                        ],
                    ],
                    'duration_trends' => [
                        '*' => [
                            'week',
                            'average_duration',
                            'total_duration',
                            'turn_count',
                        ],
                    ],
                    'average_response_time',
                    'total_turns',
                    'completed_turns',
                    'skipped_turns',
                    'completion_rate',
                    'period_start',
                    'period_end',
                ],
                'meta' => [
                    'user_id',
                    'user_name',
                    'analysis_period',
                    'trend_direction',
                ],
            ]);

        $this->assertEquals($this->user->id, $response->json('data.user_id'));
    }

    /** @test */
    public function it_can_get_user_trends_with_custom_days(): void
    {
        $this->createUserTurns();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends?days=14");

        $response->assertOk();
        
        $this->assertEquals(14, $response->json('meta.analysis_period.days'));
    }

    /** @test */
    public function it_validates_days_parameter(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends?days=500");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['days']);
    }

    /** @test */
    public function it_calculates_trend_direction_correctly(): void
    {
        $this->createIncreasingTurnTrend();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends?days=21");

        $response->assertOk();

        $trendDirection = $response->json('meta.trend_direction');
        $this->assertContains($trendDirection, ['increasing', 'decreasing', 'stable']);
    }

    /** @test */
    public function it_calculates_completion_rates_over_time(): void
    {
        $this->createVaryingCompletionRates();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");

        $response->assertOk();

        $completionRates = $response->json('data.completion_rates');
        $this->assertNotEmpty($completionRates);

        foreach ($completionRates as $rate) {
            $this->assertArrayHasKey('completion_rate', $rate);
            $this->assertGreaterThanOrEqual(0, $rate['completion_rate']);
            $this->assertLessThanOrEqual(100, $rate['completion_rate']);
        }
    }

    /** @test */
    public function it_calculates_duration_trends_properly(): void
    {
        $this->createVaryingDurations();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");

        $response->assertOk();

        $durationTrends = $response->json('data.duration_trends');
        $this->assertNotEmpty($durationTrends);

        foreach ($durationTrends as $trend) {
            $this->assertArrayHasKey('average_duration', $trend);
            $this->assertArrayHasKey('total_duration', $trend);
            $this->assertGreaterThanOrEqual(0, $trend['average_duration']);
            $this->assertGreaterThanOrEqual(0, $trend['total_duration']);
        }
    }

    /** @test */
    public function it_includes_daily_activity_data(): void
    {
        $this->createDailyTurns();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends?days=7");

        $response->assertOk();

        $dailyActivity = $response->json('data.daily_activity');
        $this->assertCount(7, $dailyActivity); // Should have 7 days of data

        foreach ($dailyActivity as $day) {
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('turns', $day);
            $this->assertArrayHasKey('completed', $day);
            $this->assertArrayHasKey('skipped', $day);
        }
    }

    /** @test */
    public function it_calculates_average_response_time(): void
    {
        $this->createTurnsWithResponseTimes();

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");

        $response->assertOk();

        $avgResponseTime = $response->json('data.average_response_time');
        $this->assertGreaterThanOrEqual(0, $avgResponseTime);
    }

    /** @test */
    public function it_handles_empty_user_data_gracefully(): void
    {
        // User with no turns
        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(0, $data['total_turns']);
        $this->assertEquals(0, $data['completed_turns']);
        $this->assertEquals(0, $data['skipped_turns']);
        $this->assertEquals(0, $data['average_response_time']);
    }

    /** @test */
    public function it_denies_access_to_other_users_trends(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");

        $response->assertForbidden();
    }

    /** @test */
    public function it_can_clear_user_analytics_cache(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/users/{$this->user->id}/analytics/cache");

        $response->assertOk()
            ->assertJson([
                'message' => 'User analytics cache cleared successfully',
                'user_id' => $this->user->id,
            ]);
    }

    /** @test */
    public function user_trends_are_cached(): void
    {
        $this->createUserTurns();

        // First request
        $start = microtime(true);
        $response1 = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");
        $firstRequestTime = microtime(true) - $start;

        $response1->assertOk();

        // Second request (should be cached)
        $start = microtime(true);
        $response2 = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends");
        $secondRequestTime = microtime(true) - $start;

        $response2->assertOk();

        // Second request should be faster (cached)
        $this->assertLessThan($firstRequestTime, $secondRequestTime);
        
        // Results should be identical
        $this->assertEquals(
            $response1->json('data.total_turns'),
            $response2->json('data.total_turns')
        );
    }

    /** @test */
    public function it_includes_proper_time_periods(): void
    {
        $this->createUserTurns();
        $days = 14;

        $response = $this->actingAs($this->user)
            ->getJson("/api/users/{$this->user->id}/analytics/trends?days={$days}");

        $response->assertOk();

        $periodStart = $response->json('data.period_start');
        $periodEnd = $response->json('data.period_end');

        $this->assertNotNull($periodStart);
        $this->assertNotNull($periodEnd);

        // Check that the period is approximately the requested days
        $startDate = \Carbon\Carbon::parse($periodStart);
        $endDate = \Carbon\Carbon::parse($periodEnd);
        $actualDays = $startDate->diffInDays($endDate);

        $this->assertGreaterThanOrEqual($days - 1, $actualDays);
        $this->assertLessThanOrEqual($days + 1, $actualDays);
    }

    private function createUserTurns(): void
    {
        // Create turns over the past 30 days
        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i);
            
            Turn::factory(rand(0, 3))->create([
                'group_id' => $this->group->id,
                'user_id' => $this->user->id,
                'status' => rand(0, 1) ? 'completed' : 'skipped',
                'started_at' => $date->copy()->addHours(rand(8, 18)),
                'completed_at' => function (array $attributes) {
                    return $attributes['started_at']->addMinutes(rand(5, 120));
                },
            ]);
        }
    }

    private function createIncreasingTurnTrend(): void
    {
        // Create an increasing trend over 3 weeks
        for ($week = 0; $week < 3; $week++) {
            $turnsThisWeek = ($week + 1) * 3; // 3, 6, 9 turns per week
            
            for ($i = 0; $i < $turnsThisWeek; $i++) {
                $date = now()->subWeeks(2 - $week)->addDays(rand(0, 6));
                
                Turn::factory()->create([
                    'group_id' => $this->group->id,
                    'user_id' => $this->user->id,
                    'status' => 'completed',
                    'started_at' => $date,
                    'completed_at' => function (array $attributes) {
                        return $attributes['started_at']->addMinutes(rand(5, 60));
                    },
                ]);
            }
        }
    }

    private function createVaryingCompletionRates(): void
    {
        // Create turns with varying completion rates by week
        $completionRates = [0.9, 0.7, 0.8, 0.6]; // 90%, 70%, 80%, 60%
        
        foreach ($completionRates as $weekIndex => $rate) {
            $weekStart = now()->subWeeks(3 - $weekIndex);
            $turnsCount = 10;
            $completedCount = (int) ($turnsCount * $rate);
            
            // Create completed turns
            for ($i = 0; $i < $completedCount; $i++) {
                Turn::factory()->create([
                    'group_id' => $this->group->id,
                    'user_id' => $this->user->id,
                    'status' => 'completed',
                    'started_at' => $weekStart->copy()->addDays(rand(0, 6)),
                    'completed_at' => function (array $attributes) {
                        return $attributes['started_at']->addMinutes(rand(5, 60));
                    },
                ]);
            }
            
            // Create skipped turns
            for ($i = $completedCount; $i < $turnsCount; $i++) {
                Turn::factory()->create([
                    'group_id' => $this->group->id,
                    'user_id' => $this->user->id,
                    'status' => 'skipped',
                    'started_at' => $weekStart->copy()->addDays(rand(0, 6)),
                    'completed_at' => function (array $attributes) {
                        return $attributes['started_at']->addMinutes(rand(1, 5));
                    },
                ]);
            }
        }
    }

    private function createVaryingDurations(): void
    {
        // Create turns with varying durations by week
        $avgDurations = [30, 45, 60, 25]; // minutes
        
        foreach ($avgDurations as $weekIndex => $avgDuration) {
            $weekStart = now()->subWeeks(3 - $weekIndex);
            
            for ($i = 0; $i < 5; $i++) {
                Turn::factory()->create([
                    'group_id' => $this->group->id,
                    'user_id' => $this->user->id,
                    'status' => 'completed',
                    'started_at' => $weekStart->copy()->addDays(rand(0, 6)),
                    'completed_at' => function (array $attributes) use ($avgDuration) {
                        $variance = $avgDuration * 0.3; // 30% variance
                        $duration = $avgDuration + rand(-$variance, $variance);
                        return $attributes['started_at']->addMinutes(max(5, $duration));
                    },
                ]);
            }
        }
    }

    private function createDailyTurns(): void
    {
        // Create turns for each of the past 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i);
            
            Turn::factory(rand(1, 4))->create([
                'group_id' => $this->group->id,
                'user_id' => $this->user->id,
                'status' => rand(0, 1) ? 'completed' : 'skipped',
                'started_at' => $date->copy()->setTime(rand(9, 17), rand(0, 59)),
                'completed_at' => function (array $attributes) {
                    return $attributes['started_at']->addMinutes(rand(5, 90));
                },
            ]);
        }
    }

    private function createTurnsWithResponseTimes(): void
    {
        // Create turns with specific response times
        $responseTimes = [15, 30, 45, 20, 60]; // minutes
        
        foreach ($responseTimes as $responseTime) {
            Turn::factory()->create([
                'group_id' => $this->group->id,
                'user_id' => $this->user->id,
                'status' => 'completed',
                'started_at' => now()->subDays(rand(1, 10)),
                'completed_at' => function (array $attributes) use ($responseTime) {
                    return $attributes['started_at']->addMinutes($responseTime);
                },
            ]);
        }
    }
}
