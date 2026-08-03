<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Jobs\JobQueueInterface;
use Sabri\File26\Support\InvariantViolation;

final class WordPressWorkerScheduler
{
    public const HOOK = 'sabri_file26_process_jobs';
    public const RECOVERY_HOOK = 'sabri_file26_recover_jobs';
    public const SCHEDULE = 'sabri_file26_five_minutes';

    public function __construct(
        private readonly WorkerLoop $loop,
        private readonly JobQueueInterface $queue,
        private readonly MissedRunDetector $missedRunDetector = new MissedRunDetector()
    ) {
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'addSchedule']);
        add_action(self::HOOK, [$this, 'runScheduled']);
        add_action(self::RECOVERY_HOOK, [$this, 'runRecovery']);
        $this->ensureScheduled();
    }

    /** @param array<string,array<string,mixed>> $schedules @return array<string,array<string,mixed>> */
    public function addSchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE] = [
            'interval' => 300,
            'display' => __('Every five minutes — File 26 jobs', 'sabri-search-discovery'),
        ];

        return $schedules;
    }

    public function ensureScheduled(): bool
    {
        if (wp_next_scheduled(self::HOOK) !== false) {
            return false;
        }

        return wp_schedule_event(time() + 60, self::SCHEDULE, self::HOOK) !== false;
    }

    public function runScheduled(): void
    {
        $this->run('wp-cron');
    }

    public function runRecovery(): void
    {
        $this->run('missed-run-recovery');
    }

    /** @return array{processed:int,idle:bool,status_counts:array<string,int>,last_error_code:?string} */
    public function runFromRealCron(int $maximumJobs = 20, int $batchLimit = 100): array
    {
        return $this->run('real-cron-cli', $maximumJobs, $batchLimit);
    }

    /** @return array{scheduled:bool,inspection:array{status:string,missed:bool,lag_seconds:int,pending_jobs:int}} */
    public function recoverMissedRun(DateTimeImmutable $now): array
    {
        $stats = $this->queue->stats();
        $pending = $stats['queued'] + $stats['running'];
        $lastRun = $this->lastRunAt();
        $inspection = $this->missedRunDetector->inspect($lastRun, $now, $pending);
        $scheduled = false;

        if ($inspection['missed'] && wp_next_scheduled(self::RECOVERY_HOOK) === false) {
            $scheduled = wp_schedule_single_event(time() + 15, self::RECOVERY_HOOK) !== false;
        }

        return ['scheduled' => $scheduled, 'inspection' => $inspection];
    }

    /** @return array<string,mixed> */
    public function diagnostics(DateTimeImmutable $now): array
    {
        $stats = $this->queue->stats();
        $pending = $stats['queued'] + $stats['running'];
        $lastRun = $this->lastRunAt();
        $inspection = $this->missedRunDetector->inspect($lastRun, $now, $pending);
        $lastResult = get_option('sabri_file26_last_worker_result', []);

        return [
            'next_scheduled_at' => $this->timestampOrNull(wp_next_scheduled(self::HOOK)),
            'recovery_scheduled_at' => $this->timestampOrNull(wp_next_scheduled(self::RECOVERY_HOOK)),
            'last_run_at' => $lastRun?->format(DATE_ATOM),
            'last_source' => (string) get_option('sabri_file26_last_worker_source', ''),
            'last_result' => is_array($lastResult) ? $this->sanitizeResult($lastResult) : [],
            'missed_run' => $inspection,
        ];
    }

    public static function unschedule(): void
    {
        foreach ([self::HOOK, self::RECOVERY_HOOK] as $hook) {
            while (($timestamp = wp_next_scheduled($hook)) !== false) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /** @return array{processed:int,idle:bool,status_counts:array<string,int>,last_error_code:?string} */
    private function run(string $source, int $maximumJobs = 10, int $batchLimit = 100): array
    {
        if (! in_array($source, ['wp-cron', 'missed-run-recovery', 'real-cron-cli'], true)) {
            throw new InvariantViolation('Worker scheduler source is invalid.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $result = $this->loop->run($now, $maximumJobs, $batchLimit);
        update_option('sabri_file26_last_worker_run_at', $now->format(DATE_ATOM), false);
        update_option('sabri_file26_last_worker_source', $source, false);
        update_option('sabri_file26_last_worker_result', $result, false);

        return $result;
    }

    private function lastRunAt(): ?DateTimeImmutable
    {
        $value = get_option('sabri_file26_last_worker_run_at', '');
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (\Throwable $exception) {
            unset($exception);
            return null;
        }
    }

    private function timestampOrNull(int|false $timestamp): ?string
    {
        return is_int($timestamp)
            ? (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM)
            : null;
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function sanitizeResult(array $result): array
    {
        $counts = isset($result['status_counts']) && is_array($result['status_counts']) ? $result['status_counts'] : [];
        $safeCounts = [];
        foreach ($counts as $status => $count) {
            if (is_string($status) && preg_match('/^[a-z][a-z-]{1,49}$/', $status) === 1 && is_int($count)) {
                $safeCounts[$status] = $count;
            }
        }

        return [
            'processed' => isset($result['processed']) && is_int($result['processed']) ? $result['processed'] : 0,
            'idle' => isset($result['idle']) && is_bool($result['idle']) ? $result['idle'] : false,
            'status_counts' => $safeCounts,
            'last_error_code' => isset($result['last_error_code']) && is_string($result['last_error_code']) ? $result['last_error_code'] : null,
        ];
    }
}
