<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use Sabri\File26\Support\InvariantViolation;

final class GenerationValidationPolicy
{
    public function __construct(
        private readonly int $minimumDocuments = 1,
        private readonly ?int $expectedDocuments = null,
        private readonly float $maximumDivergenceRatio = 0.10,
        private readonly int $maximumTombstones = 1000000
    ) {
        if ($minimumDocuments < 0 || $minimumDocuments > 1000000000) {
            throw new InvariantViolation('Minimum generation document count is outside the supported range.');
        }
        if ($expectedDocuments !== null && ($expectedDocuments < 0 || $expectedDocuments > 1000000000)) {
            throw new InvariantViolation('Expected generation document count is outside the supported range.');
        }
        if (! is_finite($maximumDivergenceRatio) || $maximumDivergenceRatio < 0.0 || $maximumDivergenceRatio > 1.0) {
            throw new InvariantViolation('Maximum generation divergence ratio must be between zero and one.');
        }
        if ($maximumTombstones < 0 || $maximumTombstones > 1000000000) {
            throw new InvariantViolation('Maximum generation tombstone count is outside the supported range.');
        }
    }

    public function assertCounts(int $documents, int $tombstones): void
    {
        if ($documents < $this->minimumDocuments) {
            throw new InvariantViolation('Generation document count is below the approved minimum.');
        }

        if ($tombstones > $this->maximumTombstones) {
            throw new InvariantViolation('Generation tombstone count exceeds the approved ceiling.');
        }

        if ($this->expectedDocuments === null) {
            return;
        }

        $difference = abs($documents - $this->expectedDocuments);
        $denominator = max(1, $this->expectedDocuments);
        $ratio = $difference / $denominator;
        if ($ratio > $this->maximumDivergenceRatio) {
            throw new InvariantViolation('Generation document-count divergence exceeds the approved threshold.');
        }
    }

    /** @return array{minimum_documents:int,expected_documents:?int,maximum_divergence_ratio:float,maximum_tombstones:int} */
    public function toArray(): array
    {
        return [
            'minimum_documents' => $this->minimumDocuments,
            'expected_documents' => $this->expectedDocuments,
            'maximum_divergence_ratio' => $this->maximumDivergenceRatio,
            'maximum_tombstones' => $this->maximumTombstones,
        ];
    }
}
