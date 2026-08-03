<?php

declare(strict_types=1);

namespace Sabri\File26\Connectors;

use Sabri\File26\Contracts\ConnectorManifest;

final class File21PublicationsConnector extends AbstractPublicOwnerConnector
{
    public function key(): string
    {
        return 'file-21-publications';
    }

    public function manifest(): ConnectorManifest
    {
        return new ConnectorManifest(
            '21',
            '1.0.0',
            ['publication', 'editorial-news'],
            ['C1'],
            'opaque-cursor',
            'approved-owner-read-contract',
            'full-rebuild-and-owner-delta',
            'restrict-then-tombstone-then-purge',
            'bounded-owner-health-contract'
        );
    }

    protected function canonicalDomain(): string
    {
        return $this->key();
    }

    protected function allowedFieldKeys(): array
    {
        return [
            'title',
            'excerpt',
            'body_text',
            'author_name',
            'author_id',
            'topics',
            'language',
            'published_at',
            'content_type',
            'source_quality',
            'is_correction',
            'correction_of',
        ];
    }
}
