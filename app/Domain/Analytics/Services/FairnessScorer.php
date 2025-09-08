<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\FairnessMetricsDto;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FairnessScorer
{
    private const CACHE_TTL = 1800; // 30 minutes cache
    private const IMBALANCE_THRESHOLD = 0.3; // 30% above/below average is considered imbalanced

    /**
     * Calculate comprehensive fairness metrics for a group
     */
    public function calculateGroupFairness(Group $group): FairnessMetricsDto
    {
        $cacheKey = "analytics:fairness:group:{$group->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            $memberTurnCounts = $this->getMemberTurnCounts($group);
            
            $fairnessScore = $this->calculateFairnessScore($memberTurnCounts);
            $distributionVariance = $this->calculateDistributionVariance($memberTurnCounts);
            $giniCoefficient = $this->calculateGiniCoefficient($memberTurnCounts);
            $memberDistribution = $this->buildMemberDistribution($group, $memberTurnCounts);
            $imbalanceMembers = $this->identifyImbalancedMembers($memberDistribution);

            return new FairnessMetricsDto(
                fairnessScore: $fairnessScore,
                distributionVariance: $distributionVariance,
                giniCoefficient: $giniCoefficient,
                memberDistribution: $memberDistribution,
                imbalanceMembers: $imbalanceMembers,
                totalMembers: $group->activeMembers()->count(),
                calculatedAt: now()
            );
        });
    }

    /**
     * Get turn counts for all active members in a group
     */
    private function getMemberTurnCounts(Group $group): Collection
    {
        return $group->activeMembers()
            ->withCount(['turns as total_turns'])
            ->withCount(['turns as completed_turns' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->withCount(['turns as skipped_turns' => function ($query) {
                $query->where('status', 'skipped');
            }])
            ->get()
            ->mapWithKeys(function ($member) {
                return [
                    $member->id => [
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'total_turns' => $member->total_turns,
                        'completed_turns' => $member->completed_turns,
                        'skipped_turns' => $member->skipped_turns,
                        'completion_rate' => $member->total_turns > 0 
                            ? round($member->completed_turns / $member->total_turns, 3) 
                            : 0,
                    ]
                ];
            });
    }

    /**
     * Calculate overall fairness score (0-1 scale)
     * Based on how evenly turns are distributed among members
     */
    private function calculateFairnessScore(Collection $memberCounts): float
    {
        if ($memberCounts->isEmpty()) {
            return 1.0; // Perfect fairness for empty group
        }

        $turnCounts = $memberCounts->pluck('total_turns');
        
        if ($turnCounts->sum() === 0) {
            return 1.0; // Perfect fairness when no turns exist
        }

        // Calculate coefficient of variation (CV)
        $mean = $turnCounts->avg();
        
        if ($mean === 0) {
            return 1.0;
        }

        $variance = $this->calculateVariance($turnCounts, $mean);
        $stdDev = sqrt($variance);
        $cv = $stdDev / $mean;

        // Convert CV to fairness score (lower CV = higher fairness)
        // Use exponential decay to map CV to 0-1 scale
        return max(0, exp(-2 * $cv));
    }

    /**
     * Calculate distribution variance
     */
    private function calculateDistributionVariance(Collection $memberCounts): float
    {
        $turnCounts = $memberCounts->pluck('total_turns');
        
        if ($turnCounts->isEmpty()) {
            return 0.0;
        }

        $mean = $turnCounts->avg();
        
        return $this->calculateVariance($turnCounts, $mean);
    }

    /**
     * Calculate Gini coefficient for turn distribution
     * 0 = perfect equality, 1 = maximum inequality
     */
    private function calculateGiniCoefficient(Collection $memberCounts): float
    {
        $turnCounts = $memberCounts->pluck('total_turns')->sort()->values();
        $n = $turnCounts->count();
        
        if ($n === 0 || $turnCounts->sum() === 0) {
            return 0.0; // Perfect equality
        }

        $sum = 0;
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $sum += abs($turnCounts[$i] - $turnCounts[$j]);
            }
        }

        $mean = $turnCounts->avg();
        
        return $sum / (2 * $n * $n * $mean);
    }

    /**
     * Build detailed member distribution array
     */
    private function buildMemberDistribution(Group $group, Collection $memberCounts): array
    {
        $totalTurns = $memberCounts->sum('total_turns');
        $memberCount = $memberCounts->count();
        $expectedTurnsPerMember = $memberCount > 0 ? $totalTurns / $memberCount : 0;

        return $memberCounts->map(function ($member) use ($expectedTurnsPerMember, $totalTurns) {
            $sharePercentage = $totalTurns > 0 
                ? round(($member['total_turns'] / $totalTurns) * 100, 2)
                : 0;
            
            $deviation = $expectedTurnsPerMember > 0 
                ? round((($member['total_turns'] - $expectedTurnsPerMember) / $expectedTurnsPerMember) * 100, 2)
                : 0;

            return [
                'user_id' => $member['user_id'],
                'name' => $member['name'],
                'total_turns' => $member['total_turns'],
                'completed_turns' => $member['completed_turns'],
                'skipped_turns' => $member['skipped_turns'],
                'completion_rate' => $member['completion_rate'],
                'share_percentage' => $sharePercentage,
                'deviation_percentage' => $deviation,
                'expected_turns' => round($expectedTurnsPerMember, 1),
            ];
        })->values()->toArray();
    }

    /**
     * Identify members with disproportionate turn counts
     */
    private function identifyImbalancedMembers(array $memberDistribution): array
    {
        return collect($memberDistribution)
            ->filter(function ($member) {
                return abs($member['deviation_percentage']) > (self::IMBALANCE_THRESHOLD * 100);
            })
            ->map(function ($member) {
                $isOvershare = $member['deviation_percentage'] > 0;
                
                return [
                    'user_id' => $member['user_id'],
                    'name' => $member['name'],
                    'total_turns' => $member['total_turns'],
                    'expected_turns' => $member['expected_turns'],
                    'deviation_percentage' => $member['deviation_percentage'],
                    'type' => $isOvershare ? 'overshare' : 'undershare',
                    'severity' => $this->calculateImbalanceSeverity(abs($member['deviation_percentage'])),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate imbalance severity level
     */
    private function calculateImbalanceSeverity(float $deviationPercentage): string
    {
        return match (true) {
            $deviationPercentage >= 80 => 'severe',
            $deviationPercentage >= 50 => 'high',
            $deviationPercentage >= 30 => 'moderate',
            default => 'low'
        };
    }

    /**
     * Calculate variance for a collection of values
     */
    private function calculateVariance(Collection $values, float $mean): float
    {
        if ($values->count() <= 1) {
            return 0.0;
        }

        $sumSquaredDeviations = $values->sum(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        });

        return $sumSquaredDeviations / $values->count();
    }

    /**
     * Get fairness trend over time (weekly snapshots)
     */
    public function getFairnessTrend(Group $group, int $weeks = 8): array
    {
        $cacheKey = "analytics:fairness_trend:group:{$group->id}:weeks:{$weeks}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $weeks) {
            $weeklySnapshots = [];
            
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = now()->subWeeks($i + 1)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                
                $memberCounts = $this->getMemberTurnCountsForPeriod($group, $weekStart, $weekEnd);
                $fairnessScore = $this->calculateFairnessScore($memberCounts);
                
                $weeklySnapshots[] = [
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'fairness_score' => round($fairnessScore, 3),
                    'total_turns' => $memberCounts->sum('total_turns'),
                    'active_members' => $memberCounts->count(),
                ];
            }

            return array_reverse($weeklySnapshots); // Return chronological order
        });
    }

    /**
     * Get member turn counts for a specific time period
     */
    private function getMemberTurnCountsForPeriod(Group $group, Carbon $startDate, Carbon $endDate): Collection
    {
        return $group->activeMembers()
            ->withCount(['turns as total_turns' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('started_at', [$startDate, $endDate]);
            }])
            ->withCount(['turns as completed_turns' => function ($query) use ($startDate, $endDate) {
                $query->where('status', 'completed')
                      ->whereBetween('started_at', [$startDate, $endDate]);
            }])
            ->withCount(['turns as skipped_turns' => function ($query) use ($startDate, $endDate) {
                $query->where('status', 'skipped')
                      ->whereBetween('started_at', [$startDate, $endDate]);
            }])
            ->get()
            ->mapWithKeys(function ($member) {
                return [
                    $member->id => [
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'total_turns' => $member->total_turns,
                        'completed_turns' => $member->completed_turns,
                        'skipped_turns' => $member->skipped_turns,
                        'completion_rate' => $member->total_turns > 0 
                            ? round($member->completed_turns / $member->total_turns, 3) 
                            : 0,
                    ]
                ];
            });
    }

    /**
     * Clear fairness cache for a group
     */
    public function clearCache(Group $group): void
    {
        Cache::forget("analytics:fairness:group:{$group->id}");
        
        // Clear trend cache as well
        for ($weeks = 1; $weeks <= 16; $weeks++) {
            Cache::forget("analytics:fairness_trend:group:{$group->id}:weeks:{$weeks}");
        }
    }
}
