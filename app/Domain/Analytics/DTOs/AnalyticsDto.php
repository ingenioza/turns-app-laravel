<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTOs;

readonly class AnalyticsDto
{
    public function __construct(
        public int $groupId,
        public array $durationPercentiles,
        public FairnessMetricsDto $fairnessMetrics,
        public array $weeklyActivity,
        public array $membershipTrends,
        public array $peakUsageTimes,
        public float $averageSessionDuration,
        public int $totalTurns,
        public int $activeTurns,
        public \Carbon\Carbon $generatedAt
    ) {}

    public function toArray(): array
    {
        return [
            'group_id' => $this->groupId,
            'duration_percentiles' => $this->durationPercentiles,
            'fairness_metrics' => $this->fairnessMetrics->toArray(),
            'weekly_activity' => $this->weeklyActivity,
            'membership_trends' => $this->membershipTrends,
            'peak_usage_times' => $this->peakUsageTimes,
            'average_session_duration' => $this->averageSessionDuration,
            'total_turns' => $this->totalTurns,
            'active_turns' => $this->activeTurns,
            'generated_at' => $this->generatedAt->toISOString(),
        ];
    }
}
