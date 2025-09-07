<?php

namespace App\Jobs;

use App\Application\Services\TurnService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireOldTurnsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(TurnService $turnService): void
    {
        try {
            $expiredCount = $turnService->expireOldTurns();

            if ($expiredCount > 0) {
                Log::info("Expired {$expiredCount} old turns", [
                    'expired_count' => $expiredCount,
                    'job' => self::class,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to expire old turns', [
                'error' => $e->getMessage(),
                'job' => self::class,
            ]);

            throw $e;
        }
    }
}
