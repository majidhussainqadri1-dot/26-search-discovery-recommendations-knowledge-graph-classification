<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Support\InvariantViolation;

final class QueryCursorCodec
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvariantViolation('Query cursor secrets must contain at least 32 bytes.');
        }
    }

    public function encode(string $generationId, int $offset, string $fingerprint): string
    {
        $this->assertGenerationId($generationId);
        if ($offset < 0 || $offset > 100000) {
            throw new InvariantViolation('Query cursor offsets must be between 0 and 100000.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1) {
            throw new InvariantViolation('Query cursor fingerprints must be SHA-256 hex values.');
        }

        $payload = json_encode([
            'generation' => $generationId,
            'offset' => $offset,
            'fingerprint' => $fingerprint,
        ], JSON_THROW_ON_ERROR);
        $encoded = $this->base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $encoded, $this->secret);

        return $encoded . '.' . $signature;
    }

    /** @return array{generation:string,offset:int,fingerprint:string} */
    public function decode(string $cursor): array
    {
        if ($cursor === '' || strlen($cursor) > 1024 || substr_count($cursor, '.') !== 1) {
            throw new InvariantViolation('Query cursor format is invalid.');
        }

        [$encoded, $signature] = explode('.', $cursor, 2);
        if (preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            throw new InvariantViolation('Query cursor signature format is invalid.');
        }

        $expected = hash_hmac('sha256', $encoded, $this->secret);
        if (! hash_equals($expected, $signature)) {
            throw new InvariantViolation('Query cursor signature verification failed.');
        }

        $decoded = $this->base64UrlDecode($encoded);
        try {
            $data = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            unset($exception);
            throw new InvariantViolation('Query cursor payload is invalid JSON.');
        }

        if (! is_array($data) || array_keys($data) !== ['generation', 'offset', 'fingerprint']) {
            throw new InvariantViolation('Query cursor payload shape is invalid.');
        }
        if (! is_string($data['generation']) || ! is_int($data['offset']) || ! is_string($data['fingerprint'])) {
            throw new InvariantViolation('Query cursor payload types are invalid.');
        }

        $this->assertGenerationId($data['generation']);
        if ($data['offset'] < 0 || $data['offset'] > 100000) {
            throw new InvariantViolation('Query cursor offset is outside the supported range.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $data['fingerprint']) !== 1) {
            throw new InvariantViolation('Query cursor fingerprint is invalid.');
        }

        return [
            'generation' => $data['generation'],
            'offset' => $data['offset'],
            'fingerprint' => $data['fingerprint'],
        ];
    }

    private function assertGenerationId(string $generationId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId) !== 1) {
            throw new InvariantViolation('Query cursor generation identifiers are invalid.');
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new InvariantViolation('Query cursor encoding is invalid.');
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if (! is_string($decoded)) {
            throw new InvariantViolation('Query cursor decoding failed.');
        }

        return $decoded;
    }
}
