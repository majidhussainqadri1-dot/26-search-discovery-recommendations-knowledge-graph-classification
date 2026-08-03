<?php

declare(strict_types=1);

namespace Sabri\File26\Taxonomy;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class TaxonomyTerm
{
    public const STATES = ['draft', 'in_review', 'approved', 'active', 'deprecated', 'merged', 'split', 'retired'];

    /**
     * @param array<string,string> $preferredLabels
     * @param array<string,list<string>> $aliases
     * @param list<string> $parentIds
     * @param list<string> $relatedIds
     */
    public function __construct(
        private readonly string $termId,
        private readonly string $version,
        private readonly array $preferredLabels,
        private readonly array $aliases,
        private readonly array $parentIds,
        private readonly array $relatedIds,
        private readonly string $definition,
        private readonly string $ownerFile,
        private readonly string $state,
        private readonly ?string $redirectTermId,
        private readonly DateTimeImmutable $updatedAt
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $termId) !== 1
            || preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) !== 1
            || preg_match('/^[0-9]{2}$/', $ownerFile) !== 1
            || ! in_array($state, self::STATES, true)) {
            throw new InvariantViolation('Taxonomy term identity, version, owner or state is invalid.');
        }
        if ($preferredLabels === [] || count($preferredLabels) > 20) {
            throw new InvariantViolation('Taxonomy term requires bounded localized labels.');
        }
        foreach ($preferredLabels as $locale => $label) {
            if (! is_string($locale) || preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $locale) !== 1
                || ! is_string($label) || trim($label) === '' || strlen($label) > 500) {
                throw new InvariantViolation('Taxonomy preferred label is invalid.');
            }
        }
        foreach ($aliases as $locale => $values) {
            if (! is_string($locale) || ! array_key_exists($locale, $preferredLabels) || ! array_is_list($values) || count($values) > 100 || count($values) !== count(array_unique($values))) {
                throw new InvariantViolation('Taxonomy aliases must be bounded, localized and unique.');
            }
            foreach ($values as $value) {
                if (! is_string($value) || trim($value) === '' || strlen($value) > 500) {
                    throw new InvariantViolation('Taxonomy alias is invalid.');
                }
            }
        }
        $this->assertIdList($parentIds, 'parent');
        $this->assertIdList($relatedIds, 'related');
        if (in_array($termId, $parentIds, true) || in_array($termId, $relatedIds, true)) {
            throw new InvariantViolation('Taxonomy terms cannot reference themselves.');
        }
        if (trim($definition) === '' || strlen($definition) > 20000) {
            throw new InvariantViolation('Taxonomy definition is empty or too large.');
        }
        if (in_array($state, ['merged', 'split'], true) && ($redirectTermId === null || preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $redirectTermId) !== 1 || $redirectTermId === $termId)) {
            throw new InvariantViolation('Merged or split taxonomy terms require a distinct redirect target.');
        }
        if (! in_array($state, ['merged', 'split'], true) && $redirectTermId !== null) {
            throw new InvariantViolation('Only merged or split terms may carry a redirect.');
        }
    }

    public function termId(): string { return $this->termId; }
    public function version(): string { return $this->version; }
    /** @return array<string,string> */ public function preferredLabels(): array { return $this->preferredLabels; }
    /** @return array<string,list<string>> */ public function aliases(): array { return $this->aliases; }
    /** @return list<string> */ public function parentIds(): array { return $this->parentIds; }
    /** @return list<string> */ public function relatedIds(): array { return $this->relatedIds; }
    public function definition(): string { return $this->definition; }
    public function ownerFile(): string { return $this->ownerFile; }
    public function state(): string { return $this->state; }
    public function redirectTermId(): ?string { return $this->redirectTermId; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'term_id' => $this->termId,
            'version' => $this->version,
            'preferred_labels' => $this->preferredLabels,
            'aliases' => $this->aliases,
            'parent_ids' => $this->parentIds,
            'related_ids' => $this->relatedIds,
            'definition' => $this->definition,
            'owner_file' => $this->ownerFile,
            'state' => $this->state,
            'redirect_term_id' => $this->redirectTermId,
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    /** @param list<string> $values */
    private function assertIdList(array $values, string $label): void
    {
        if (! array_is_list($values) || count($values) > 100 || count($values) !== count(array_unique($values))) {
            throw new InvariantViolation('Taxonomy ' . $label . ' references must be bounded and unique.');
        }
        foreach ($values as $value) {
            if (! is_string($value) || preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $value) !== 1) {
                throw new InvariantViolation('Taxonomy ' . $label . ' reference is invalid.');
            }
        }
    }
}

