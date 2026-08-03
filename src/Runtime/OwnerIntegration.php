<?php

declare(strict_types=1);

namespace Sabri\File26\Adapters;

use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Support\InvariantViolation;

final class WordPressFilterBatchProvider implements SourceBatchProviderInterface
{
    public function __construct(private readonly string $filterName, private readonly string $ownerKey)
    {
        if (! preg_match('/^[a-z][a-z0-9_]{2,127}$/', $filterName) || ! preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $ownerKey)) {
            throw new InvariantViolation('Owner adapter filter or owner key is invalid.');
        }
    }

    public function fetch(?string $cursor, int $limit): array
    {
        if ($limit < 1 || $limit > 200) { throw new InvariantViolation('Owner adapter page limit is invalid.'); }
        if ($cursor !== null && (trim($cursor) === '' || strlen($cursor) > 500)) { throw new InvariantViolation('Owner adapter cursor is invalid.'); }
        if (! function_exists('apply_filters')) { throw new InvariantViolation('WordPress owner provider is unavailable.'); }
        $page = apply_filters($this->filterName, null, $cursor, $limit, $this->ownerKey);
        if (! is_array($page)) { throw new InvariantViolation('Owner module did not return a source batch page.'); }
        $expected = ['records','tombstones','next_cursor','complete'];
        if (array_keys($page) !== $expected || ! is_array($page['records']) || ! array_is_list($page['records']) || ! is_array($page['tombstones']) || ! array_is_list($page['tombstones']) || ! is_bool($page['complete']) || ($page['next_cursor'] !== null && ! is_string($page['next_cursor']))) {
            throw new InvariantViolation('Owner source batch page has an invalid canonical shape.');
        }
        if (count($page['records']) + count($page['tombstones']) > $limit) { throw new InvariantViolation('Owner source batch exceeded its requested page limit.'); }
        if ($page['complete'] && $page['next_cursor'] !== null) { throw new InvariantViolation('Complete owner page cannot contain a continuation cursor.'); }
        if (! $page['complete'] && ($page['next_cursor'] === null || trim($page['next_cursor']) === '' || $page['next_cursor'] === $cursor)) { throw new InvariantViolation('Continuing owner page requires a distinct bounded cursor.'); }
        return $page;
    }
}

namespace Sabri\File26\Connectors;

use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Contracts\ConnectorManifest;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Support\InvariantViolation;

final class GenericPublicOwnerConnector implements ConnectorInterface
{
    public function __construct(private readonly ConnectorManifest $manifest, private readonly SourceBatchProviderInterface $provider, private readonly array $allowedFields)
    {
        if (! $manifest->supportsMode(ConnectorManifest::MODE_FULL)) throw new InvariantViolation('Public owner connector must support full rebuild mode.');
        if (! array_is_list($allowedFields) || $allowedFields === [] || count($allowedFields) > 100 || count($allowedFields) !== count(array_unique($allowedFields))) throw new InvariantViolation('Public owner connector field allowlist is invalid.');
        foreach ($allowedFields as $field) if (! is_string($field) || ! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $field)) throw new InvariantViolation('Public owner connector field name is invalid.');
    }
    public function manifest(): ConnectorManifest { return $this->manifest; }
    public function fetch(?string $cursor, int $limit): ConnectorBatch
    {
        $page=$this->provider->fetch($cursor,$limit);$documents=[];$tombstones=[];$keys=[];
        foreach($page['records'] as $record){if(!is_array($record))throw new InvariantViolation('Owner connector record must be an associative array.');$document=$this->mapRecord($record);if(isset($keys[$document->canonicalKey()]))throw new InvariantViolation('Owner connector returned a duplicate canonical identity.');$keys[$document->canonicalKey()]=true;$documents[]=$document;}
        foreach($page['tombstones'] as $record){if(!is_array($record))throw new InvariantViolation('Owner connector tombstone must be an associative array.');$tombstone=$this->mapTombstone($record);if(isset($keys[$tombstone->canonicalKey()]))throw new InvariantViolation('Owner connector returned a duplicate document/tombstone identity.');$keys[$tombstone->canonicalKey()]=true;$tombstones[]=$tombstone;}
        return new ConnectorBatch($documents,$tombstones,$page['next_cursor'],$page['complete']);
    }
    private function mapRecord(array $record): SearchDocument
    {
        foreach(['canonical_key','owner_key','object_version','locale','state','destination_url','last_source_event_at','fields'] as $key) if(!array_key_exists($key,$record))throw new InvariantViolation('Owner connector record is missing required field: '.$key);
        if(!is_string($record['canonical_key'])||!str_starts_with($record['canonical_key'],$this->manifest->ownerKey().':')||!is_string($record['owner_key'])||$record['owner_key']!==$this->manifest->ownerKey()||!is_string($record['object_version'])||!is_string($record['locale'])||!is_string($record['state'])||!is_string($record['destination_url'])||!is_string($record['last_source_event_at'])||!is_array($record['fields']))throw new InvariantViolation('Owner connector record types or ownership are invalid.');
        $this->assertCanonicalHost($record['destination_url']);$fields=[];foreach($record['fields'] as $field=>$value){if(!is_string($field)||!in_array($field,$this->allowedFields,true))throw new InvariantViolation('Owner connector exposed a field outside its public allowlist.');$this->assertSafePublicValue($value);$fields[$field]=$value;}
        if(!isset($fields['title'])||!is_string($fields['title'])||trim($fields['title'])==='')throw new InvariantViolation('Public owner record requires a title.');
        return new SearchDocument($record['canonical_key'],$record['owner_key'],$record['object_version'],$record['locale'],$record['state'],$record['destination_url'],$fields,VisibilityEnvelope::public(),new \DateTimeImmutable($record['last_source_event_at']));
    }
    private function mapTombstone(array $record):IndexTombstone
    {
        foreach(['canonical_key','owner_key','object_version','reason','occurred_at'] as $key)if(!array_key_exists($key,$record))throw new InvariantViolation('Owner tombstone is missing required field: '.$key);
        if(!is_string($record['canonical_key'])||!str_starts_with($record['canonical_key'],$this->manifest->ownerKey().':')||!is_string($record['owner_key'])||$record['owner_key']!==$this->manifest->ownerKey()||!is_string($record['object_version'])||!is_string($record['reason'])||!is_string($record['occurred_at']))throw new InvariantViolation('Owner tombstone types or ownership are invalid.');
        return new IndexTombstone($record['canonical_key'],$record['owner_key'],$record['object_version'],$record['reason'],new \DateTimeImmutable($record['occurred_at']));
    }
    private function assertCanonicalHost(string $url):void{$parts=parse_url($url);if(!is_array($parts)||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||!isset($parts['host']))throw new InvariantViolation('Destination URL must be credential-free HTTPS.');$host=strtolower(rtrim((string)$parts['host'],'.'));if($host!==SABRI_FILE26_CANONICAL_HOST&&!str_ends_with($host,'.'.SABRI_FILE26_CANONICAL_HOST))throw new InvariantViolation('Destination URL must remain on the canonical Sabri host.');}
    private function assertSafePublicValue(mixed $value):void{if(is_string($value)){if(strlen($value)>20000)throw new InvariantViolation('Public owner field value is too large.');return;}if(is_int($value)||is_float($value)||is_bool($value)||$value===null)return;if(is_array($value)){if(!array_is_list($value)||count($value)>100)throw new InvariantViolation('Public owner list field is invalid.');foreach($value as $item)if(!is_string($item)&&!is_int($item)&&!is_float($item)&&!is_bool($item)&&$item!==null)throw new InvariantViolation('Public owner list item is not a safe scalar.');return;}throw new InvariantViolation('Public owner field value is not a safe public scalar.');}
}

