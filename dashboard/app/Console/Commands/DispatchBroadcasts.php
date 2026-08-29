<?php

namespace App\Console\Commands;

use App\Jobs\SendBroadcastMessage;
use App\Models\Bulk;
use App\Models\Campaigns;
use Illuminate\Console\Command;

class DispatchBroadcasts extends Command
{
    protected $signature = 'wa:broadcast-dispatch';
    protected $description = 'Queue eligible opted-in broadcast recipients with controlled delays.';

    public function handle(): int
    {
        $campaigns = Campaigns::whereIn('status', ['waiting', 'processing'])
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($campaigns as $campaign) {
            if (!$this->insideSendingWindow($campaign)) continue;

            if ($campaign->processed_count >= 10 && $campaign->processed_count > 0) {
                $rate = ($campaign->error_count / $campaign->processed_count) * 100;
                if ($rate >= $campaign->stop_error_rate) {
                    $campaign->update(['status' => 'paused', 'stopped_reason' => 'Paused automatically because the error rate reached '.round($rate, 1).'%.']);
                    continue;
                }
            }

            $batch = Bulk::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                ->orderBy('created_at')
                ->limit((int) config('services.broadcast.worker_batch', 50))
                ->get();

            if ($batch->isEmpty()) {
                $remaining = Bulk::where('campaign_id', $campaign->id)->where('status', 'pending')->count();
                if ($remaining === 0) $campaign->update(['status' => 'completed']);
                continue;
            }

            $campaign->update(['status' => 'processing']);
            $min = max(1, (int) ($campaign->delay ?: config('services.broadcast.min_delay', 15)));
            $max = max($min, (int) ($campaign->delay_max ?: config('services.broadcast.max_delay', 45)));
            $offset = 0;

            foreach ($batch as $bulk) {
                $offset += random_int($min, $max);
                $runAt = now()->addSeconds($offset);
                $bulk->update(['next_attempt_at' => $runAt]);
                SendBroadcastMessage::dispatch($bulk->id)->delay($runAt)->onQueue('broadcasts');
            }
        }
        return self::SUCCESS;
    }

    private function insideSendingWindow(Campaigns $campaign): bool
    {
        if (!$campaign->send_window_start || !$campaign->send_window_end) return true;
        $time = now()->format('H:i:s');
        return $time >= $campaign->send_window_start && $time <= $campaign->send_window_end;
    }
}
