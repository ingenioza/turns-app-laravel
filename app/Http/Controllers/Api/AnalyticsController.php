<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Analytics\Contracts\AnalyticsServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsServiceInterface $analyticsService
    ) {}

    /**
     * Get comprehensive analytics for a group
     * 
     * @param Group $group
     * @param Request $request
     * @return JsonResponse
     */
    public function getGroupAnalytics(Group $group, Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', $group);

        $validated = $this->validateDateRange($request);

        $analytics = $this->analyticsService->getGroupAnalytics(
            $group,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json([
            'data' => $analytics->toArray(),
            'meta' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'filters_applied' => [
                    'start_date' => $validated['start_date']?->toDateString(),
                    'end_date' => $validated['end_date']?->toDateString(),
                ],
                'cache_info' => [
                    'cacheable' => true,
                    'ttl_minutes' => 30,
                ],
            ],
        ]);
    }

    /**
     * Get fairness metrics for a group
     * 
     * @param Group $group
     * @return JsonResponse
     */
    public function getGroupFairness(Group $group): JsonResponse
    {
        $this->authorize('viewAnalytics', $group);

        $fairnessMetrics = $this->analyticsService->getGroupFairness($group);

        return response()->json([
            'data' => $fairnessMetrics->toArray(),
            'meta' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'interpretation' => [
                    'fairness_level' => $fairnessMetrics->getFairnessLevel(),
                    'is_balanced' => $fairnessMetrics->isBalanced(),
                    'needs_attention' => !$fairnessMetrics->isBalanced(),
                ],
            ],
        ]);
    }

    /**
     * Get user trend data
     * 
     * @param User $user
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserTrends(User $user, Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', $user);

        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:7', 'max:365'],
        ]);

        $days = $validated['days'] ?? 30;

        $trends = $this->analyticsService->getUserTrends($user, $days);

        return response()->json([
            'data' => $trends->toArray(),
            'meta' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'analysis_period' => [
                    'days' => $days,
                    'start_date' => $trends->periodStart->toDateString(),
                    'end_date' => $trends->periodEnd->toDateString(),
                ],
                'trend_direction' => $trends->getTurnsTrend(),
            ],
        ]);
    }

    /**
     * Get group insights and recommendations
     * 
     * @param Group $group
     * @return JsonResponse
     */
    public function getGroupInsights(Group $group): JsonResponse
    {
        $this->authorize('viewAnalytics', $group);

        $insights = $this->analyticsService->getGroupInsights($group);

        return response()->json([
            'data' => $insights,
            'meta' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'has_insights' => $insights['insight_count'] > 0,
            ],
        ]);
    }

    /**
     * Get performance metrics for a group
     * 
     * @param Group $group
     * @return JsonResponse
     */
    public function getGroupPerformance(Group $group): JsonResponse
    {
        $this->authorize('viewAnalytics', $group);

        $performance = $this->analyticsService->getPerformanceMetrics($group);

        return response()->json([
            'data' => $performance,
            'meta' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
            ],
        ]);
    }

    /**
     * Get duration percentiles for a group
     * 
     * @param Group $group
     * @param Request $request
     * @return JsonResponse
     */
    public function getGroupPercentiles(Group $group, Request $request): JsonResponse
    {
        $this->authorize('viewAnalytics', $group);

        $validated = $request->validate([
            'percentiles' => ['sometimes', 'array'],
            'percentiles.*' => ['integer', 'min:1', 'max:99'],
            'start_date' => ['sometimes', 'date', 'before_or_equal:end_date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ]);

        $percentiles = $validated['percentiles'] ?? [50, 75, 90, 95, 99];
        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;

        $durationPercentiles = $this->analyticsService->calculateDurationPercentiles(
            $group,
            $percentiles,
            $startDate,
            $endDate
        );

        return response()->json([
            'data' => [
                'percentiles' => $durationPercentiles,
                'requested_percentiles' => $percentiles,
                'date_range' => [
                    'start_date' => $startDate?->toDateString(),
                    'end_date' => $endDate?->toDateString(),
                ],
            ],
            'meta' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'units' => 'seconds',
            ],
        ]);
    }

    /**
     * Clear analytics cache for a group
     * 
     * @param Group $group
     * @return JsonResponse
     */
    public function clearGroupCache(Group $group): JsonResponse
    {
        $this->authorize('manage', $group);

        $this->analyticsService->clearCache($group);

        return response()->json([
            'message' => 'Analytics cache cleared successfully',
            'group_id' => $group->id,
            'cleared_at' => now()->toISOString(),
        ], Response::HTTP_OK);
    }

    /**
     * Clear analytics cache for a user
     * 
     * @param User $user
     * @return JsonResponse
     */
    public function clearUserCache(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $this->analyticsService->clearUserCache($user);

        return response()->json([
            'message' => 'User analytics cache cleared successfully',
            'user_id' => $user->id,
            'cleared_at' => now()->toISOString(),
        ], Response::HTTP_OK);
    }

    /**
     * Get analytics summary for multiple groups (for dashboard)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDashboardSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_ids' => ['sometimes', 'array', 'max:10'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
        ]);

        $user = $request->user();
        
        // Get user's groups if not specified
        $groupIds = $validated['group_ids'] ?? $user->groups()
            ->wherePivot('status', 'active')
            ->pluck('groups.id')
            ->take(10)
            ->toArray();

        $summaries = [];

        foreach ($groupIds as $groupId) {
            $group = Group::find($groupId);
            
            if (!$group || !$request->user()->can('viewAnalytics', $group)) {
                continue;
            }

            $fairness = $this->analyticsService->getGroupFairness($group);
            $performance = $this->analyticsService->getPerformanceMetrics($group);

            $summaries[] = [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'fairness_score' => $fairness->fairnessScore,
                'fairness_level' => $fairness->getFairnessLevel(),
                'avg_weekly_turns' => $performance['engagement']['avg_weekly_turns'],
                'completion_rate' => $performance['efficiency']['completion_rate'],
                'active_members_ratio' => $performance['engagement']['active_members_ratio'],
            ];
        }

        return response()->json([
            'data' => $summaries,
            'meta' => [
                'user_id' => $user->id,
                'groups_count' => count($summaries),
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Validate date range parameters
     * 
     * @param Request $request
     * @return array
     * @throws ValidationException
     */
    private function validateDateRange(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date', 'before_or_equal:end_date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ]);

        $result = [];

        if (isset($validated['start_date'])) {
            $result['start_date'] = Carbon::parse($validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $result['end_date'] = Carbon::parse($validated['end_date']);
        }

        // Validate date range is not too large (max 1 year)
        if (isset($result['start_date']) && isset($result['end_date'])) {
            $daysDiff = $result['start_date']->diffInDays($result['end_date']);
            
            if ($daysDiff > 365) {
                throw ValidationException::withMessages([
                    'date_range' => 'Date range cannot exceed 365 days.'
                ]);
            }
        }

        return $result;
    }
}
