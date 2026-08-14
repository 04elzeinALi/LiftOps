<?php

namespace App\Console\Commands;

use App\Support\PastWorkCloser;
use Illuminate\Console\Command;

/**
 * Marks trips and shifts whose time has passed as completed.
 *
 * The app also does this lazily off list requests (see PastWorkCloser) so it
 * stays correct without a scheduler. This command is here so it can be put on
 * a cron once the backend is actually deployed somewhere:
 *
 *     * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
 */
class CloseOutPastWork extends Command
{
    protected $signature = 'work:close-past';

    protected $description = 'Mark trips and shifts whose scheduled time has passed as completed';

    public function handle(): int
    {
        $closed = PastWorkCloser::sweep();

        $this->info("Closed {$closed['trips']} trip(s) and {$closed['shifts']} shift(s).");

        return self::SUCCESS;
    }
}
