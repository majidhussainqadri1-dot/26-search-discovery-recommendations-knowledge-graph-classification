<?php

declare(strict_types=1);

namespace Sabri\File26\Classification;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class ClassificationSuggestion
{
    public const STATES = ['suggested', 'review_pending', 'approved', 'rejected', 'applied', 'corrected', 'appealed', 'removed'];

    public function __construct(
        private readonly string $suggestionId,
        private readonly string $canonicalKey,
        private readonly string $termId,
        private readonly float $confidence,
        private readonly bool $highImpact,
        private readonly string $proposer,
        private readonly string $evidenceVersion,
        private readonly string $state,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?string $reviewer = null,
        private readonly ?string $reasonCode = null
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $suggestionId)
            || trim($canonicalKey) === '' || strlen($canonicalKey) > 292
            || ! preg_match('/^[a-z][a-z0-9._-]{2,63}$/', $termId)
            || $confidence < 0.0 || $confidence > 1.0
            || ! preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $proposer)
            || trim($evidenceVersion) === '' || strlen($evidenceVersion) > 100
            || ! in_array($state, self::STATES, true)) {
            throw new InvariantViolation('Classification suggestion metadata is invalid.');
        }
        if ($reviewer !== null && ! preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $reviewer)) {
            throw new InvariantViolation('Classification reviewer key is invalid.');
        }
        if ($reasonCode !== null && ! preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $reasonCode)) {
            throw new InvariantViolation('Classification reason code is invalid.');
        }
    }

    public function suggestionId(): string { return $this->suggestionId; }
    public function canonicalKey(): string { return $this->canonicalKey; }
    public function termId(): string { return $this->termId; }
    public function confidence(): float { return $this->confidence; }
    public function highImpact(): bool { return $this->highImpact; }
    public function proposer(): string { return $this->proposer; }
    public function evidenceVersion(): string { return $this->evidenceVersion; }
    public function state(): string { return $this->state; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function reviewer(): ?string { return $this->reviewer; }
    public function reasonCode(): ?string { return $this->reasonCode; }

    public function reviewed(string $state, string $reviewer, string $reasonCode, DateTimeImmutable $at): self
    {
        if (! in_array($state, ['approved', 'rejected', 'corrected', 'removed'], true)) {
            throw new InvariantViolation('Unsupported classification review transition.');
        }
        if ($this->highImpact && $reviewer === $this->proposer) {
            throw new InvariantViolation('High-impact classifications require proposer/reviewer separation.');
        }
        return new self(
            $this->suggestionId, $this->canonicalKey, $this->termId, $this->confidence,
            $this->highImpact, $this->proposer, $this->evidenceVersion, $state, $at,
            $reviewer, $reasonCode
        );
    }

    public function appealed(string $actor, string $reasonCode, DateTimeImmutable $at): self
    {
        if (! in_array($this->state, ['rejected', 'approved', 'applied', 'corrected'], true)) {
            throw new InvariantViolation('Classification suggestion is not appealable in its current state.');
        }
        return new self(
            $this->suggestionId, $this->canonicalKey, $this->termId, $this->confidence,
            $this->highImpact, $this->proposer, $this->evidenceVersion, 'appealed', $at,
            $actor, $reasonCode
        );
    }
}

use DateTimeZone;
use Sabri\File26\Taxonomy\TaxonomyRegistry;

final class ClassificationWorkflow
{
    private array $suggestions = [];

    public function __construct(private readonly TaxonomyRegistry $taxonomy, private readonly float $automaticThreshold = 0.95)
    {
        if ($automaticThreshold < 0.8 || $automaticThreshold > 1.0) {
            throw new InvariantViolation('Classification automatic threshold is unsafe.');
        }
    }

    public function suggest(string $canonicalKey,string $termId,float $confidence,bool $highImpact,string $proposer,string $evidenceVersion,string $idempotencyKey): ClassificationSuggestion
    {
        $this->taxonomy->get($termId);
        $id = hash('sha256', implode("\0", [$canonicalKey, $termId, $proposer, $evidenceVersion, $idempotencyKey]));
        if (isset($this->suggestions[$id])) { return $this->suggestions[$id]; }
        $state = ! $highImpact && $confidence >= $this->automaticThreshold ? 'approved' : 'review_pending';
        $suggestion = new ClassificationSuggestion($id,$canonicalKey,$termId,$confidence,$highImpact,$proposer,$evidenceVersion,$state,new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $this->suggestions[$id] = $suggestion;
        return $suggestion;
    }

    public function review(string $suggestionId, string $decision, string $reviewer, string $reasonCode): ClassificationSuggestion
    {
        $current = $this->suggestions[$suggestionId] ?? null;
        if (! $current instanceof ClassificationSuggestion) { throw new InvariantViolation('Classification suggestion was not found.'); }
        if (! in_array($current->state(), ['review_pending', 'appealed'], true)) { throw new InvariantViolation('Classification suggestion is not pending review.'); }
        return $this->suggestions[$suggestionId] = $current->reviewed($decision, $reviewer, $reasonCode, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    public function appeal(string $suggestionId, string $actor, string $reasonCode): ClassificationSuggestion
    {
        $current = $this->suggestions[$suggestionId] ?? null;
        if (! $current instanceof ClassificationSuggestion) { throw new InvariantViolation('Classification suggestion was not found.'); }
        return $this->suggestions[$suggestionId] = $current->appealed($actor, $reasonCode, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
    public function all(): array { return array_values($this->suggestions); }
}

use wpdb;

final class WordPressClassificationStore
{
    private readonly string $table;
    public function __construct(private readonly wpdb $db) { $this->table = $db->prefix . 's26_classifications'; }
    public function save(ClassificationSuggestion $suggestion): void
    {
        $written = $this->db->query($this->db->prepare(
            "INSERT INTO {$this->table} (classification_id,canonical_key,term_id,status,confidence,high_impact,proposer_key,reviewer_key,evidence_version,reason_code,updated_at) VALUES (%s,%s,%s,%s,%f,%d,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE status=VALUES(status),confidence=VALUES(confidence),high_impact=VALUES(high_impact),proposer_key=VALUES(proposer_key),reviewer_key=VALUES(reviewer_key),evidence_version=VALUES(evidence_version),reason_code=VALUES(reason_code),updated_at=VALUES(updated_at)",
            $suggestion->suggestionId(),$suggestion->canonicalKey(),$suggestion->termId(),$suggestion->state(),$suggestion->confidence(),$suggestion->highImpact()?1:0,$suggestion->proposer(),$suggestion->reviewer(),$suggestion->evidenceVersion(),$suggestion->reasonCode(),$suggestion->updatedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')
        ));
        if ($written === false) { throw new InvariantViolation('Classification persistence failed.'); }
    }
    public function forObject(string $canonicalKey, int $limit = 100): array
    {
        if (trim($canonicalKey) === '' || strlen($canonicalKey) > 292 || $limit < 1 || $limit > 500) { throw new InvariantViolation('Classification query is invalid.'); }
        $rows=$this->db->get_results($this->db->prepare("SELECT classification_id,canonical_key,term_id,status,confidence,high_impact,proposer_key,reviewer_key,evidence_version,reason_code,updated_at FROM {$this->table} WHERE canonical_key=%s ORDER BY updated_at DESC LIMIT %d",$canonicalKey,$limit),ARRAY_A);
        return is_array($rows)?$rows:[];
    }
}
