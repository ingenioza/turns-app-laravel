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
use Illuminate\Support\Facades\DB;

class HistoricalAggregator
{
    private const CACHE_TTL = 3600; // 1 hour cache

    /**
     * Get weekly activity summary for a group
     */
    public function getWeeklyActivity(Group $group, int $weeks = 12): array
    {
        $cacheKey = "analytics:weekly_activity:group:{$group->id}:weeks:{$weeks}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $weeks) {
            $weeklyData = [];
            
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                
                $turns = $group->turns()
                    ->whereBetween('started_at', [$weekStart, $weekEnd])
                    ->get();

                $weeklyData[] = [
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'total_turns' => $turns->count(),
                    'completed_turns' => $turns->where('status', 'completed')->count(),
                    'skipped_turns' => $turns->where('status', 'skipped')->count(),
                    'active_turns' => $turns->where('status', 'active')->count(),
                    'average_duration' => $this->calculateAverageDuration($turns),
                    'unique_participants' => $turns->pluck('user_id')->unique()->count(),
                ];
            }

            return array_reverse($weeklyData); // Return chronological order
        });
    }

    /**
     * Get monthly activity summary for a group
     */
    public function getMonthlyActivity(Group $group, int $months = 6): array
    {
        $cacheKey = "analytics:monthly_activity:group:{$group->id}:months:{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $months) {
            $monthlyData = [];
            
            for ($i = 0; $i < $months; $i++) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                $turns = $group->turns()
                    ->whereBetween('started_at', [$monthStart, $monthEnd])
                    ->get();

                $monthlyData[] = [
                    'month' => $monthStart->format('Y-m'),
                    'month_name' => $monthStart->format('F Y'),
                    'total_turns' => $turns->count(),
                    'completed_turns' => $turns->where('status', 'completed')->count(),
                    'skipped_turns' => $turns->where('status', 'skipped')->count(),
                    'active_turns' => $turns->where('status', 'active')->count(),
                    'average_duration' => $this->calculateAverageDuration($turns),
                    'unique_participants' => $turns->pluck('user_id')->unique()->count(),
                    'total_duration' => $this->calculateTotalDuration($turns),
                ];
            }

            return array_reverse($monthlyData); // Return chronological order
        });
    }

    /**
     * Identify peak usage times for a group
     */
    public function getPeakUsageTimes(Group $group, int $days = 30): array
    {
        $cacheKey = "analytics:peak_usage:group:{$group->id}:days:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $days) {
            $startDate = now()->subDays($days);
            
            $hourlyData = $group->turns()
                ->where('started_at', '>=', $startDate)
                ->whereNotNull('started_at')
                ->get()
                ->groupBy(function ($turn) {
                    return $turn->started_at->format('H');
                })
                ->map(function ($turns, $hour) {
                    return [
                        'hour' => (int) $hour,
                        'hour_label' => sprintf('%02d:00', $hour),
                        'turn_count' => $turns->count(),
                        'average_duration' => $this->calculateAverageDuration($turns),
                    ];
                })
                ->sortBy('hour')
                ->values()
                ->toArray();

            $dayOfWeekData = $group->turns()
                ->where('started_at', '>=', $startDate)
                ->whereNotNull('started_at')
                ->get()
                ->groupBy(function ($turn) {
                    return $turn->started_at->format('w'); // 0=Sunday, 6=Saturday
                })
                ->map(function ($turns, $dayOfWeek) {
                    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    
                    return [
                        'day_of_week' => (int) $dayOfWeek,
                        'day_name' => $dayNames[$dayOfWeek],
                        'turn_count' => $turns->count(),
                        'average_duration' => $this->calculateAverageDuration($turns),
                    ];
                })
                ->sortBy('day_of_week')
                ->values()
                ->toArray();

            return [
                'hourly_distribution' => $hourlyData,
                'daily_distribution' => $dayOfWeekData,
                'peak_hour' => collect($hourlyData)->sortByDesc('turn_count')->first(),
                'peak_day' => collect($dayOfWeekData)->sortByDesc('turn_count')->first(),
                'analysis_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => now()->toDateString(),
                    'total_days' => $days,
                ],
            ];
        });
    }

    /**
     * Get user activity trends
     */
    public function getUserTrends(User $user, int $days = 30): array
    {
        $cacheKey = "analytics:user_trends:user:{$user->id}:days:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $days) {
            $startDate = now()->subDays($days);
            $endDate = now();

            $turns = $user->turns()
                ->whereBetween('started_at', [$startDate, $endDate])
                ->orderBy('started_at')
                ->get();

            $dailyActivity = $this->groupTurnsByDay($turns, $startDate, $endDate);
            $weeklyTrends = $this->groupTurnsByWeek($turns, $startDate, $endDate);
            $completionRates = $this->calculateCompletionRates($turns);
            $durationTrends = $this->calculateDurationTrends($turns);

            return [
                'daily_activity' => $dailyActivity,
                'weekly_trends' => $weeklyTrends,
                'completion_rates' => $completionRates,
                'duration_trends' => $durationTrends,
                'summary' => [
                    'total_turns' => $turns->count(),
                    'completed_turns' => $turns->where('status', 'completed')->count(),
                    'skipped_turns' => $turns->where('status', 'skipped')->count(),
                    'average_response_time' => $this->calculateAverageResponseTime($turns),
                    'period_start' => $startDate->toDateString(),
                    'period_end' => $endDate->toDateString(),
                ],
            ];
        });
    }

    /**
     * Get membership trends for a group
     */
    public function getMembershipTrends(Group $group, int $weeks = 12): array
    {
        $cacheKey = "analytics:membership_trends:group:{$group->id}:weeks:{$weeks}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $weeks) {
            $trendsData = [];
            
            for ($i = 0; $i < $weeks; $i++) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                
                // Count active members for this week (those who had turns)
                $activeMembersCount = $group->turns()
                    ->whereBetween('started_at', [$weekStart, $weekEnd])
                    ->distinct('user_id')
                    ->count();

                // Total enrolled members at week end
                $totalMembers = $group->members()
                    ->wherePivot('joined_at', '<=', $weekEnd)
                    ->wherePivot('status', 'active')
                    ->count();

                $trendsData[] = [
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'total_members' => $totalMembers,
                    'active_members' => $activeMembersCount,
                    'engagement_rate' => $totalMembers > 0 
                        ? round(($activeMembersCount / $totalMembers) * 100, 2) 
                        : 0,
                ];
            }

            return array_reverse($trendsData); // Return chronological order
        });
    }

    /**
     * Calculate average duration for a collection of turns
     */
    private function calculateAverageDuration(Collection $turns): float
    {
        $durations = $turns
            ->filter(function ($turn) {
                return $turn->started_at && $turn->ended_at;
            })
            ->map(function ($turn) {
                return $turn->started_at->diffInSeconds($turn->ended_at);
            });

        return $durations->isEmpty() ? 0 : round($durations->avg(), 2);
    }

    /**
     * Calculate total duration for a collection of turns
     */
    private function calculateTotalDuration(Collection $turns): float
    {
        return $turns
            ->filter(function ($turn) {
                return $turn->started_at && $turn->ended_at;
            })
            ->sum(function ($turn) {
                return $turn->started_at->diffInSeconds($turn->ended_at);
            });
    }

    /**
     * Group turns by day
     */
    private function groupTurnsByDay(Collection $turns, Carbon $startDate, Carbon $endDate): array
    {
        $dailyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayTurns = $turns->filter(function ($turn) use ($currentDate) {
                return $turn->started_at && $turn->started_at->isSameDay($currentDate);
            });

            $dailyData[] = [
                'date' => $currentDate->toDateString(),
                'turns' => $dayTurns->count(),
                'completed' => $dayTurns->where('status', 'completed')->count(),
                'skipped' => $dayTurns->where('status', 'skipped')->count(),
                'average_duration' => $this->calculateAverageDuration($dayTurns),
            ];

            $currentDate->addDay();
        }

        return $dailyData;
    }

    /**
     * Group turns by week
     */
    private function groupTurnsByWeek(Collection $turns, Carbon $startDate, Carbon $endDate): array
    {
        $weeklyData = [];
        $currentWeek = $startDate->copy()->startOfWeek();

        while ($currentWeek->lte($endDate)) {
            $weekEnd = $currentWeek->copy()->endOfWeek();
            
            $weekTurns = $turns->filter(function ($turn) use ($currentWeek, $weekEnd) {
                return $turn->started_at && 
                       $turn->started_at->between($currentWeek, $weekEnd);
            });

            $weeklyData[] = [
                'week_start' => $currentWeek->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'turns' => $weekTurns->count(),
                'completed' => $weekTurns->where('status', 'completed')->count(),
                'skipped' => $weekTurns->where('status', 'skipped')->count(),
                'average_duration' => $this->calculateAverageDuration($weekTurns),
            ];

            $currentWeek->addWeek();
        }

        return $weeklyData;
    }

    /**
     * Calculate completion rates over time
     */
    private function calculateCompletionRates(Collection $turns): array
    {
        $weeklyRates = [];
        $turnsByWeek = $turns->groupBy(function ($turn) {
            return $turn->started_at ? $turn->started_at->format('Y-W') : null;
        });

        foreach ($turnsByWeek as $week => $weekTurns) {
            if (!$week) continue;

            $completed = $weekTurns->where('status', 'completed')->count();
            $total = $weekTurns->whereIn('status', ['completed', 'skipped'])->count();

            $weeklyRates[] = [
                'week' => $week,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'total_turns' => $total,
                'completed_turns' => $completed,
            ];
        }

        return $weeklyRates;
    }

    /**
     * Calculate duration trends over time
     */
    private function calculateDurationTrends(Collection $turns): array
    {
        $durationTrends = [];
        $turnsByWeek = $turns->groupBy(function ($turn) {
            return $turn->started_at ? $turn->started_at->format('Y-W') : null;
        });

        foreach ($turnsByWeek as $week => $weekTurns) {
            if (!$week) continue;

            $durationTrends[] = [
                'week' => $week,
                'average_duration' => $this->calculateAverageDuration($weekTurns),
                'total_duration' => $this->calculateTotalDuration($weekTurns),
                'turn_count' => $weekTurns->count(),
            ];
        }

        return $durationTrends;
    }

    /**
     * Calculate average response time (time from turn start to completion)
     */
    private function calculateAverageResponseTime(Collection $turns): float
    {
        $responseTimes = $turns
            ->where('status', 'completed')
            ->filter(function ($turn) {
                return $turn->started_at && $turn->ended_at;
            })
            ->map(function ($turn) {
                return $turn->started_at->diffInMinutes($turn->ended_at);
            });

        return $responseTimes->isEmpty() ? 0 : round($responseTimes->avg(), 2);
    }

    /**
     * Clear historical data cache
     */
    public function clearCache(Group $group): void
    {
        // Clear group-specific caches
        $patterns = [
            "analytics:weekly_activity:group:{$group->id}:*",
            "analytics:monthly_activity:group:{$group->id}:*",
            "analytics:peak_usage:group:{$group->id}:*",
            "analytics:membership_trends:group:{$group->id}:*",
        ];

        // For simplicity, clear all cache
        // In production, implement pattern-based cache clearing
        Cache::flush();
    }

    /**
     * Clear user trends cache
     */
    public function clearUserCache(User $user): void
    {
        // Clear user-specific caches
        Cache::flush(); // Simplified for now
    }
}
