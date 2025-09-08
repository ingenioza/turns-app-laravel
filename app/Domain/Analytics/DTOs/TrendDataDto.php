<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTOs;

readonly class TrendDataDto
{
    public function __construct(
        public int $userId,
        public array $dailyActivity,
        public array $weeklyTrends,
        public array $completionRates,
        public array $durationTrends,
        public float $averageResponseTime,
        public int $totalTurns,
        public int $completedTurns,
        public int $skippedTurns,
        public \Carbon\Carbon $periodStart,
        public \Carbon\Carbon $periodEnd
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'daily_activity' => $this->dailyActivity,
            'weekly_trends' => $this->weeklyTrends,
            'completion_rates' => $this->completionRates,
            'duration_trends' => $this->durationTrends,
            'average_response_time' => round($this->averageResponseTime, 2),
            'total_turns' => $this->totalTurns,
            'completed_turns' => $this->completedTurns,
            'skipped_turns' => $this->skippedTurns,
            'completion_rate' => $this->totalTurns > 0 ? round($this->completedTurns / $this->totalTurns, 3) : 0,
            'period_start' => $this->periodStart->toISOString(),
            'period_end' => $this->periodEnd->toISOString(),
        ];
    }

    /**
     * Get trend direction for turns
     */
    public function getTurnsTrend(): string
    {
        if (count($this->weeklyTrends) < 2) {
            return 'stable';
        }

        $recent = array_slice($this->weeklyTrends, -2);
        $change = $recent[1]['turns'] - $recent[0]['turns'];

        return match (true) {
            $change > 2 => 'increasing',
            $change < -2 => 'decreasing',
            default => 'stable'
        };
    }
}
