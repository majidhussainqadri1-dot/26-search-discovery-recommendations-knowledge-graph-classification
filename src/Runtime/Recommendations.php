<?php

declare(strict_types=1);

namespace Sabri\File26\Recommendations;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Query\UnicodeNormalizer;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class RecommendationContext
{
    /**
     * @param list<string> $interests
     * @param list<string> $follows
     * @param list<string> $learningTopics
     * @param list<string> $savedTopics
     * @param list<string> $hiddenItems
     * @param list<string> $hiddenCreators
     * @param list<string> $hiddenTopics
     */
    public function __construct(
        private readonly bool $personalizationConsent,
        private readonly bool $minor,
        private readonly bool $guardianConsentVerified,
        private readonly array $interests = [],
        private readonly array $follows = [],
        private readonly array $learningTopics = [],
        private readonly array $savedTopics = [],
        private readonly array $hiddenItems = [],
        private readonly array $hiddenCreators = [],
        private readonly array $hiddenTopics = [],
        private readonly int $limit = 20
    ) {
        foreach ([
            'interests' => $interests,
            'follows' => $follows,
            'learning_topics' => $learningTopics,
            'saved_topics' => $savedTopics,
            'hidden_items' => $hiddenItems,
            'hidden_creators' => $hiddenCreators,
            'hidden_topics' => $hiddenTopics,
        ] as $label => $values) {
            if (! array_is_list($values)
                || count($values) > 100
                || count($values) !== count(array_unique($values, SORT_STRING))) {
                throw new InvariantViolation(
                    'Recommendation ' . $label . ' must be bounded and unique.'
                );
            }
            foreach ($values as $value) {
                if (! is_string($value) || trim($value) === '' || strlen($value) > 292) {
                    throw new InvariantViolation('Recommendation context value is invalid.');
                }
            }
        }

        if ($minor && ! $guardianConsentVerified && $personalizationConsent) {
            throw new InvariantViolation('Minor personalization requires verified guardian consent.');
        }
        if (! $personalizationConsent
            && ($interests !== [] || $follows !== [] || $learningTopics !== [] || $savedTopics !== [])) {
            throw new InvariantViolation('Personal recommendation signals require consent.');
        }
        if ($limit < 1 || $limit > 50) {
            throw new InvariantViolation('Recommendation limit is invalid.');
        }
    }

    public static function coldStart(int $limit = 20): self
    {
        return new self(false, false, false, limit: $limit);
    }

    public function personalizationConsent(): bool { return $this->personalizationConsent; }
    public function isMinor(): bool { return $this->minor; }
    public function guardianConsentVerified(): bool { return $this->guardianConsentVerified; }
    public function interests(): array { return $this->interests; }
    public function follows(): array { return $this->follows; }
    public function learningTopics(): array { return $this->learningTopics; }
    public function savedTopics(): array { return $this->savedTopics; }
    public function hiddenItems(): array { return $this->hiddenItems; }
    public function hiddenCreators(): array { return $this->hiddenCreators; }
    public function hiddenTopics(): array { return $this->hiddenTopics; }
    public function limit(): int { return $this->limit; }
}

final class RecommendationResult
{
    /** @param list<string> $reasons */
    public function __construct(
        private readonly SearchDocument $document,
        private readonly int $score,
        private readonly array $reasons,
        private readonly string $policyVersion
    ) {
        if ($score < 0
            || $score > 1000000
            || ! array_is_list($reasons)
            || $reasons === []
            || count($reasons) > 8
            || count($reasons) !== count(array_unique($reasons, SORT_STRING))
            || preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $policyVersion) !== 1) {
            throw new InvariantViolation('Recommendation result is invalid.');
        }
        foreach ($reasons as $reason) {
            if (! is_string($reason) || ! preg_match('/^[a-z][a-z0-9-]{1,79}$/', $reason)) {
                throw new InvariantViolation('Recommendation reason is invalid.');
            }
        }
    }

    public function document(): SearchDocument { return $this->document; }
    public function score(): int { return $this->score; }
    public function reasons(): array { return $this->reasons; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'document' => $this->document->toArray(),
            'score' => $this->score,
            'why_this' => $this->reasons,
            'policy_version' => $this->policyVersion,
            'controls' => [
                'hide', 'not_interested', 'hide_creator',
                'hide_topic', 'reset', 'opt_out',
            ],
            'click_visibility_recheck_required' => true,
        ];
    }
}

final class RecommendationEngine
{
    public const POLICY_VERSION = '1.0.0';

    public function __construct(private readonly UnicodeNormalizer $normalizer = new UnicodeNormalizer())
    {
    }

    /** @param list<SearchDocument> $documents @return list<RecommendationResult> */
    public function recommend(array $documents, RecommendationContext $context): array
    {
        if (! array_is_list($documents) || count($documents) > 5000) {
            throw new InvariantViolation('Recommendation candidates are unbounded.');
        }

        $interests = $this->normalizeList($context->interests());
        $learning = $this->normalizeList($context->learningTopics());
        $saved = $this->normalizeList($context->savedTopics());
        $hiddenTopics = $this->normalizeList($context->hiddenTopics());
        $ranked = [];

        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument
                || in_array($document->state(), ['restricted', 'retracted', 'suspended'], true)) {
                continue;
            }

