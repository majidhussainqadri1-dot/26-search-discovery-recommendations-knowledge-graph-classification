<?php

declare(strict_types=1);

namespace Sabri\File26\Registry;

use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Support\InvariantViolation;

final class ConnectorRegistry
{
    /** @var array<string, ConnectorInterface> */
    private array $connectors = [];

    public function register(ConnectorInterface $connector): void
    {
        $key = $connector->key();
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $key)) {
            throw new InvariantViolation('Connector key must be a stable lowercase machine key.');
        }

        if (isset($this->connectors[$key])) {
            throw new InvariantViolation('Duplicate connector registration is forbidden: ' . $key);
        }

        // Materialize the manifest during registration so invalid contracts fail closed.
        $connector->manifest()->toArray();
        $this->connectors[$key] = $connector;
    }

    public function has(string $key): bool
    {
        return isset($this->connectors[$key]);
    }

    public function get(string $key): ConnectorInterface
    {
        if (! isset($this->connectors[$key])) {
            throw new InvariantViolation('Unknown connector: ' . $key);
        }

        return $this->connectors[$key];
    }

    /** @return array<string, ConnectorInterface> */
    public function all(): array
    {
        ksort($this->connectors);

        return $this->connectors;
    }

    /** @return array<string, array<string, mixed>> */
    public function publicSummary(): array
    {
        $summary = [];
        foreach ($this->all() as $key => $connector) {
            $summary[$key] = [
                'manifest' => $connector->manifest()->toArray(),
                'health' => $this->sanitizeHealth($connector->health()),
            ];
        }

        return $summary;
    }

    /**
     * @param array<string, bool|int|float|string|null> $health
     * @return array<string, bool|int|float|string|null>
     */
    private function sanitizeHealth(array $health): array
    {
        $allowed = ['status', 'healthy', 'latency_ms', 'last_success_at', 'contract_version', 'message_code'];
        $sanitized = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $health)) {
                continue;
            }

            $value = $health[$key];
            if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
