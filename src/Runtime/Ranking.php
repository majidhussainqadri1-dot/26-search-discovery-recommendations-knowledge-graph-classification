<?php

declare(strict_types=1);

namespace Sabri\File26\Ranking;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Query\QueryPlan;
use Sabri\File26\Query\UnicodeNormalizer;
use Sabri\File26\Support\InvariantViolation;

final class RankingPolicy
{
    public function __construct(private readonly string $version,private readonly array $weights,private readonly int $maximumPerCreator=3,private readonly int $maximumPerDomain=5,private readonly int $maximumContentTypePercent=60,private readonly DateTimeImmutable $effectiveAt=new DateTimeImmutable('2026-08-04T00:00:00+00:00')){if(!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$version))throw new InvariantViolation('Ranking policy version must be semantic.');foreach(['exact_title','title_term','body_term','authority','quality','freshness','popularity','corrected_penalty'] as $key)if(!array_key_exists($key,$weights)||!is_int($weights[$key])||abs($weights[$key])>1000)throw new InvariantViolation('Ranking policy weights are incomplete or unsafe.');if($maximumPerCreator<1||$maximumPerCreator>20||$maximumPerDomain<1||$maximumPerDomain>50||$maximumContentTypePercent<20||$maximumContentTypePercent>100)throw new InvariantViolation('Ranking concentration limits are outside safe bounds.');}
    public static function productionDefault():self{return new self('1.0.0',['exact_title'=>120,'title_term'=>30,'body_term'=>8,'authority'=>4,'quality'=>3,'freshness'=>2,'popularity'=>1,'corrected_penalty'=>-15]);}public function version():string{return $this->version;}public function weight(string $feature):int{return $this->weights[$feature]??0;}public function maximumPerCreator():int{return $this->maximumPerCreator;}public function maximumPerDomain():int{return $this->maximumPerDomain;}public function maximumContentTypePercent():int{return $this->maximumContentTypePercent;}public function effectiveAt():DateTimeImmutable{return $this->effectiveAt;}
}

final class RankedResult
{
    public function __construct(private readonly SearchDocument $document,private readonly int $score,private readonly array $explanations,private readonly string $policyVersion){if($score < -1000000||$score>1000000||!array_is_list($explanations)||count($explanations)>12)throw new InvariantViolation('Ranked result evidence is outside bounded limits.');}
    public function document():SearchDocument{return $this->document;}public function score():int{return $this->score;}public function explanations():array{return $this->explanations;}public function policyVersion():string{return $this->policyVersion;}public function toArray():array{return ['document'=>$this->document->toArray(),'score'=>$this->score,'explanations'=>$this->explanations,'policy_version'=>$this->policyVersion,'paid'=>false,'click_visibility_recheck_required'=>true];}
}

