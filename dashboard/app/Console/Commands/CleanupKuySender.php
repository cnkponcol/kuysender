<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CleanupKuySender extends Command
{
    protected $signature = 'kuysender:cleanup {--dry-run : Show what would be removed without deleting it}';
    protected $description = 'Prune KuySender transient logs, deliveries, failed jobs, orphan runtime rows, and old temporary files.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $rules = config('kuysender.cleanup');
        $total = 0;

        $total += $this->pruneTable('gateway_logs', 'created_at', (int) $rules['gateway_log_days'], $dry);
        $total += $this->pruneTable('api_request_logs', 'created_at', (int) $rules['api_request_log_days'], $dry);
        $total += $this->pruneWebhook('delivered', (int) $rules['webhook_delivered_days'], $dry);
        $total += $this->pruneWebhook(['failed', 'retrying'], (int) $rules['webhook_failed_days'], $dry);
        $total += $this->pruneTable('failed_jobs', 'failed_at', (int) $rules['failed_job_days'], $dry);

        if ((int) $rules['message_days'] > 0) {
            $total += $this->pruneTable('messages', 'message_at', (int) $rules['message_days'], $dry);
        }

        $total += $this->pruneOrphans($dry);
        $total += $this->pruneTempFiles((int) $rules['temp_file_hours'], $dry);

        $this->info(($dry ? 'Dry run: ' : '').$total.' stale item(s) '.($dry ? 'would be removed.' : 'removed.'));
        return self::SUCCESS;
    }

    private function pruneTable(string $table, string $column, int $days, bool $dry): int
    {
        if ($days <= 0 || !DB::getSchemaBuilder()->hasTable($table)) return 0;
        $query = DB::table($table)->where($column, '<', now()->subDays($days));
        $count = $query->count();
        if (!$dry && $count) $query->delete();
        $this->line($table.': '.$count);
        return $count;
    }

    private function pruneWebhook(string|array $status, int $days, bool $dry): int
    {
        if ($days <= 0 || !DB::getSchemaBuilder()->hasTable('webhook_deliveries')) return 0;
        $query = DB::table('webhook_deliveries')->whereIn('status', (array) $status)->where('updated_at', '<', now()->subDays($days));
        $count = $query->count();
        if (!$dry && $count) $query->delete();
        $this->line('webhook_deliveries['.implode(',', (array) $status).']: '.$count);
        return $count;
    }

    private function pruneOrphans(bool $dry): int
    {
        $total = 0;
        foreach (['messages', 'contacts', 'contact_labels', 'auto_responders', 'ai_settings', 'ai_knowledge_items', 'campaigns', 'bulks'] as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table) || !DB::getSchemaBuilder()->hasColumn($table, 'session_id')) continue;
            $query = DB::table($table)->whereNotExists(function ($sub) use ($table) {
                $sub->selectRaw('1')->from('sessions')->whereColumn('sessions.id', $table.'.session_id');
            });
            $count = $query->count();
            if (!$dry && $count) $query->delete();
            if ($count) $this->line($table.' orphans: '.$count);
            $total += $count;
        }
        return $total;
    }

    private function pruneTempFiles(int $hours, bool $dry): int
    {
        if ($hours <= 0) return 0;
        $root = storage_path('app/public/temp');
        if (!is_dir($root)) return 0;
        $cutoff = now()->subHours($hours)->getTimestamp();
        $count = 0;
        foreach (File::allFiles($root) as $file) {
            if ($file->getFilename() === '.gitignore' || $file->getMTime() >= $cutoff) continue;
            $count++;
            if (!$dry) @unlink($file->getPathname());
        }
        $this->line('temporary files: '.$count);
        return $count;
    }
}