final class TaxonomyRegistry
{
    /** @var array<string,TaxonomyTerm> */
    private array $terms = [];

    public function register(TaxonomyTerm $term): void
    {
        $current = $this->terms[$term->termId()] ?? null;
        if ($current instanceof TaxonomyTerm && version_compare($term->version(), $current->version(), '<=')) {
            throw new InvariantViolation('Taxonomy versions must increase monotonically.');
        }
        foreach (array_merge($term->parentIds(), $term->relatedIds()) as $reference) {
            if (! isset($this->terms[$reference])) {
                throw new InvariantViolation('Taxonomy reference points to an unknown concept: ' . $reference);
            }
        }
        $candidate = $this->terms;
        $candidate[$term->termId()] = $term;
        $this->assertNoLabelCollisions($candidate);
        $this->assertAcyclic($candidate);
        $this->terms[$term->termId()] = $term;
    }

    public function get(string $termId): TaxonomyTerm
    {
        $term = $this->terms[$termId] ?? null;
        if (! $term instanceof TaxonomyTerm) {
            throw new InvariantViolation('Unknown taxonomy term: ' . $termId);
        }
        return $term;
    }

    /** @return array<string,TaxonomyTerm> */
    public function all(): array
    {
        ksort($this->terms);
        return $this->terms;
    }

    /** @return array{source:string,target:string,source_version:string,target_version:string,children:list<string>,related:list<string>} */
    public function previewMerge(string $sourceId, string $targetId): array
    {
        if ($sourceId === $targetId) {
            throw new InvariantViolation('A taxonomy term cannot merge into itself.');
        }
        $source = $this->get($sourceId);
        $target = $this->get($targetId);
        if (! in_array($source->state(), ['approved', 'active', 'deprecated'], true) || ! in_array($target->state(), ['approved', 'active'], true)) {
            throw new InvariantViolation('Only eligible taxonomy states may be merged.');
        }
        $children = [];
        $related = [];
        foreach ($this->terms as $term) {
            if (in_array($sourceId, $term->parentIds(), true)) { $children[] = $term->termId(); }
            if (in_array($sourceId, $term->relatedIds(), true)) { $related[] = $term->termId(); }
        }
        sort($children); sort($related);
        return ['source' => $sourceId, 'target' => $targetId, 'source_version' => $source->version(), 'target_version' => $target->version(), 'children' => $children, 'related' => $related];
    }

    /** @param array{source:string,target:string,source_version:string,target_version:string,children:list<string>,related:list<string>} $preview */
    public function applyMerge(array $preview, DateTimeImmutable $at): void
    {
        $source = $this->get($preview['source']);
        $target = $this->get($preview['target']);
        if ($source->version() !== $preview['source_version'] || $target->version() !== $preview['target_version']) {
            throw new InvariantViolation('Taxonomy merge preview is stale.');
        }
        $updated = [];
        foreach ($this->terms as $id => $term) {
            $parents = array_values(array_unique(array_map(static fn (string $value): string => $value === $source->termId() ? $target->termId() : $value, $term->parentIds())));
            $related = array_values(array_unique(array_map(static fn (string $value): string => $value === $source->termId() ? $target->termId() : $value, $term->relatedIds())));
            $updated[$id] = $id === $source->termId()
                ? new TaxonomyTerm($id, $this->nextVersion($term->version()), $term->preferredLabels(), $term->aliases(), $term->parentIds(), $term->relatedIds(), $term->definition(), $term->ownerFile(), 'merged', $target->termId(), $at)
                : new TaxonomyTerm($id, ($parents !== $term->parentIds() || $related !== $term->relatedIds()) ? $this->nextVersion($term->version()) : $term->version(), $term->preferredLabels(), $term->aliases(), $parents, $related, $term->definition(), $term->ownerFile(), $term->state(), $term->redirectTermId(), $at);
        }
        $this->assertNoLabelCollisions($updated);
        $this->assertAcyclic($updated);
        $this->terms = $updated;
    }

