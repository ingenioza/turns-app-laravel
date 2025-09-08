<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Models\Group;
use App\Models\Turn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PercentileCalculator
{
    private const CACHE_TTL = 3600; // 1 hour cache

    /**
     * Calculate duration percentiles for a group
     */
    public function calculateGroupPercentiles(
        Group $group,
        array $percentiles = [50, 95, 99],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $cacheKey = $this->generateCacheKey('group', $group->id, $percentiles, $startDate, $endDate);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $percentiles, $startDate, $endDate) {
            $durations = $this->getGroupDurations($group, $startDate, $endDate);

            return $this->calculatePercentilesFromDurations($durations, $percentiles);
        });
    }

    /**
     * Calculate duration percentiles for a user across all groups
     */
    public function calculateUserPercentiles(
        User $user,
        array $percentiles = [50, 95, 99],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $cacheKey = $this->generateCacheKey('user', $user->id, $percentiles, $startDate, $endDate);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $percentiles, $startDate, $endDate) {
            $durations = $this->getUserDurations($user, $startDate, $endDate);

            return $this->calculatePercentilesFromDurations($durations, $percentiles);
        });
    }

    /**
     * Get turn durations for a group
     */
    private function getGroupDurations(Group $group, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $query = $group->turns()
            ->whereIn('status', ['completed', 'skipped'])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at');

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        return $query->get()
            ->map(function (Turn $turn) {
                if (!$turn->started_at || !$turn->ended_at) {
                    return null;
                }

                return $turn->started_at->diffInSeconds($turn->ended_at);
            })
            ->filter()
            ->values();
    }

    /**
     * Get turn durations for a user
     */
    private function getUserDurations(User $user, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $query = $user->turns()
            ->whereIn('status', ['completed', 'skipped'])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at');

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        return $query->get()
            ->map(function (Turn $turn) {
                if (!$turn->started_at || !$turn->ended_at) {
                    return null;
                }

                return $turn->started_at->diffInSeconds($turn->ended_at);
            })
            ->filter()
            ->values();
    }

    /**
     * Calculate percentiles from duration data
     */
    private function calculatePercentilesFromDurations(Collection $durations, array $percentiles): array
    {
        if ($durations->isEmpty()) {
            return array_fill_keys($percentiles, 0);
        }

        $sorted = $durations->sort()->values();
        $count = $sorted->count();
        $result = [];

        foreach ($percentiles as $percentile) {
            $index = ($percentile / 100) * ($count - 1);
            
            if ($index === floor($index)) {
                // Exact percentile
                $result[$percentile] = $sorted[(int) $index];
            } else {
                // Interpolate between two values
                $lower = $sorted[(int) floor($index)];
                $upper = $sorted[(int) ceil($index)];
                $fraction = $index - floor($index);
                
                $result[$percentile] = $lower + ($fraction * ($upper - $lower));
            }
        }

        return $result;
    }

    /**
     * Get duration statistics with additional metrics
     */
    public function getDetailedDurationStats(
        Group $group,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $durations = $this->getGroupDurations($group, $startDate, $endDate);

        if ($durations->isEmpty()) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'mean' => 0,
                'median' => 0,
                'std_dev' => 0,
                'percentiles' => [],
            ];
        }

        $sorted = $durations->sort()->values();
        $count = $sorted->count();
        $mean = $durations->avg();

        return [
            'count' => $count,
            'min' => $sorted->first(),
            'max' => $sorted->last(),
            'mean' => round($mean, 2),
            'median' => $this->calculatePercentilesFromDurations($durations, [50])[50],
            'std_dev' => round($this->calculateStandardDeviation($durations, $mean), 2),
            'percentiles' => $this->calculatePercentilesFromDurations($durations, [25, 50, 75, 90, 95, 99]),
        ];
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation(Collection $values, float $mean): float
    {
        if ($values->count() <= 1) {
            return 0.0;
        }

        $sumSquaredDeviations = $values->sum(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        });

        return sqrt($sumSquaredDeviations / ($values->count() - 1));
    }

    /**
     * Generate cache key for percentile calculations
     */
    private function generateCacheKey(
        string $type,
        int $id,
        array $percentiles,
        ?Carbon $startDate,
        ?Carbon $endDate
    ): string {
        $dateString = ($startDate ? $startDate->format('Y-m-d') : '') . '_' . 
                     ($endDate ? $endDate->format('Y-m-d') : '');
        
        $percentilesString = implode(',', $percentiles);
        
        return "analytics:percentiles:{$type}:{$id}:{$percentilesString}:{$dateString}";
    }

    /**
     * Clear cache for group percentiles
     */
    public function clearGroupCache(Group $group): void
    {
        $pattern = "analytics:percentiles:group:{$group->id}:*";
        
        // Note: In production, consider using Redis SCAN for pattern deletion
        Cache::flush(); // For simplicity, flush all cache
    }

    /**
     * Clear cache for user percentiles
     */
    public function clearUserCache(User $user): void
    {
        $pattern = "analytics:percentiles:user:{$user->id}:*";
        
        // Note: In production, consider using Redis SCAN for pattern deletion
        Cache::flush(); // For simplicity, flush all cache
    }
}
