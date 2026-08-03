<?php

declare(strict_types=1);

namespace Sabri\File26\Contracts;

use Sabri\File26\Support\InvariantViolation;

final class ConnectorManifest
{
    private const PRIVACY_CLASSES = ['C1', 'C2', 'C3', 'C4', 'C5'];

    /**
     * @param list<string> $entityTypes
     * @param list<string> $privacyClasses
     */
    public function __construct(
        private readonly string $ownerFile,
        private readonly string $contractVersion,
        private readonly array $entityTypes,
        private readonly array $privacyClasses,
        private readonly string $cursorStrategy,
        private readonly string $changeSource,
        private readonly string $rebuildMethod,
        private readonly string $deletionSemantics,
        private readonly string $healthCheck
    ) {
        if (! preg_match('/^\d{2}$/', $ownerFile)) {
            throw new InvariantViolation('Owner file must be a two-digit canonical file number.');
        }

        if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $contractVersion)) {
            throw new InvariantViolation('Contract version must be semantic-version compatible.');
        }

        if ($entityTypes === []) {
            throw new InvariantViolation('At least one entity type is required.');
        }

        if (count($entityTypes) !== count(array_unique($entityTypes))) {
            throw new InvariantViolation('Entity types must be unique.');
        }

        foreach ($entityTypes as $entityType) {
            if (! is_string($entityType) || ! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $entityType)) {
                throw new InvariantViolation('Entity types must use stable lowercase machine keys.');
            }
        }

        if ($privacyClasses === []) {
            throw new InvariantViolation('At least one privacy class is required.');
        }

        if (count($privacyClasses) !== count(array_unique($privacyClasses))) {
            throw new InvariantViolation('Privacy classes must be unique.');
        }

        foreach ($privacyClasses as $privacyClass) {
            if (! in_array($privacyClass, self::PRIVACY_CLASSES, true)) {
                throw new InvariantViolation('Unknown privacy class: ' . (string) $privacyClass);
            }
        }

        foreach ([$cursorStrategy, $changeSource, $rebuildMethod, $deletionSemantics, $healthCheck] as $value) {
            if (trim($value) === '') {
                throw new InvariantViolation('Connector lifecycle declarations must not be empty.');
            }
        }
    }

    public function ownerFile(): string
    {
        return $this->ownerFile;
    }

    public function contractVersion(): string
    {
        return $this->contractVersion;
    }

    /** @return list<string> */
    public function entityTypes(): array
    {
        return $this->entityTypes;
    }

    /** @return list<string> */
    public function privacyClasses(): array
    {
        return $this->privacyClasses;
    }

    /** @return array<string, string|list<string>> */
    public function toArray(): array
    {
        return [
            'owner_file' => $this->ownerFile,
            'contract_version' => $this->contractVersion,
            'entity_types' => $this->entityTypes,
            'privacy_classes' => $this->privacyClasses,
            'cursor_strategy' => $this->cursorStrategy,
            'change_source' => $this->changeSource,
            'rebuild_method' => $this->rebuildMethod,
            'deletion_semantics' => $this->deletionSemantics,
            'health_check' => $this->healthCheck,
        ];
    }
}