    /** @param array<string,TaxonomyTerm> $terms */
    private function assertNoLabelCollisions(array $terms): void
    {
        $seen = [];
        foreach ($terms as $term) {
            if (! in_array($term->state(), ['approved', 'active'], true)) { continue; }
            foreach ($term->preferredLabels() as $locale => $label) {
                $values = array_merge([$label], $term->aliases()[$locale] ?? []);
                foreach ($values as $value) {
                    $normalized = $this->normalizeLabel($value);
                    $key = strtolower(str_replace('_', '-', $locale)) . ':' . $normalized;
                    if (isset($seen[$key]) && $seen[$key] !== $term->termId()) {
                        throw new InvariantViolation('Duplicate active taxonomy label or alias is forbidden.');
                    }
                    $seen[$key] = $term->termId();
                }
            }
        }
    }

    /** @param array<string,TaxonomyTerm> $terms */
    private function assertAcyclic(array $terms): void
    {
        $visiting = [];
        $visited = [];
        $visit = function (string $id) use (&$visit, &$visiting, &$visited, $terms): void {
            if (isset($visited[$id])) { return; }
            if (isset($visiting[$id])) { throw new InvariantViolation('Taxonomy parent cycle is forbidden.'); }
            $visiting[$id] = true;
            foreach ($terms[$id]->parentIds() as $parent) { $visit($parent); }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($terms) as $id) { $visit($id); }
    }

    private function normalizeLabel(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function nextVersion(string $version): string
    {
        [$major, $minor, $patch] = array_map('intval', explode('.', $version));
        return $major . '.' . $minor . '.' . ($patch + 1);
    }
}

final class WordPressTaxonomyStore
{
    private readonly string $table;
    public function __construct(private readonly wpdb $db) { $this->table = $db->prefix . 's26_taxonomy_terms'; }