            $fields = $document->fields();
            $creator = $this->text($fields['creator_id'] ?? '');
            $topics = $this->strings($fields['topics'] ?? []);

            if (in_array($document->canonicalKey(), $context->hiddenItems(), true)
                || ($creator !== '' && in_array($creator, $context->hiddenCreators(), true))
                || array_intersect($topics, $hiddenTopics) !== []) {
                continue;
            }
            if ($context->isMinor()
                && ($this->truthy($fields['adult_only'] ?? false)
                    || $this->truthy($fields['persuasive_commerce'] ?? false))) {
                continue;
            }

            $score = $this->signal($fields['authority_score'] ?? 0) * 4
                + $this->signal($fields['quality_score'] ?? 0) * 3
                + min(25, $this->signal($fields['trending_score'] ?? 0));
            $reasons = ['source-quality'];

            if ($context->personalizationConsent()) {
                if (array_intersect($topics, $interests) !== []) {
                    $score += 100;
                    $reasons[] = 'matches-declared-interest';
                }
                if (array_intersect($topics, $learning) !== []) {
                    $score += 80;
                    $reasons[] = 'continue-learning';
                }
                if (array_intersect($topics, $saved) !== []) {
                    $score += 50;
                    $reasons[] = 'related-to-saved-topic';
                }
                if ($creator !== '' && in_array($creator, $context->follows(), true)) {
                    $score += 60;
                    $reasons[] = 'followed-creator';
                }
            } else {
                $reasons[] = 'cold-start-curated';
            }

            $ranked[] = new RecommendationResult(
                $document,
                $score,
                array_values(array_unique($reasons, SORT_STRING)),
                self::POLICY_VERSION
            );
        }

        usort(
            $ranked,
            static fn (RecommendationResult $a, RecommendationResult $b): int =>
                ($b->score() <=> $a->score())
                ?: ($a->document()->canonicalKey() <=> $b->document()->canonicalKey())
        );

        $out = [];
        $creators = [];
        $domains = [];
        foreach ($ranked as $result) {
            $document = $result->document();
            $creator = $this->text($document->fields()['creator_id'] ?? '');
            if ($creator === '') {
                $creator = 'unknown:' . $document->canonicalKey();
            }
            $domain = (string) $document->toArray()['canonical_domain'];
            $creatorKey = 'c:' . $creator;
            $domainKey = 'd:' . $domain;

            if (($creators[$creatorKey] ?? 0) >= 2 || ($domains[$domainKey] ?? 0) >= 4) {
                continue;
            }

            $out[] = $result;
            $creators[$creatorKey] = ($creators[$creatorKey] ?? 0) + 1;
            $domains[$domainKey] = ($domains[$domainKey] ?? 0) + 1;
            if (count($out) >= $context->limit()) {
                break;
            }
        }

        return $out;
    }

    /** @param list<string> $values @return list<string> */
    private function normalizeList(array $values): array
    {
        /** @var array<string,string> $out */
        $out = [];
        foreach ($values as $value) {
            $normalized = $this->normalizer->normalizeForSearch($value);
            if ($normalized !== '') {
                $out['v:' . $normalized] = $normalized;
            }
        }
        return array_values($out);
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        /** @var array<string,string> $out */
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }
            $normalized = $this->normalizer->normalizeForSearch($item);
            if ($normalized !== '') {
                $out['v:' . $normalized] = $normalized;
            }
        }
        return array_values($out);
    }

    private function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function signal(mixed $value): int
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return 0;
        }
        if (is_string($value) && ! is_numeric($value)) {
            return 0;
        }
        return max(0, min(100, (int) round((float) $value)));
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}

final class FeedbackEvent
{
    public const TYPES = [
        'like', 'save', 'hide_item', 'hide_creator',
        'hide_topic', 'not_interested', 'reset', 'opt_out',
    ];

    public function __construct(
        private readonly string $idempotencyKey,
        private readonly string $actorHash,
        private readonly string $targetKey,
        private readonly string $type,
        private readonly string $contextHash,
        private readonly DateTimeImmutable $recordedAt,
        private readonly bool $reversed = false
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)
            || ! preg_match('/^[a-f0-9]{64}$/', $actorHash)
            || ! preg_match('/^[a-f0-9]{64}$/', $contextHash)
            || trim($targetKey) === ''
            || strlen($targetKey) > 292
            || ! in_array($type, self::TYPES, true)) {
            throw new InvariantViolation('Recommendation feedback is invalid.');
        }
    }

    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function actorHash(): string { return $this->actorHash; }
    public function targetKey(): string { return $this->targetKey; }
    public function type(): string { return $this->type; }
    public function contextHash(): string { return $this->contextHash; }
    public function recordedAt(): DateTimeImmutable { return $this->recordedAt; }
    public function reversed(): bool { return $this->reversed; }

    public function reverse(DateTimeImmutable $at): self
    {
        return new self(
            $this->idempotencyKey,
            $this->actorHash,
            $this->targetKey,
            $this->type,
            $this->contextHash,
            $at,
            true
        );
    }
}

