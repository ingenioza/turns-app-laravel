<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

use App\Domain\Analytics\DTOs\AnalyticsDto;
use App\Domain\Analytics\DTOs\FairnessMetricsDto;
use App\Domain\Analytics\DTOs\TrendDataDto;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;

interface AnalyticsServiceInterface
{
    /**
     * Get comprehensive analytics for a group
     */
    public function getGroupAnalytics(
        Group $group,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): AnalyticsDto;

    /**
     * Get fairness metrics for a group
     */
    public function getGroupFairness(Group $group): FairnessMetricsDto;

    /**
     * Get trend data for a user
     */
    public function getUserTrends(
        User $user,
        int $days = 30
    ): TrendDataDto;

    /**
     * Calculate percentiles for turn durations
     */
    public function calculateDurationPercentiles(
        Group $group,
        array $percentiles = [50, 95, 99],
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array;

    /**
     * Clear analytics cache for a group
     */
    public function clearCache(Group $group): void;
}