    public function save(TaxonomyTerm $term, string $actorHash, string $reason, string $traceId): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $actorHash) !== 1 || trim($reason) === '' || strlen($reason) > 1000 || preg_match('/^[a-f0-9]{32,64}$/', $traceId) !== 1) {
            throw new InvariantViolation('Taxonomy audit metadata is invalid.');
        }
        $payload = json_encode($term->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $payload);
        $updated = $this->utc($term->updatedAt());
        $this->db->query('START TRANSACTION');
        try {
            $current = $this->db->get_row($this->db->prepare("SELECT version FROM {$this->table} WHERE term_id=%s FOR UPDATE", $term->termId()), ARRAY_A);
            if (is_array($current) && version_compare($term->version(), (string) $current['version'], '<=')) {
                throw new InvariantViolation('Persistent taxonomy version did not advance.');
            }
            $written = $this->db->query($this->db->prepare(
                "INSERT INTO {$this->table} (term_id,version,state,owner_file,redirect_term_id,payload,payload_hash,actor_hash,reason,trace_id,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE version=VALUES(version),state=VALUES(state),owner_file=VALUES(owner_file),redirect_term_id=VALUES(redirect_term_id),payload=VALUES(payload),payload_hash=VALUES(payload_hash),actor_hash=VALUES(actor_hash),reason=VALUES(reason),trace_id=VALUES(trace_id),updated_at=VALUES(updated_at)",
                $term->termId(), $term->version(), $term->state(), $term->ownerFile(), $term->redirectTermId(), $payload, $hash, $actorHash, $reason, $traceId, $updated
            ));
            if ($written === false) { throw new InvariantViolation('Persistent taxonomy write failed.'); }
            $this->db->query('COMMIT');
        } catch (\Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    public function get(string $termId): TaxonomyTerm
    {
        $row = $this->db->get_row($this->db->prepare("SELECT payload,payload_hash FROM {$this->table} WHERE term_id=%s", $termId), ARRAY_A);
        if (! is_array($row) || ! is_string($row['payload']) || ! is_string($row['payload_hash']) || ! hash_equals($row['payload_hash'], hash('sha256', $row['payload']))) {
            throw new InvariantViolation('Persistent taxonomy term is missing or corrupt.');
        }
        $data = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        return self::hydrate($data);
    }

    /** @return list<TaxonomyTerm> */
    public function active(int $limit = 1000): array
    {
        if ($limit < 1 || $limit > 5000) { throw new InvariantViolation('Taxonomy read limit is invalid.'); }
        $rows = $this->db->get_results($this->db->prepare("SELECT payload,payload_hash FROM {$this->table} WHERE state IN ('approved','active') ORDER BY term_id ASC LIMIT %d", $limit), ARRAY_A);
        $terms = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_string($row['payload']) || ! is_string($row['payload_hash']) || ! hash_equals($row['payload_hash'], hash('sha256', $row['payload']))) { throw new InvariantViolation('Persistent taxonomy payload integrity failed.'); }
            $terms[] = self::hydrate(json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR));
        }
        return $terms;
    }

    /** @param array<string,mixed> $data */
    public static function hydrate(array $data): TaxonomyTerm
    {
        $expected = ['term_id','version','preferred_labels','aliases','parent_ids','related_ids','definition','owner_file','state','redirect_term_id','updated_at'];
        if (array_keys($data) !== $expected) { throw new InvariantViolation('Taxonomy payload shape is invalid.'); }
        return new TaxonomyTerm((string)$data['term_id'], (string)$data['version'], $data['preferred_labels'], $data['aliases'], $data['parent_ids'], $data['related_ids'], (string)$data['definition'], (string)$data['owner_file'], (string)$data['state'], $data['redirect_term_id'] === null ? null : (string)$data['redirect_term_id'], new DateTimeImmutable((string)$data['updated_at']));
    }

    private function utc(DateTimeImmutable $at): string { return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'); }
}