namespace Sabri\File26\Registry;

use Sabri\File26\Adapters\WordPressFilterBatchProvider;
use Sabri\File26\Connectors\GenericPublicOwnerConnector;
use Sabri\File26\Contracts\ConnectorManifest;

final class DefaultConnectorRegistrar
{
    public function registerInto(ConnectorRegistry $registry):void
    {
        foreach($this->definitions() as $definition){[$key,$owner,$filter,$fields]=$definition;$registry->register(new GenericPublicOwnerConnector(new ConnectorManifest($key,$owner,'1.0.0','1.0.0','1.0.0',[ConnectorManifest::MODE_FULL,ConnectorManifest::MODE_PARTIAL,ConnectorManifest::MODE_DELTA],200),new WordPressFilterBatchProvider($filter,$owner),$fields));}
    }
    private function definitions():array
    {
        return [
            ['file21.publications','file21','sabri_file21_public_search_batch',['title','excerpt','content_type','creator_id','topics','authority_score','quality_score','popularity_score','trending_score']],
            ['file09.doctors','file09','sabri_file09_public_doctor_search_batch',['title','clinic_name','country','languages','specialization','creator_id','topics','authority_score','quality_score']],
            ['file08.academy','file08','sabri_file08_public_learning_search_batch',['title','excerpt','content_type','creator_id','topics','authority_score','quality_score']],
            ['file10.videos','file10','sabri_file10_public_video_search_batch',['title','summary','content_type','creator_id','topics','authority_score','quality_score','popularity_score','trending_score']],
            ['file11.reels','file11','sabri_file11_public_reel_search_batch',['title','summary','content_type','creator_id','topics','authority_score','quality_score','popularity_score','trending_score']],
            ['file12.pdf','file12','sabri_file12_public_pdf_search_batch',['title','summary','content_type','creator_id','topics','authority_score','quality_score']],
            ['file14.clinics','file14','sabri_file14_public_clinic_search_batch',['title','clinic_name','country','languages','specialization','creator_id','topics','authority_score','quality_score']],
            ['file15.radar','file15','sabri_file15_public_radar_search_batch',['title','summary','content_type','creator_id','topics','authority_score','quality_score','trending_score']],
            ['file18.marketplace','file18','sabri_file18_public_market_search_batch',['title','summary','content_type','creator_id','topics','authority_score','quality_score','popularity_score']],
        ];
    }
}
