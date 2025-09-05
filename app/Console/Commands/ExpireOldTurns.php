<?php

namespace App\Console\Commands;

use App\Application\Services\TurnService;
use Illuminate\Console\Command;

class ExpireOldTurns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'turns:expire-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire turns that have been active for more than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(TurnService $turnService): int
    {
        $this->info('Checking for expired turns...');
        
        $expiredCount = $turnService->expireOldTurns();
        
        if ($expiredCount > 0) {
            $this->info("Expired {$expiredCount} old turns.");
        } else {
            $this->info('No expired turns found.');
        }
        
        return 0;
    }
}