namespace Sabri\File26\Classification;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class ClassificationSuggestion
{
    public const STATES = ['suggested','review_pending','approved','rejected','applied','corrected','appealed','removed'];
    public function __construct(
        private readonly string $suggestionId,
        private readonly string $canonicalKey,
        private readonly string $termId,
        private readonly float $confidence,
        private readonly string $sourceKind,
        private readonly string $sourceVersion,
        private readonly string $evidence,
        private readonly bool $highImpact,
        private readonly string $state,
        private readonly string $proposerHash,
        private readonly ?string $reviewerHash,
        private readonly ?string $decisionReason,
        private readonly DateTimeImmutable $updatedAt
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $suggestionId) !== 1 || trim($canonicalKey) === '' || strlen($canonicalKey) > 292
            || preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $termId) !== 1 || $confidence < 0 || $confidence > 1
            || ! in_array($sourceKind, ['rule','model','human'], true) || trim($sourceVersion) === '' || strlen($sourceVersion) > 100
            || trim($evidence) === '' || strlen($evidence) > 20000 || ! in_array($state, self::STATES, true)
            || preg_match('/^[a-f0-9]{64}$/', $proposerHash) !== 1
            || ($reviewerHash !== null && preg_match('/^[a-f0-9]{64}$/', $reviewerHash) !== 1)
            || ($decisionReason !== null && (trim($decisionReason) === '' || strlen($decisionReason) > 2000))) {
            throw new InvariantViolation('Classification suggestion is invalid.');
        }
        if ($highImpact && in_array($state, ['approved','applied'], true) && $reviewerHash === null) {
            throw new InvariantViolation('High-impact classification requires a human reviewer.');
        }
        if ($highImpact && $reviewerHash !== null && hash_equals($proposerHash, $reviewerHash)) {
            throw new InvariantViolation('High-impact classification proposer cannot self-review.');
        }
    }
    public static function create(string $canonicalKey,string $termId,float $confidence,string $sourceKind,string $sourceVersion,string $evidence,bool $highImpact,string $proposerHash,DateTimeImmutable $at):self
    { $id=hash('sha256',implode("\0",[$canonicalKey,$termId,$sourceKind,$sourceVersion,$proposerHash])); return new self($id,$canonicalKey,$termId,$confidence,$sourceKind,$sourceVersion,$evidence,$highImpact,$highImpact?'review_pending':'suggested',$proposerHash,null,null,$at); }
    public function suggestionId():string{return $this->suggestionId;} public function canonicalKey():string{return $this->canonicalKey;} public function termId():string{return $this->termId;} public function confidence():float{return $this->confidence;} public function sourceKind():string{return $this->sourceKind;} public function sourceVersion():string{return $this->sourceVersion;} public function evidence():string{return $this->evidence;} public function highImpact():bool{return $this->highImpact;} public function state():string{return $this->state;} public function proposerHash():string{return $this->proposerHash;} public function reviewerHash():?string{return $this->reviewerHash;} public function decisionReason():?string{return $this->decisionReason;} public function updatedAt():DateTimeImmutable{return $this->updatedAt;}
    public function transition(string $state,string $reviewerHash,string $reason,DateTimeImmutable $at):self
    { if(!in_array($state,['approved','rejected','corrected','appealed','removed'],true))throw new InvariantViolation('Classification transition is invalid.'); if(preg_match('/^[a-f0-9]{64}$/',$reviewerHash)!==1||trim($reason)===''||strlen($reason)>2000)throw new InvariantViolation('Classification decision metadata is invalid.'); return new self($this->suggestionId,$this->canonicalKey,$this->termId,$this->confidence,$this->sourceKind,$this->sourceVersion,$this->evidence,$this->highImpact,$state,$this->proposerHash,$reviewerHash,$reason,$at); }
    public function toArray():array{return ['suggestion_id'=>$this->suggestionId,'canonical_key'=>$this->canonicalKey,'term_id'=>$this->termId,'confidence'=>$this->confidence,'source_kind'=>$this->sourceKind,'source_version'=>$this->sourceVersion,'evidence'=>$this->evidence,'high_impact'=>$this->highImpact,'state'=>$this->state,'proposer_hash'=>$this->proposerHash,'reviewer_hash'=>$this->reviewerHash,'decision_reason'=>$this->decisionReason,'updated_at'=>$this->updatedAt->format(DATE_ATOM)];}
}

final class ClassificationWorkflow
{
    /** @var array<string,ClassificationSuggestion> */ private array $suggestions=[];
    public function submit(ClassificationSuggestion $suggestion):void{if(isset($this->suggestions[$suggestion->suggestionId()]))throw new InvariantViolation('Duplicate classification suggestion is forbidden.');$this->suggestions[$suggestion->suggestionId()]=$suggestion;}
    public function decide(string $id,string $decision,string $reviewerHash,string $reason,DateTimeImmutable $at):ClassificationSuggestion
    { $current=$this->get($id); if(!in_array($current->state(),['suggested','review_pending','appealed'],true))throw new InvariantViolation('Classification is not awaiting a decision.'); if(!in_array($decision,['approved','rejected','corrected','removed'],true))throw new InvariantViolation('Classification decision is unsupported.'); if($current->highImpact()&&hash_equals($current->proposerHash(),$reviewerHash))throw new InvariantViolation('High-impact proposer cannot approve or reject their own suggestion.'); return $this->suggestions[$id]=$current->transition($decision,$reviewerHash,$reason,$at); }
    public function appeal(string $id,string $actorHash,string $reason,DateTimeImmutable $at):ClassificationSuggestion
    { $current=$this->get($id); if(!in_array($current->state(),['approved','rejected','corrected','applied'],true))throw new InvariantViolation('Classification state cannot be appealed.'); return $this->suggestions[$id]=$current->transition('appealed',$actorHash,$reason,$at); }
    public function get(string $id):ClassificationSuggestion{$value=$this->suggestions[$id]??null;if(!$value instanceof ClassificationSuggestion)throw new InvariantViolation('Unknown classification suggestion.');return $value;}
}

