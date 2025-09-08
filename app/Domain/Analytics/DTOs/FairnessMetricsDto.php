<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTOs;

readonly class FairnessMetricsDto
{
    public function __construct(
        public float $fairnessScore,
        public float $distributionVariance,
        public float $giniCoefficient,
        public array $memberDistribution,
        public array $imbalanceMembers,
        public int $totalMembers,
        public \Carbon\Carbon $calculatedAt
    ) {}

    public function toArray(): array
    {
        return [
            'fairness_score' => round($this->fairnessScore, 3),
            'distribution_variance' => round($this->distributionVariance, 3),
            'gini_coefficient' => round($this->giniCoefficient, 3),
            'member_distribution' => $this->memberDistribution,
            'imbalance_members' => $this->imbalanceMembers,
            'total_members' => $this->totalMembers,
            'calculated_at' => $this->calculatedAt->toISOString(),
        ];
    }

    /**
     * Check if the group has good fairness (score >= 0.7)
     */
    public function isBalanced(): bool
    {
        return $this->fairnessScore >= 0.7;
    }

    /**
     * Get fairness level description
     */
    public function getFairnessLevel(): string
    {
        return match (true) {
            $this->fairnessScore >= 0.9 => 'excellent',
            $this->fairnessScore >= 0.7 => 'good',
            $this->fairnessScore >= 0.5 => 'fair',
            $this->fairnessScore >= 0.3 => 'poor',
            default => 'very_poor'
        };
    }
}
