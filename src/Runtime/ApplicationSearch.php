<?php

declare(strict_types=1);

namespace Sabri\File26\Application;

use Sabri\File26\Ranking\RankedResult;
use Sabri\File26\Support\InvariantViolation;

final class SearchResultPage
{
    public function __construct(private readonly string $generationId,private readonly array $results,private readonly ?string $nextCursor,private readonly bool $candidateTruncated,private readonly string $queryPolicyVersion,private readonly string $rankingPolicyVersion){if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/',$generationId)||!array_is_list($results)||count($results)>50||($nextCursor!==null&&($nextCursor===''||strlen($nextCursor)>1024)))throw new InvariantViolation('Search result page metadata is invalid.');}
    public function rankedResults():array{return $this->results;}public function toArray():array{return ['generation_id'=>$this->generationId,'results'=>array_map(static fn(RankedResult $result):array=>$result->toArray(),$this->results),'next_cursor'=>$this->nextCursor,'candidate_truncated'=>$this->candidateTruncated,'query_policy_version'=>$this->queryPolicyVersion,'ranking_policy_version'=>$this->rankingPolicyVersion];}
}

use Sabri\File26\Query\QueryUnderstandingPipeline;
use Sabri\File26\Ranking\RankingEngine;
use Sabri\File26\Search\ActiveGenerationRepositoryInterface;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\EligibilityEvaluator;
use Sabri\File26\Search\PersistentQuery;
use Sabri\File26\Search\QueryCursorCodec;

final class AdvancedSearchService
{
    private const MAXIMUM_CANDIDATES=2000;
    public function __construct(private readonly ActiveGenerationRepositoryInterface $repository,private readonly QueryCursorCodec $cursorCodec,private readonly QueryUnderstandingPipeline $understanding=new QueryUnderstandingPipeline(),private readonly RankingEngine $ranking=new RankingEngine(),private readonly EligibilityEvaluator $eligibility=new EligibilityEvaluator()){}
    public function search(string $rawQuery,AudienceContext $audience,int $limit=20,?string $cursor=null,array $domains=[],array $locales=[]):SearchResultPage
    {
        $query=new PersistentQuery($rawQuery,$limit,$cursor,$domains,$locales);$plan=$this->understanding->understand($rawQuery);$fingerprint=hash('sha256',implode("\0",[$query->fingerprint(),$plan->policyVersion(),$this->ranking->policyVersion()]));if($cursor===null){$generationId=$this->repository->activeGenerationId();if($generationId===null)throw new InvariantViolation('No active search generation is available.');$offset=0;}else{$decoded=$this->cursorCodec->decode($cursor);if(!hash_equals($fingerprint,$decoded['fingerprint']))throw new InvariantViolation('Search cursor does not belong to the current query, filters and policies.');$generationId=$decoded['generation'];$offset=$decoded['offset'];}if(!$this->repository->isReadableGeneration($generationId))throw new InvariantViolation('The search cursor generation is no longer readable.');[$eligible,$candidateCount]=$this->eligibleForGeneration($generationId,$query,$plan,$audience);$ranked=$this->ranking->rank($eligible,$plan,min(self::MAXIMUM_CANDIDATES,max(1,count($eligible))));if($offset>count($ranked))throw new InvariantViolation('Search cursor offset is outside the current snapshot range.');$page=array_slice($ranked,$offset,$limit);$nextOffset=$offset+count($page);$nextCursor=$nextOffset<count($ranked)?$this->cursorCodec->encode($generationId,$nextOffset,$fingerprint):null;return new SearchResultPage($generationId,$page,$nextCursor,$candidateCount===self::MAXIMUM_CANDIDATES,$plan->policyVersion(),$this->ranking->policyVersion());
    }
    public function eligibleDocuments(string $rawQuery,AudienceContext $audience,array $domains=[],array $locales=[]):array{$query=new PersistentQuery($rawQuery,50,null,$domains,$locales);$plan=$this->understanding->understand($rawQuery);$generationId=$this->repository->activeGenerationId();if($generationId===null||!$this->repository->isReadableGeneration($generationId))throw new InvariantViolation('No readable active search generation is available.');[$eligible]=$this->eligibleForGeneration($generationId,$query,$plan,$audience);return $eligible;}
    private function eligibleForGeneration(string $generationId,PersistentQuery $query,\Sabri\File26\Query\QueryPlan $plan,AudienceContext $audience):array{$terms=array_slice($plan->terms(),0,16);$candidates=$this->repository->candidates($generationId,$terms,self::MAXIMUM_CANDIDATES);$eligible=[];foreach($candidates as $document)if($query->allows($document)&&$this->eligibility->canView($document,$audience))$eligible[]=$document;return[$eligible,count($candidates)];}
    public function understanding():QueryUnderstandingPipeline{return $this->understanding;}
}