final class WordPressClassificationStore
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_classifications';}
    public function save(ClassificationSuggestion $suggestion,string $traceId):void
    { if(preg_match('/^[a-f0-9]{32,64}$/',$traceId)!==1)throw new InvariantViolation('Classification trace identifier is invalid.');$payload=json_encode($suggestion->toArray(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$hash=hash('sha256',$payload);$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (suggestion_id,canonical_key,term_id,state,confidence,high_impact,payload,payload_hash,trace_id,updated_at) VALUES (%s,%s,%s,%s,%f,%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE state=VALUES(state),confidence=VALUES(confidence),high_impact=VALUES(high_impact),payload=VALUES(payload),payload_hash=VALUES(payload_hash),trace_id=VALUES(trace_id),updated_at=VALUES(updated_at)",$suggestion->suggestionId(),$suggestion->canonicalKey(),$suggestion->termId(),$suggestion->state(),$suggestion->confidence(),$suggestion->highImpact()?1:0,$payload,$hash,$traceId,$suggestion->updatedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')));if($written===false)throw new InvariantViolation('Classification persistence failed.');}
    public function get(string $id):ClassificationSuggestion{$row=$this->db->get_row($this->db->prepare("SELECT payload,payload_hash FROM {$this->table} WHERE suggestion_id=%s",$id),ARRAY_A);if(!is_array($row)||!hash_equals((string)$row['payload_hash'],hash('sha256',(string)$row['payload'])))throw new InvariantViolation('Classification payload is missing or corrupt.');$d=json_decode((string)$row['payload'],true,32,JSON_THROW_ON_ERROR);return new ClassificationSuggestion((string)$d['suggestion_id'],(string)$d['canonical_key'],(string)$d['term_id'],(float)$d['confidence'],(string)$d['source_kind'],(string)$d['source_version'],(string)$d['evidence'],(bool)$d['high_impact'],(string)$d['state'],(string)$d['proposer_hash'],$d['reviewer_hash']===null?null:(string)$d['reviewer_hash'],$d['decision_reason']===null?null:(string)$d['decision_reason'],new DateTimeImmutable((string)$d['updated_at']));}
}

namespace Sabri\File26\KnowledgeGraph;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class GraphEdge
{
    public const TYPES=['author-of','lesson-about','remedy-related','doctor-published','book-chapter','video-explains','research-supports','research-contradicts','post-about','pdf-authored-by','correction-of'];
    /** @param list<string> $requiredCapabilities */
    public function __construct(private readonly string $edgeId,private readonly string $sourceKey,private readonly string $targetKey,private readonly string $type,private readonly string $ownerFile,private readonly string $evidenceUrl,private readonly string $sourceVersion,private readonly bool $public,private readonly array $requiredCapabilities,private readonly DateTimeImmutable $updatedAt)
    { if(preg_match('/^[a-f0-9]{64}$/',$edgeId)!==1||trim($sourceKey)===''||trim($targetKey)===''||$sourceKey===$targetKey||strlen($sourceKey)>292||strlen($targetKey)>292||!in_array($type,self::TYPES,true)||preg_match('/^[0-9]{2}$/',$ownerFile)!==1||!filter_var($evidenceUrl,FILTER_VALIDATE_URL)||!str_starts_with($evidenceUrl,'https://')||trim($sourceVersion)===''||strlen($sourceVersion)>100||!array_is_list($requiredCapabilities)||count($requiredCapabilities)>20||count($requiredCapabilities)!==count(array_unique($requiredCapabilities)))throw new InvariantViolation('Knowledge graph edge is invalid.'); foreach($requiredCapabilities as $capability)if(!is_string($capability)||preg_match('/^[a-z][a-z0-9_]{2,99}$/',$capability)!==1)throw new InvariantViolation('Knowledge graph edge capability is invalid.');if($public&&$requiredCapabilities!==[])throw new InvariantViolation('Public graph edges cannot require hidden capabilities.'); }
    public static function create(string $sourceKey,string $targetKey,string $type,string $ownerFile,string $evidenceUrl,string $sourceVersion,bool $public,array $requiredCapabilities,DateTimeImmutable $at):self{$id=hash('sha256',implode("\0",[$sourceKey,$targetKey,$type,$ownerFile,$sourceVersion]));return new self($id,$sourceKey,$targetKey,$type,$ownerFile,$evidenceUrl,$sourceVersion,$public,$requiredCapabilities,$at);}
    public function edgeId():string{return $this->edgeId;} public function sourceKey():string{return $this->sourceKey;} public function targetKey():string{return $this->targetKey;} public function type():string{return $this->type;} public function ownerFile():string{return $this->ownerFile;} public function evidenceUrl():string{return $this->evidenceUrl;} public function sourceVersion():string{return $this->sourceVersion;} public function isPublic():bool{return $this->public;} public function requiredCapabilities():array{return $this->requiredCapabilities;} public function updatedAt():DateTimeImmutable{return $this->updatedAt;}
    public function visibleTo(AudienceContext $audience):bool{if($this->public)return true;if(!$audience->isAuthenticated())return false;foreach($this->requiredCapabilities as $capability)if(!$audience->hasCapability($capability))return false;return true;}
    public function toArray():array{return ['edge_id'=>$this->edgeId,'source_key'=>$this->sourceKey,'target_key'=>$this->targetKey,'type'=>$this->type,'owner_file'=>$this->ownerFile,'evidence_url'=>$this->evidenceUrl,'source_version'=>$this->sourceVersion,'public'=>$this->public,'required_capabilities'=>$this->requiredCapabilities,'updated_at'=>$this->updatedAt->format(DATE_ATOM)];}
}

final class GraphTraversalResult
{
    /** @param list<string> $nodes @param list<GraphEdge> $edges */
    public function __construct(private readonly array $nodes,private readonly array $edges,private readonly bool $truncated)
    {if(!array_is_list($nodes)||!array_is_list($edges)||count($nodes)>500||count($edges)>2000)throw new InvariantViolation('Graph traversal result is invalid.');}
    public function nodes():array{return $this->nodes;} public function edges():array{return $this->edges;} public function truncated():bool{return $this->truncated;}
}

final class KnowledgeGraph
{
    /** @var array<string,true> */ private array $nodes=[]; /** @var array<string,GraphEdge> */ private array $edges=[];
    public function registerNode(string $canonicalKey):void{if(trim($canonicalKey)===''||strlen($canonicalKey)>292)throw new InvariantViolation('Knowledge graph node is invalid.');$this->nodes[$canonicalKey]=true;}
    public function addEdge(GraphEdge $edge):void{if(!isset($this->nodes[$edge->sourceKey()],$this->nodes[$edge->targetKey()]))throw new InvariantViolation('Knowledge graph edge endpoints must exist.');if(isset($this->edges[$edge->edgeId()]))throw new InvariantViolation('Duplicate graph edge is forbidden.');$this->edges[$edge->edgeId()]=$edge;}
    /** @param list<string> $allowedTypes */ public function traverse(string $start,array $allowedTypes,AudienceContext $audience,int $maximumDepth=2,int $maximumDegree=20,int $maximumNodes=100):GraphTraversalResult
    {if(!isset($this->nodes[$start])||!array_is_list($allowedTypes)||$allowedTypes===[]||count($allowedTypes)>20||count($allowedTypes)!==count(array_unique($allowedTypes))||$maximumDepth<0||$maximumDepth>5||$maximumDegree<1||$maximumDegree>100||$maximumNodes<1||$maximumNodes>500)throw new InvariantViolation('Graph traversal request is invalid.');foreach($allowedTypes as $type)if(!in_array($type,GraphEdge::TYPES,true))throw new InvariantViolation('Graph traversal edge type is unsupported.');$queue=[[$start,0]];$seen=[$start=>true];$used=[];$truncated=false;while($queue!==[]){[$node,$depth]=array_shift($queue);if($depth>=$maximumDepth)continue;$degree=0;foreach($this->edges as $edge){if($edge->sourceKey()!==$node||!in_array($edge->type(),$allowedTypes,true)||!$edge->visibleTo($audience))continue;if(++$degree>$maximumDegree){$truncated=true;break;}$target=$edge->targetKey();if(!isset($this->nodes[$target]))continue;$used[$edge->edgeId()]=$edge;if(!isset($seen[$target])){$seen[$target]=true;if(count($seen)>=$maximumNodes){$truncated=true;break 2;}$queue[]=[$target,$depth+1];}}}return new GraphTraversalResult(array_keys($seen),array_values($used),$truncated);}
    public function integrity():array{$orphans=0;foreach($this->edges as $edge)if(!isset($this->nodes[$edge->sourceKey()],$this->nodes[$edge->targetKey()]))++$orphans;return ['nodes'=>count($this->nodes),'edges'=>count($this->edges),'orphans'=>$orphans];}
}

final class WordPressGraphStore
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_graph_edges';}
    public function save(GraphEdge $edge,string $actorHash,string $reason,string $traceId):void{if(preg_match('/^[a-f0-9]{64}$/',$actorHash)!==1||trim($reason)===''||preg_match('/^[a-f0-9]{32,64}$/',$traceId)!==1)throw new InvariantViolation('Graph audit metadata is invalid.');$payload=json_encode($edge->toArray(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$hash=hash('sha256',$payload);$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (edge_id,source_key,target_key,edge_type,owner_file,is_public,payload,payload_hash,actor_hash,reason,trace_id,updated_at) VALUES (%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE source_key=VALUES(source_key),target_key=VALUES(target_key),edge_type=VALUES(edge_type),owner_file=VALUES(owner_file),is_public=VALUES(is_public),payload=VALUES(payload),payload_hash=VALUES(payload_hash),actor_hash=VALUES(actor_hash),reason=VALUES(reason),trace_id=VALUES(trace_id),updated_at=VALUES(updated_at)",$edge->edgeId(),$edge->sourceKey(),$edge->targetKey(),$edge->type(),$edge->ownerFile(),$edge->isPublic()?1:0,$payload,$hash,$actorHash,$reason,$traceId,$edge->updatedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')));if($written===false)throw new InvariantViolation('Graph edge persistence failed.');}
    /** @return list<GraphEdge> */ public function outgoing(string $sourceKey,AudienceContext $audience,array $allowedTypes,int $limit=100):array{if($limit<1||$limit>500)throw new InvariantViolation('Graph edge read limit is invalid.');$rows=$this->db->get_results($this->db->prepare("SELECT payload,payload_hash FROM {$this->table} WHERE source_key=%s ORDER BY edge_id ASC LIMIT %d",$sourceKey,$limit),ARRAY_A);$out=[];foreach(is_array($rows)?$rows:[] as $row){if(!hash_equals((string)$row['payload_hash'],hash('sha256',(string)$row['payload'])))throw new InvariantViolation('Graph payload integrity failed.');$d=json_decode((string)$row['payload'],true,32,JSON_THROW_ON_ERROR);$edge=new GraphEdge((string)$d['edge_id'],(string)$d['source_key'],(string)$d['target_key'],(string)$d['type'],(string)$d['owner_file'],(string)$d['evidence_url'],(string)$d['source_version'],(bool)$d['public'],$d['required_capabilities'],new DateTimeImmutable((string)$d['updated_at']));if(in_array($edge->type(),$allowedTypes,true)&&$edge->visibleTo($audience))$out[]=$edge;}return $out;}
}
