<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use Sabri\File26\Support\InvariantViolation;
use Throwable;

final class WordPressCliAdapter
{
    public function __construct(private readonly WordPressRuntime $runtime)
    {
    }

    public function register(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI || ! class_exists('WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('sabri-file26 jobs run', [$this, 'runJobs']);
        \WP_CLI::add_command('sabri-file26 jobs recover', [$this, 'recoverJobs']);
        \WP_CLI::add_command('sabri-file26 operations status', [$this, 'status']);
        \WP_CLI::add_command('sabri-file26 dead-letter replay', [$this, 'replayDeadLetter']);
        \WP_CLI::add_command('sabri-file26 connector probe', [$this, 'probeConnector']);
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function runJobs(array $args, array $assocArgs): void
    {
        unset($args);
        $maximum = $this->boundedInteger($assocArgs['max'] ?? 20, 1, 50, 'max');
        $batch = $this->boundedInteger($assocArgs['batch'] ?? 100, 1, 200, 'batch');
        $this->execute(fn () => $this->runtime->runRealCron($maximum, $batch));
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function recoverJobs(array $args, array $assocArgs): void
    {
        unset($args, $assocArgs);
        $this->execute(fn () => $this->runtime->recoverMissedRun());
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function status(array $args, array $assocArgs): void
    {
        unset($args, $assocArgs);
        $this->execute(fn () => $this->runtime->diagnostics());
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function replayDeadLetter(array $args, array $assocArgs): void
    {
        unset($args);
        $jobId = isset($assocArgs['job']) && is_string($assocArgs['job']) ? $assocArgs['job'] : '';
        $errorCode = isset($assocArgs['error']) && is_string($assocArgs['error']) ? $assocArgs['error'] : '';
        if ($jobId === '' || $errorCode === '') {
            \WP_CLI::error('Both --job=<sha256> and --error=<current-error-code> are required.');
        }

        $this->execute(fn () => $this->runtime->replayDeadLetter($jobId, $errorCode));
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function probeConnector(array $args, array $assocArgs): void
    {
        unset($args);
        $connector = isset($assocArgs['connector']) && is_string($assocArgs['connector']) ? $assocArgs['connector'] : '';
        if ($connector === '') {
            \WP_CLI::error('--connector=<registered-connector-key> is required.');
        }
        $batch = $this->boundedInteger($assocArgs['batch'] ?? 50, 1, 200, 'batch');
        $pages = $this->boundedInteger($assocArgs['pages'] ?? 50, 1, 100, 'pages');

        $this->execute(fn () => $this->runtime->probeConnector($connector, $batch, $pages));
    }

    /** @param callable():array<string,mixed> $operation */
    private function execute(callable $operation): void
    {
        try {
            $result = $operation();
            $json = function_exists('wp_json_encode')
                ? wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            \WP_CLI::line(is_string($json) ? $json : '{}');
            \WP_CLI::success('File 26 operation completed.');
        } catch (Throwable $exception) {
            $message = $exception instanceof InvariantViolation
                ? $exception->getMessage()
                : 'File 26 operation failed without exposing internal details.';
            \WP_CLI::error($message);
        }
    }

    private function boundedInteger(mixed $value, int $minimum, int $maximum, string $label): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            \WP_CLI::error(sprintf('--%s must be between %d and %d.', $label, $minimum, $maximum));
        }

        return $value;
    }
}
