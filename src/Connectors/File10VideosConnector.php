<?php

declare(strict_types=1);

namespace Sabri\File26\Connectors;

use Sabri\File26\Contracts\ConnectorManifest;

final class File10VideosConnector extends AbstractPublicOwnerConnector
{
    public function key(): string
    {
        return 'file-10-videos';
    }

    public function manifest(): ConnectorManifest
    {
        return new ConnectorManifest(
            '10',
            '1.0.0',
            ['recorded-video', 'live-replay'],
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
            'description',
            'channel_name',
            'channel_id',
            'topics',
            'language',
            'published_at',
            'duration_seconds',
            'captions_available',
            'media_type',
            'source_quality',
        ];
    }
}
