<?php

namespace App\Console\Commands;

use App\Mail\SavedSearchMatchesMail;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Support\PropertySearchFilters;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Send email alerts for saved marketplace searches.
 *
 * Runs hourly from routes/console.php. Every saved search keeps its own
 * `last_run_at` window, so daily/weekly searches are throttled independently
 * and an "instant" search is checked on each run. The command is safe to run
 * repeatedly: properties published before the previous run are never emailed
 * twice.
 */
class SendSavedSearchAlerts extends Command
{
    protected $signature = 'saved-searches:send-alerts {--dry-run : Report what would be sent without emailing or updating windows}';

    protected $description = 'Email newly published property matches for saved searches';

    public function handle(): int
    {
        $now = Carbon::now();
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $checked = 0;
        $failed = 0;

        SavedSearch::query()
            ->with('user')
            ->where('alerts_enabled', true)
            ->orderBy('id')
            ->chunkById(100, function ($searches) use ($now, $dryRun, &$sent, &$checked, &$failed) {
                foreach ($searches as $search) {
                    $checked++;

                    if (!$this->isDue($search, $now)) {
                        continue;
                    }

                    $cutoff = $search->last_run_at ?? $search->created_at ?? $now->copy()->subDay();

                    /** @var \Illuminate\Database\Eloquent\Collection<int, Property> $matches */
                    $matches = PropertySearchFilters::apply(
                        Property::query()->publiclyVisible()->where('listing_purpose', 'sale'),
                        $search->filters ?? []
                    )
                        ->where(function ($q) use ($cutoff) {
                            $q->where('created_at', '>', $cutoff)
                                ->orWhere('published_at', '>', $cutoff);
                        })
                        ->orderByDesc('created_at')
                        ->orderBy('id')
                        ->limit(20)
                        ->get();

                    if ($matches->isEmpty()) {
                        if (!$dryRun) {
                            $search->forceFill(['last_run_at' => $now])->save();
                        }
                        continue;
                    }

                    $email = $search->user?->email;
                    if (!$email) {
                        // User row vanished between the FK cascade and this run.
                        if (!$dryRun) {
                            $search->forceFill(['last_run_at' => $now])->save();
                        }
                        continue;
                    }

                    try {
                        if (!$dryRun) {
                            Mail::to($email)->send(new SavedSearchMatchesMail($search, $matches));
                            $search->forceFill([
                                'last_run_at' => $now,
                                'last_notified_at' => $now,
                            ])->save();
                        }
                        $sent++;
                    } catch (\Throwable $e) {
                        // One bad mailbox must not stop every other alert.
                        $failed++;
                        report($e);
                        $this->error("Alert failed for saved search {$search->id}: {$e->getMessage()}");
                    }
                }
            }, 'id');

        $verb = $dryRun ? 'Would send' : 'Sent';
        $this->info("{$verb} {$sent} saved-search alert(s); checked {$checked}; failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isDue(SavedSearch $search, Carbon $now): bool
    {
        if ($search->frequency === 'instant') {
            return true;
        }

        $minutes = $search->frequency === 'weekly' ? 7 * 24 * 60 : 24 * 60;

        return $search->last_run_at === null
            || $search->last_run_at->lte($now->copy()->subMinutes($minutes));
    }
}
