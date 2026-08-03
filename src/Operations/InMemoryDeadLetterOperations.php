<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class InMemoryDeadLetterOperations implements DeadLetterOperationsInterface
{
    /** @var array<string,array{generation_id:string,connector_key:string,attempt:int,error_code:string,replay_count:int,status:string,updated_at:DateTimeImmutable,generation_state:string,checkpoint_complete:bool}> */
    private array $rows = [];

    public function seed(
        string $jobId,
        string $generationId,
        string $connectorKey,
        int $attempt,
        string $errorCode,
        DateTimeImmutable $updatedAt,
        string $generationState = 'building',
        bool $checkpointComplete = false
    ): void {
        $this->assertJobId($jobId);
        $this->assertErrorCode($errorCode);
        if ($attempt < 0 || $attempt > 1000) {
            throw new InvariantViolation('Dead-letter attempts are outside the supported range.');
        }
        if (! in_array($generationState, ['building', 'validated', 'active', 'superseded', 'rolled_back'], true)) {
            throw new InvariantViolation('Dead-letter generation state is invalid.');
        }

        $this->rows[$jobId] = [
            'generation_id' => $generationId,
            'connector_key' => $connectorKey,
            'attempt' => $attempt,
            'error_code' => $errorCode,
            'replay_count' => 0,
            'status' => 'dead_letter',
            'updated_at' => $updatedAt,
            'generation_state' => $generationState,
            'checkpoint_complete' => $checkpointComplete,
        ];
    }

    public function deadLetters(int $limit = 20): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvariantViolation('Dead-letter list limits must be between 1 and 100.');
        }

        $rows = [];
        foreach ($this->rows as $jobId => $row) {
            if ($row['status'] !== 'dead_letter') {
                continue;
            }
            $rows[] = [
                'job_id' => $jobId,
                'generation_id' => $row['generation_id'],
                'connector_key' => $row['connector_key'],
                'attempt' => $row['attempt'],
                'error_code' => $row['error_code'],
                'replay_count' => $row['replay_count'],
                'updated_at' => $row['updated_at']->format(DATE_ATOM),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => ($right['updated_at'] <=> $left['updated_at']) ?: ($left['job_id'] <=> $right['job_id']));

        return array_slice($rows, 0, $limit);
    }

    public function replay(string $jobId, string $expectedErrorCode, DateTimeImmutable $replayedAt): array
    {
        $this->assertJobId($jobId);
        $this->assertErrorCode($expectedErrorCode);
        $row = &$this->rowForWrite($jobId);

        if ($row['status'] !== 'dead_letter') {
            throw new InvariantViolation('Only a dead-letter job may be replayed.');
        }
        if (! hash_equals($row['error_code'], $expectedErrorCode)) {
            throw new InvariantViolation('Dead-letter replay error-code confirmation failed.');
        }
        if ($row['generation_state'] !== 'building') {
            throw new InvariantViolation('Dead-letter replay requires a building generation.');
        }
        if ($row['checkpoint_complete']) {
            throw new InvariantViolation('Dead-letter replay is forbidden after connector completion.');
        }
        if ($row['replay_count'] >= 10) {
            throw new InvariantViolation('Dead-letter replay limit has been reached.');
        }

        ++$row['replay_count'];
        $row['status'] = 'queued';
        $row['updated_at'] = $replayedAt;

        return [
            'job_id' => $jobId,
            'status' => 'queued',
            'replay_count' => $row['replay_count'],
            'replayed_at' => $replayedAt->format(DATE_ATOM),
        ];
    }

    /** @return array{generation_id:string,connector_key:string,attempt:int,error_code:string,replay_count:int,status:string,updated_at:DateTimeImmutable,generation_state:string,checkpoint_complete:bool} */
    private function &rowForWrite(string $jobId): array
    {
        if (! isset($this->rows[$jobId])) {
            throw new InvariantViolation('Unknown dead-letter job identifier.');
        }

        return $this->rows[$jobId];
    }

    private function assertJobId(string $jobId): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $jobId) !== 1) {
            throw new InvariantViolation('Dead-letter job identifiers must be SHA-256 hex values.');
        }
    }

    private function assertErrorCode(string $errorCode): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode) !== 1) {
            throw new InvariantViolation('Dead-letter error codes are invalid.');
        }
    }
}
