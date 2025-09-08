<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Contracts\AnalyticsServiceInterface;
use App\Domain\Analytics\DTOs\AnalyticsDto;
use App\Domain\Analytics\DTOs\FairnessMetricsDto;
use App\Domain\Analytics\DTOs\TrendDataDto;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TurnAnalyticsService implements AnalyticsServiceInterface
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly PercentileCalculator $percentileCalculator,
        private readonly FairnessScorer $fairnessScorer,
        private readonly HistoricalAggregator $historicalAggregator
    ) {}

    /**
     * Get comprehensive analytics for a group
     */
    public function getGroupAnalytics(
        Group $group,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): AnalyticsDto {
        $cacheKey = $this->generateAnalyticsCacheKey($group, $startDate, $endDate);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $startDate, $endDate) {
            // Get duration percentiles
            $durationPercentiles = $this->percentileCalculator->calculateGroupPercentiles(
                $group,
                [50, 75, 90, 95, 99],
                $startDate,
                $endDate
            );

            // Get fairness metrics
            $fairnessMetrics = $this->fairnessScorer->calculateGroupFairness($group);

            // Get historical data
            $weeklyActivity = $this->historicalAggregator->getWeeklyActivity($group, 12);
            $membershipTrends = $this->historicalAggregator->getMembershipTrends($group, 12);
            $peakUsageTimes = $this->historicalAggregator->getPeakUsageTimes($group, 30);

            // Calculate aggregated metrics
            $turnStats = $this->calculateTurnStatistics($group, $startDate, $endDate);

            return new AnalyticsDto(
                groupId: $group->id,
                durationPercentiles: $durationPercentiles,
                fairnessMetrics: $fairnessMetrics,
                weeklyActivity: $weeklyActivity,
                membershipTrends: $membershipTrends,
                peakUsageTimes: $peakUsageTimes,
                averageSessionDuration: $turnStats['average_duration'],
                totalTurns: $turnStats['total_turns'],
                activeTurns: $turnStats['active_turns'],
                generatedAt: now()
            );
        });
    }

    /**
     * Get fairness metrics for a group
     */
    public function getGroupFairness(Group $group): FairnessMetricsDto
    {
        return $this->fairnessScorer->calculateGroupFairness($group);
    }

    /**
     * Get trend data for a user
     */
    public function getUserTrends(User $user, int $days = 30): TrendDataDto
    {
        $cacheKey = "analytics:user_trends:user:{$user->id}:days:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $days) {
            $startDate = now()->subDays($days);
            $endDate = now();

            // Get user trend data from historical aggregator
            $trendsData = $this->historicalAggregator->getUserTrends($user, $days);

            // Get user-specific percentiles
            $userPercentiles = $this->percentileCalculator->calculateUserPercentiles(
                $user,
                [50, 95, 99],
                $startDate,
                $endDate
            );

            return new TrendDataDto(
                userId: $user->id,
                dailyActivity: $trendsData['daily_activity'],
                weeklyTrends: $trendsData['weekly_trends'],
                completionRates: $trendsData['completion_rates'],
                durationTrends: $trendsData['duration_trends'],
                averageResponseTime: $trendsData['summary']['average_response_time'],
                totalTurns: $trendsData['summary']['total_turns'],
                completedTurns: $trendsData['summary']['completed_turns'],
                skippedTurns: $trendsData['summary']['skipped_turns'],
                periodStart: $startDate,
                periodEnd: $endDate
            );
        });
    }

    /**
     * Calculate percentiles for turn durations
     */
    public function calculateDurationPercentiles(
        Group $group,
        array $percentiles = [50, 95, 99],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        return $this->percentileCalculator->calculateGroupPercentiles(
            $group,
            $percentiles,
            $startDate,
            $endDate
        );
    }

    /**
     * Get advanced group insights
     */
    public function getGroupInsights(Group $group): array
    {
        $cacheKey = "analytics:insights:group:{$group->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            $fairnessMetrics = $this->fairnessScorer->calculateGroupFairness($group);
            $durationStats = $this->percentileCalculator->getDetailedDurationStats($group);
            $peakTimes = $this->historicalAggregator->getPeakUsageTimes($group, 30);

            $insights = [];

            // Fairness insights
            if ($fairnessMetrics->fairnessScore < 0.5) {
                $insights[] = [
                    'type' => 'fairness_warning',
                    'title' => 'Uneven Turn Distribution',
                    'description' => 'Some members have significantly more or fewer turns than others.',
                    'severity' => 'medium',
                    'data' => [
                        'fairness_score' => $fairnessMetrics->fairnessScore,
                        'imbalanced_members' => count($fairnessMetrics->imbalanceMembers),
                    ],
                ];
            }

            // Duration insights
            if ($durationStats['count'] > 0 && $durationStats['percentiles'][95] > 3600) {
                $insights[] = [
                    'type' => 'duration_alert',
                    'title' => 'Long Turn Durations Detected',
                    'description' => '95% of turns exceed 1 hour, which may indicate stuck sessions.',
                    'severity' => 'high',
                    'data' => [
                        'p95_duration' => $durationStats['percentiles'][95],
                        'avg_duration' => $durationStats['mean'],
                    ],
                ];
            }

            // Activity insights
            $weeklyActivity = $this->historicalAggregator->getWeeklyActivity($group, 4);
            $recentWeeks = array_slice($weeklyActivity, -2);
            
            if (count($recentWeeks) === 2) {
                $change = $recentWeeks[1]['total_turns'] - $recentWeeks[0]['total_turns'];
                $percentChange = $recentWeeks[0]['total_turns'] > 0 
                    ? ($change / $recentWeeks[0]['total_turns']) * 100 
                    : 0;

                if ($percentChange < -50) {
                    $insights[] = [
                        'type' => 'activity_decline',
                        'title' => 'Significant Activity Decline',
                        'description' => 'Turn activity has decreased significantly in the past week.',
                        'severity' => 'medium',
                        'data' => [
                            'percent_change' => round($percentChange, 1),
                            'previous_week_turns' => $recentWeeks[0]['total_turns'],
                            'current_week_turns' => $recentWeeks[1]['total_turns'],
                        ],
                    ];
                } elseif ($percentChange > 100) {
                    $insights[] = [
                        'type' => 'activity_surge',
                        'title' => 'Activity Surge Detected',
                        'description' => 'Turn activity has increased significantly in the past week.',
                        'severity' => 'info',
                        'data' => [
                            'percent_change' => round($percentChange, 1),
                            'previous_week_turns' => $recentWeeks[0]['total_turns'],
                            'current_week_turns' => $recentWeeks[1]['total_turns'],
                        ],
                    ];
                }
            }

            // Peak usage insights
            $peakHour = $peakTimes['peak_hour'];
            if ($peakHour && $peakHour['turn_count'] > 10) {
                $insights[] = [
                    'type' => 'peak_usage',
                    'title' => 'Peak Usage Pattern Identified',
                    'description' => "Most activity occurs around {$peakHour['hour_label']}.",
                    'severity' => 'info',
                    'data' => [
                        'peak_hour' => $peakHour['hour'],
                        'peak_hour_turns' => $peakHour['turn_count'],
                    ],
                ];
            }

            return [
                'insights' => $insights,
                'insight_count' => count($insights),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get performance metrics for a group
     */
    public function getPerformanceMetrics(Group $group): array
    {
        $cacheKey = "analytics:performance:group:{$group->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            $durationStats = $this->percentileCalculator->getDetailedDurationStats($group);
            $fairnessMetrics = $this->fairnessScorer->calculateGroupFairness($group);
            $weeklyActivity = $this->historicalAggregator->getWeeklyActivity($group, 4);

            $recentActivity = count($weeklyActivity) > 0 ? array_slice($weeklyActivity, -4) : [];
            $avgWeeklyTurns = count($recentActivity) > 0 
                ? collect($recentActivity)->avg('total_turns') 
                : 0;

            $avgCompletionRate = count($recentActivity) > 0
                ? collect($recentActivity)->avg(function ($week) {
                    return $week['total_turns'] > 0 
                        ? ($week['completed_turns'] / $week['total_turns']) * 100 
                        : 0;
                })
                : 0;

            return [
                'efficiency' => [
                    'avg_turn_duration' => $durationStats['mean'] ?? 0,
                    'median_turn_duration' => $durationStats['median'] ?? 0,
                    'p95_turn_duration' => $durationStats['percentiles'][95] ?? 0,
                    'completion_rate' => round($avgCompletionRate, 2),
                ],
                'fairness' => [
                    'fairness_score' => $fairnessMetrics->fairnessScore,
                    'fairness_level' => $fairnessMetrics->getFairnessLevel(),
                    'distribution_balance' => $fairnessMetrics->isBalanced(),
                ],
                'engagement' => [
                    'avg_weekly_turns' => round($avgWeeklyTurns, 1),
                    'active_members_ratio' => $this->calculateActiveMembersRatio($group),
                    'consistency_score' => $this->calculateConsistencyScore($recentActivity),
                ],
                'calculated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Clear analytics cache for a group
     */
    public function clearCache(Group $group): void
    {
        // Clear all related caches
        $this->percentileCalculator->clearGroupCache($group);
        $this->fairnessScorer->clearCache($group);
        $this->historicalAggregator->clearCache($group);

        // Clear orchestrator-specific caches
        $patterns = [
            "analytics:group:{$group->id}:*",
            "analytics:insights:group:{$group->id}",
            "analytics:performance:group:{$group->id}",
        ];

        // For simplicity, clear all cache
        Cache::flush();
    }

    /**
     * Clear user analytics cache
     */
    public function clearUserCache(User $user): void
    {
        $this->percentileCalculator->clearUserCache($user);
        $this->historicalAggregator->clearUserCache($user);

        // Clear user-specific analytics caches
        Cache::flush(); // Simplified
    }

    /**
     * Calculate basic turn statistics for a group
     */
    private function calculateTurnStatistics(Group $group, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $query = $group->turns();

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $turns = $query->get();

        $completedTurns = $turns->where('status', 'completed');
        $totalDuration = $completedTurns
            ->filter(function ($turn) {
                return $turn->started_at && $turn->ended_at;
            })
            ->sum(function ($turn) {
                return $turn->started_at->diffInSeconds($turn->ended_at);
            });

        return [
            'total_turns' => $turns->count(),
            'active_turns' => $turns->where('status', 'active')->count(),
            'completed_turns' => $completedTurns->count(),
            'average_duration' => $completedTurns->count() > 0 
                ? round($totalDuration / $completedTurns->count(), 2) 
                : 0,
        ];
    }

    /**
     * Calculate active members ratio
     */
    private function calculateActiveMembersRatio(Group $group): float
    {
        $totalMembers = $group->activeMembers()->count();
        $activeMembersLastWeek = $group->turns()
            ->where('started_at', '>=', now()->subWeek())
            ->distinct('user_id')
            ->count();

        return $totalMembers > 0 ? round($activeMembersLastWeek / $totalMembers, 3) : 0;
    }

    /**
     * Calculate consistency score based on weekly activity variation
     */
    private function calculateConsistencyScore(array $weeklyActivity): float
    {
        if (count($weeklyActivity) < 2) {
            return 1.0; // Perfect consistency if not enough data
        }

        $turnCounts = collect($weeklyActivity)->pluck('total_turns');
        $mean = $turnCounts->avg();
        
        if ($mean === 0) {
            return 1.0;
        }

        $variance = $turnCounts->sum(function ($count) use ($mean) {
            return pow($count - $mean, 2);
        }) / count($weeklyActivity);

        $coefficientOfVariation = sqrt($variance) / $mean;

        // Convert CV to consistency score (lower CV = higher consistency)
        return max(0, 1 - $coefficientOfVariation);
    }

    /**
     * Generate cache key for comprehensive analytics
     */
    private function generateAnalyticsCacheKey(Group $group, ?Carbon $startDate, ?Carbon $endDate): string
    {
        $dateString = ($startDate ? $startDate->format('Y-m-d') : '') . '_' . 
                     ($endDate ? $endDate->format('Y-m-d') : '');
        
        return "analytics:group:{$group->id}:comprehensive:{$dateString}";
    }
}
