<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class WordPressActiveGenerationRepository implements ActiveGenerationRepositoryInterface
{
    private readonly string $aliasesTable;
    private readonly string $generationsTable;
    private readonly string $documentsTable;

    public function __construct(
        private readonly wpdb $db,
        private readonly SearchDocumentHydrator $hydrator = new SearchDocumentHydrator()
    ) {
        $prefix = $db->prefix . 's26_';
        $this->aliasesTable = $prefix . 'aliases';
        $this->generationsTable = $prefix . 'generations';
        $this->documentsTable = $prefix . 'documents';
    }

    public function activeGenerationId(): ?string
    {
        $value = $this->db->get_var(
            "SELECT a.generation_id
             FROM {$this->aliasesTable} a
             INNER JOIN {$this->generationsTable} g ON g.generation_id = a.generation_id
             WHERE a.alias_key = 'active' AND g.state = 'active'"
        );

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isReadableGeneration(string $generationId): bool
    {
        $this->assertGenerationId($generationId);
        $state = $this->db->get_var($this->db->prepare(
            "SELECT state FROM {$this->generationsTable} WHERE generation_id = %s",
            $generationId
        ));

        return $state === 'active' || $state === 'superseded';
    }

    public function candidates(string $generationId, array $terms, int $maximum): array
    {
        if (! $this->isReadableGeneration($generationId)) {
            throw new InvariantViolation('Requested persistent query generation is not readable.');
        }
        $this->assertTermsAndMaximum($terms, $maximum);

        $where = [];
        $arguments = [$generationId];
        foreach ($terms as $term) {
            $escaped = method_exists($this->db, 'esc_like') ? $this->db->esc_like($term) : addcslashes($term, '_%\\');
            $where[] = 'payload LIKE %s';
            $arguments[] = '%' . $escaped . '%';
        }
        $arguments[] = $maximum;

        $sql = "SELECT payload, payload_hash
                FROM {$this->documentsTable}
                WHERE generation_id = %s AND (" . implode(' OR ', $where) . ")
                ORDER BY canonical_key ASC
                LIMIT %d";
        $prepared = $this->db->prepare($sql, ...$arguments);
        $rows = $this->db->get_results($prepared, ARRAY_A);

        $documents = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $payload = isset($row['payload']) && is_string($row['payload']) ? $row['payload'] : '';
            $payloadHash = isset($row['payload_hash']) && is_string($row['payload_hash']) ? $row['payload_hash'] : '';
            if ($payload === '' || preg_match('/^[a-f0-9]{64}$/', $payloadHash) !== 1) {
                throw new InvariantViolation('Persistent search row contains invalid payload metadata.');
            }
            if (! hash_equals($payloadHash, hash('sha256', $payload))) {
                throw new InvariantViolation('Persistent search payload integrity verification failed.');
            }

            $document = $this->hydrator->hydrate($payload);
            if (isset($documents[$document->canonicalKey()])) {
                throw new InvariantViolation('Persistent query candidate rows contain duplicate canonical identities.');
            }
            $documents[$document->canonicalKey()] = $document;
        }

        return array_values($documents);
    }

    /** @param list<string> $terms */
    private function assertTermsAndMaximum(array $terms, int $maximum): void
    {
        if (! array_is_list($terms) || $terms === [] || count($terms) > 16 || count($terms) !== count(array_unique($terms))) {
            throw new InvariantViolation('Persistent candidate terms must be a bounded unique list.');
        }
        foreach ($terms as $term) {
            if (! is_string($term) || $term === '' || strlen($term) > 256) {
                throw new InvariantViolation('Persistent candidate terms must contain bounded strings only.');
            }
        }
        if ($maximum < 1 || $maximum > 2000) {
            throw new InvariantViolation('Persistent candidate limits must be between 1 and 2000.');
        }
    }

    private function assertGenerationId(string $generationId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId) !== 1) {
            throw new InvariantViolation('Persistent query generation identifiers are invalid.');
        }
    }
}
