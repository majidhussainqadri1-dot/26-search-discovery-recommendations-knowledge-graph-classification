<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class PersistentQuery
{
    /**
     * @param list<string> $domains
     * @param list<string> $locales
     */
    public function __construct(
        private readonly string $query,
        private readonly int $limit = 20,
        private readonly ?string $cursor = null,
        private readonly array $domains = [],
        private readonly array $locales = []
    ) {
        $normalized = $this->normalize($query);
        if ($normalized === '' || $this->length($normalized) > 200 || preg_match('/\p{C}/u', $normalized) === 1) {
            throw new InvariantViolation('Persistent queries must contain 1 to 200 visible characters.');
        }

        if ($limit < 1 || $limit > 50) {
            throw new InvariantViolation('Persistent query limits must be between 1 and 50.');
        }

        if ($cursor !== null && ($cursor === '' || strlen($cursor) > 1024)) {
            throw new InvariantViolation('Persistent query cursors must be null or bounded opaque values.');
        }

        $this->assertUniqueList($domains, 20, '/^[a-z][a-z0-9._-]{1,79}$/', 'domains');
        $this->assertUniqueList($locales, 20, '/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', 'locales');

        $normalizedLocales = array_map([$this, 'normalizeLocale'], $locales);
        if (count($normalizedLocales) !== count(array_unique($normalizedLocales))) {
            throw new InvariantViolation('Persistent query locales must remain unique after normalization.');
        }
    }

    public function text(): string
    {
        return $this->normalize($this->query);
    }

    /** @return list<string> */
    public function terms(): array
    {
        $parts = preg_split('/[\s\p{P}\p{S}]+/u', $this->lower($this->text()), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            throw new InvariantViolation('Persistent query tokenization failed.');
        }

        $terms = [];
        foreach ($parts as $part) {
            if ($this->length($part) > 64) {
                throw new InvariantViolation('Persistent query terms must be at most 64 characters.');
            }
            $terms[$part] = true;
            if (count($terms) === 16) {
                break;
            }
        }

        if ($terms === []) {
            throw new InvariantViolation('Persistent queries require at least one searchable term.');
        }

        return array_keys($terms);
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function cursor(): ?string
    {
        return $this->cursor;
    }

    public function allows(SearchDocument $document): bool
    {
        $data = $document->toArray();
        if ($this->domains !== [] && ! in_array($data['canonical_domain'], $this->domains, true)) {
            return false;
        }

        $locale = $this->normalizeLocale((string) $data['locale']);
        $allowedLocales = array_map([$this, 'normalizeLocale'], $this->locales);

        return $allowedLocales === [] || in_array($locale, $allowedLocales, true);
    }

    public function fingerprint(): string
    {
        $domains = $this->domains;
        $locales = array_map([$this, 'normalizeLocale'], $this->locales);
        sort($domains);
        sort($locales);

        return hash('sha256', json_encode([
            'query' => $this->lower($this->text()),
            'limit' => $this->limit,
            'domains' => $domains,
            'locales' => $locales,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $values */
    private function assertUniqueList(array $values, int $maximum, string $pattern, string $label): void
    {
        if (! array_is_list($values) || count($values) > $maximum || count($values) !== count(array_unique($values))) {
            throw new InvariantViolation('Persistent query ' . $label . ' must be a bounded unique list.');
        }

        foreach ($values as $value) {
            if (! is_string($value) || preg_match($pattern, $value) !== 1) {
                throw new InvariantViolation('Persistent query ' . $label . ' contain an invalid value.');
            }
        }
    }

    private function normalizeLocale(string $locale): string
    {
        return strtolower(str_replace('_', '-', $locale));
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