final class RankingEngine
{
    public function __construct(private readonly RankingPolicy $policy=new RankingPolicy('1.0.0',['exact_title'=>120,'title_term'=>30,'body_term'=>8,'authority'=>4,'quality'=>3,'freshness'=>2,'popularity'=>1,'corrected_penalty'=>-15]),private readonly UnicodeNormalizer $normalizer=new UnicodeNormalizer()){}
    public function policyVersion():string{return $this->policy->version();}
    public function rank(array $documents,QueryPlan $plan,int $limit=20):array
    {
        if(!array_is_list($documents)||count($documents)>5000||$limit<1||$limit>2000)throw new InvariantViolation('Ranking input must be a bounded document list.');$ranked=[];foreach($documents as $document){if(!$document instanceof SearchDocument||in_array($document->state(),['suspended','retracted'],true))continue;[$score,$evidence]=$this->score($document,$plan);if($score>0)$ranked[]=new RankedResult($document,$score,$evidence,$this->policy->version());}usort($ranked,static fn(RankedResult $a,RankedResult $b):int=>($b->score()<=>$a->score())?:($a->document()->canonicalKey()<=>$b->document()->canonicalKey()));return $this->applyDiversity($ranked,$limit);
    }
    private function score(SearchDocument $document,QueryPlan $plan):array
    {
        $fields=$document->fields();$title=$this->scalarText($fields['title']??'');$normalizedTitle=$this->normalizer->normalizeForSearch($title);$allText=[];foreach($fields as $value){if(is_scalar($value))$allText[]=(string)$value;elseif(is_array($value))foreach($value as $item)if(is_scalar($item))$allText[]=(string)$item;}$haystack=$this->normalizer->normalizeForSearch(implode(' ',$allText));$score=0;$evidence=[];if($normalizedTitle!==''&&$normalizedTitle===$plan->normalizedQuery()){$score+=$this->policy->weight('exact_title');$evidence[]='exact-title-match';}foreach($plan->terms() as $term){if($normalizedTitle!==''&&str_contains($normalizedTitle,$term)){$score+=$this->policy->weight('title_term');$evidence[]='title-term-match';}if(str_contains($haystack,$term)){$score+=$this->policy->weight('body_term');$evidence[]='content-term-match';}}$authority=$this->boundedSignal($fields['authority_score']??0);$quality=$this->boundedSignal($fields['quality_score']??0);$popularity=min(25,$this->boundedSignal($fields['popularity_score']??0));$freshness=$this->freshnessSignal($document->lastSourceEventAt());$score+=intdiv($authority*$this->policy->weight('authority'),10)+intdiv($quality*$this->policy->weight('quality'),10)+intdiv($popularity*$this->policy->weight('popularity'),10)+$freshness*$this->policy->weight('freshness');if($authority>=70)$evidence[]='authoritative-source';if($quality>=70)$evidence[]='reviewed-quality';if($freshness>=5)$evidence[]='recent-source-update';if($document->state()==='corrected'){$score+=$this->policy->weight('corrected_penalty');$evidence[]='corrected-record';}return[$score,array_values(array_unique(array_slice($evidence,0,12)))];
    }
    private function applyDiversity(array $ranked,int $limit):array
    {
        $accepted=[];$creatorCounts=[];$domainCounts=[];$typeCounts=[];$deferred=[];foreach($ranked as $result){$fields=$result->document()->fields();$creator=$this->scalarText($fields['creator_id']??'');if($creator==='')$creator='unknown:'.$result->document()->canonicalKey();$domain=(string)$result->document()->toArray()['canonical_domain'];$type=$this->scalarText($fields['content_type']??$domain);if(($creatorCounts[$creator]??0)>=$this->policy->maximumPerCreator()||($domainCounts[$domain]??0)>=$this->policy->maximumPerDomain())continue;$prospective=count($accepted)+1;$typeProspective=($typeCounts[$type]??0)+1;if($prospective>=5&&(int)floor(($typeProspective/$prospective)*100)>$this->policy->maximumContentTypePercent()){$deferred[]=$result;continue;}$accepted[]=$result;$creatorCounts[$creator]=($creatorCounts[$creator]??0)+1;$domainCounts[$domain]=($domainCounts[$domain]??0)+1;$typeCounts[$type]=($typeCounts[$type]??0)+1;if(count($accepted)>=$limit)return $accepted;}foreach($deferred as $result){$fields=$result->document()->fields();$creator=$this->scalarText($fields['creator_id']??'');if($creator==='')$creator='unknown:'.$result->document()->canonicalKey();$domain=(string)$result->document()->toArray()['canonical_domain'];if(($creatorCounts[$creator]??0)>=$this->policy->maximumPerCreator()||($domainCounts[$domain]??0)>=$this->policy->maximumPerDomain())continue;$accepted[]=$result;$creatorCounts[$creator]=($creatorCounts[$creator]??0)+1;$domainCounts[$domain]=($domainCounts[$domain]??0)+1;if(count($accepted)>=$limit)break;}return $accepted;
    }
    private function boundedSignal(mixed $value):int{if(!is_int($value)&&!is_float($value)&&!is_string($value))return 0;if(is_string($value)&&!is_numeric($value))return 0;return max(0,min(100,(int)round((float)$value)));}private function freshnessSignal(DateTimeImmutable $time):int{$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$days=max(0,(int)floor(($now->getTimestamp()-$time->getTimestamp())/86400));return max(0,10-intdiv($days,30));}private function scalarText(mixed $value):string{return is_string($value)||is_int($value)||is_float($value)?trim((string)$value):'';}
}