interface FeedbackStoreInterface
{
    public function record(FeedbackEvent $event): FeedbackEvent;
    public function reverse(string $idempotencyKey, DateTimeImmutable $at): FeedbackEvent;
    /** @return list<FeedbackEvent> */
    public function activeForActor(string $actorHash, int $limit = 100): array;
    public function purgeActor(string $actorHash): int;
}

final class InMemoryFeedbackStore implements FeedbackStoreInterface
{
    /** @var array<string,FeedbackEvent> */
    private array $events = [];

    public function record(FeedbackEvent $event): FeedbackEvent
    {
        if (isset($this->events[$event->idempotencyKey()])) {
            return $this->events[$event->idempotencyKey()];
        }
        $this->events[$event->idempotencyKey()] = $event;
        return $event;
    }

    public function reverse(string $id, DateTimeImmutable $at): FeedbackEvent
    {
        $event = $this->events[$id] ?? null;
        if (! $event instanceof FeedbackEvent || $event->reversed()) {
            throw new InvariantViolation('Active feedback was not found.');
        }
        return $this->events[$id] = $event->reverse($at);
    }

    public function activeForActor(string $actorHash, int $limit = 100): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $actorHash) || $limit < 1 || $limit > 500) {
            throw new InvariantViolation('Feedback actor or limit is invalid.');
        }
        $out = [];
        foreach (array_reverse($this->events) as $event) {
            if ($event->actorHash() === $actorHash && ! $event->reversed()) {
                $out[] = $event;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    public function purgeActor(string $actorHash): int
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $actorHash)) {
            throw new InvariantViolation('Feedback actor is invalid.');
        }
        $count = 0;
        foreach ($this->events as $id => $event) {
            if ($event->actorHash() === $actorHash) {
                unset($this->events[$id]);
                ++$count;
            }
        }
        return $count;
    }
}

final class WordPressFeedbackStore implements FeedbackStoreInterface
{
    private readonly string $table;

    public function __construct(private readonly wpdb $db)
    {
        $this->table = $db->prefix . 's26_feedback';
    }

    public function record(FeedbackEvent $event): FeedbackEvent
    {
        $inserted = $this->db->query($this->db->prepare(
            "INSERT IGNORE INTO {$this->table}
                (feedback_id,actor_hash,target_key,feedback_type,state,context_hash,created_at,updated_at)
             VALUES (%s,%s,%s,%s,'active',%s,%s,%s)",
            $event->idempotencyKey(),
            $event->actorHash(),
            $event->targetKey(),
            $event->type(),
            $event->contextHash(),
            $this->utc($event->recordedAt()),
            $this->utc($event->recordedAt())
        ));
        if ($inserted === false) {
            throw new InvariantViolation('Recommendation feedback write failed.');
        }
        return $this->hydrate($event->idempotencyKey());
    }

    public function reverse(string $id, DateTimeImmutable $at): FeedbackEvent
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $id)) {
            throw new InvariantViolation('Feedback identifier is invalid.');
        }
        $updated = $this->db->query($this->db->prepare(
            "UPDATE {$this->table}
             SET state='reversed',updated_at=%s
             WHERE feedback_id=%s AND state='active'",
            $this->utc($at),
            $id
        ));
        if ($updated !== 1) {
            throw new InvariantViolation('Active feedback was not found.');
        }
        return $this->hydrate($id);
    }

    public function activeForActor(string $actorHash, int $limit = 100): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $actorHash) || $limit < 1 || $limit > 500) {
            throw new InvariantViolation('Feedback actor or limit is invalid.');
        }
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE actor_hash=%s AND state='active'
             ORDER BY updated_at DESC LIMIT %d",
            $actorHash,
            $limit
        ), ARRAY_A);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = $this->fromRow($row);
        }
        return $out;
    }

    public function purgeActor(string $actorHash): int
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $actorHash)) {
            throw new InvariantViolation('Feedback actor is invalid.');
        }
        $deleted = $this->db->query($this->db->prepare(
            "DELETE FROM {$this->table} WHERE actor_hash=%s",
            $actorHash
        ));
        if ($deleted === false) {
            throw new InvariantViolation('Feedback purge failed.');
        }
        return (int) $deleted;
    }

    private function hydrate(string $id): FeedbackEvent
    {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table} WHERE feedback_id=%s",
            $id
        ), ARRAY_A);
        if (! is_array($row)) {
            throw new InvariantViolation('Feedback persistence mismatch.');
        }
        return $this->fromRow($row);
    }

    private function fromRow(array $row): FeedbackEvent
    {
        return new FeedbackEvent(
            (string) $row['feedback_id'],
            (string) $row['actor_hash'],
            (string) $row['target_key'],
            (string) $row['feedback_type'],
            (string) $row['context_hash'],
            new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')),
            (string) $row['state'] === 'reversed'
        );
    }

    private function utc(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
