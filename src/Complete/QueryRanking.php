<?php

declare(strict_types=1);

namespace Sabri\File26\Query;

use Sabri\File26\Support\InvariantViolation;

final class UnicodeNormalizer
{
    private const DIGITS=['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9'];
    private const PRESENTATION=['ي'=>'ی','ى'=>'ی','ئ'=>'ی','ك'=>'ک','ۀ'=>'ہ','ة'=>'ہ','ھ'=>'ہ','ؤ'=>'و'];

    public function normalizeForSearch(string $value): string
    {
        if(strlen($value)>20000||preg_match('/\p{C}/u',$value)===1)throw new InvariantViolation('Text normalization input is invalid or too large.');
        if(class_exists('Normalizer')){$n=\Normalizer::normalize($value,\Normalizer::FORM_KC);if(is_string($n))$value=$n;}
        $value=strtr(strtr($value,self::DIGITS),self::PRESENTATION);
        $value=str_replace(["\u{0640}","\u{200C}","\u{200D}","\u{FEFF}"],' ',$value);
        $value=preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u','',$value)??'';
        $value=preg_replace('/[\p{P}\p{S}]+/u',' ',$value)??'';
        $value=preg_replace('/\s+/u',' ',trim($value))??'';
        return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
    }

    /** @return list<string> */
    public function tokens(string $value,int $maximum=16):array
    {
        if($maximum<1||$maximum>64)throw new InvariantViolation('Token maximum must be between 1 and 64.');
        $normalized=$this->normalizeForSearch($value);if($normalized==='')return [];
        $parts=preg_split('/\s+/u',$normalized,-1,PREG_SPLIT_NO_EMPTY);if(!is_array($parts))throw new InvariantViolation('Unicode tokenization failed.');
        $tokens=[];foreach($parts as $part){$length=function_exists('mb_strlen')?mb_strlen($part,'UTF-8'):strlen($part);if($length>64)throw new InvariantViolation('Normalized query term is too long.');$tokens[$part]=true;if(count($tokens)>=$maximum)break;}
        return array_keys($tokens);
    }
}

final class SensitiveQueryClassifier
{
    public const PUBLIC='public',CLINICAL='clinical',PII='pii',ABUSIVE='abusive';
    private array $clinical=['prescription','patient','case record','medical record','diagnosis','نسخہ','مریض','کیس ریکارڈ','تشخیص','رپورٹ'];
    private array $abusive=['porn','suicide method','قتل کا طریقہ'];
    public function classify(string $raw,UnicodeNormalizer $normalizer):string
    {
        $normalized=$normalizer->normalizeForSearch($raw);
        if(filter_var(trim($raw),FILTER_VALIDATE_EMAIL)||preg_match('/(?:\+?\d[\d\s().-]{7,}\d)/u',$raw)===1||preg_match('/\b\d{5}-?\d{7}-?\d\b/u',$raw)===1||preg_match('/\b[A-Z]{1,3}\d{6,12}\b/i',$raw)===1)return self::PII;
        foreach($this->abusive as $term)if(str_contains($normalized,$normalizer->normalizeForSearch($term)))return self::ABUSIVE;
        foreach($this->clinical as $term)if(str_contains($normalized,$normalizer->normalizeForSearch($term)))return self::CLINICAL;
        return self::PUBLIC;
    }
}

final class TransliterationService
{
    public const POLICY_VERSION='1.0.0';
    /** @var array<string,list<string>> */ private array $aliases;
    public function __construct(?array $aliases=null)
    {
        $this->aliases=$aliases??['jigar'=>['جگر'],'sozish'=>['سوزش'],'jigar ki sozish'=>['جگر کی سوزش'],'ilaaj'=>['علاج'],'ilaj'=>['علاج'],'dawa'=>['دوا'],'dawai'=>['دوائی'],'marz'=>['مرض'],'doctor'=>['ڈاکٹر'],'kitab'=>['کتاب'],'lesson'=>['سبق'],'remedy'=>['ریمیڈی','دوا'],'homeopathy'=>['ہومیوپیتھی'],'homeopathic'=>['ہومیوپیتھک']];
        foreach($this->aliases as $source=>$values){if(!is_string($source)||trim($source)===''||!array_is_list($values)||count($values)>8)throw new InvariantViolation('Transliteration policy is invalid.');foreach($values as $v)if(!is_string($v)||trim($v)===''||strlen($v)>200)throw new InvariantViolation('Transliteration alias is invalid.');}
    }
    /** @return list<string> */ public function expand(string $query,UnicodeNormalizer $normalizer):array
    {
        $result=[$query=>true];foreach($this->aliases as $source=>$aliases){$source=$normalizer->normalizeForSearch($source);if($source===$query||str_contains($query,$source)){foreach($aliases as $alias){$candidate=$normalizer->normalizeForSearch($source===$query?$alias:str_replace($source,$alias,$query));if($candidate!=='')$result[$candidate]=true;if(count($result)>=8)break 2;}}}return array_keys($result);
    }
}

final class SynonymRegistry
{
    public const POLICY_VERSION='1.0.0';
    /** @var array<string,list<string>> */ private array $approved;
    /** @var array<string,true> */ private array $prohibited=[];
    public function __construct(?array $approved=null,array $prohibitedPairs=[])
    {
        $this->approved=$approved??['physician'=>['doctor'],'doctor'=>['physician','معالج'],'book'=>['کتاب'],'lesson'=>['سبق','lecture'],'video'=>['ویڈیو'],'clinic'=>['کلینک'],'research'=>['تحقیق'],'homeopathy'=>['ہومیوپیتھی']];
        foreach($prohibitedPairs as $pair){if(!is_array($pair)||count($pair)!==2||!is_string($pair[0])||!is_string($pair[1]))throw new InvariantViolation('Prohibited synonym pair is invalid.');$this->prohibited[$this->pairKey($pair[0],$pair[1])]=true;}
    }
    /** @param list<string> $terms @return list<string> */ public function expandTerms(array $terms,UnicodeNormalizer $normalizer):array
    {
        $out=[];foreach($terms as $term){$term=$normalizer->normalizeForSearch($term);if($term==='')continue;$out[$term]=true;foreach($this->approved[$term]??[] as $candidate){$candidate=$normalizer->normalizeForSearch($candidate);if($candidate!==''&&!isset($this->prohibited[$this->pairKey($term,$candidate)]))$out[$candidate]=true;if(count($out)>=32)break 2;}}return array_keys($out);
    }
    private function pairKey(string $a,string $b):string{$pair=[trim($a),trim($b)];sort($pair,SORT_STRING);return hash('sha256',implode("\0",$pair));}
}

final class QueryPlan
{
    /** @param list<string> $terms @param list<string> $expandedQueries */
    public function __construct(private readonly string $normalizedQuery,private readonly array $terms,private readonly array $expandedQueries,private readonly string $sensitivity,private readonly string $policyVersion)
    {
        if($normalizedQuery===''||strlen($normalizedQuery)>1000||!array_is_list($terms)||$terms===[]||count($terms)>32||count($terms)!==count(array_unique($terms)))throw new InvariantViolation('Query plan terms are invalid.');
        if(!array_is_list($expandedQueries)||$expandedQueries===[]||count($expandedQueries)>8||count($expandedQueries)!==count(array_unique($expandedQueries)))throw new InvariantViolation('Query plan expansions are invalid.');
        if(!in_array($sensitivity,[SensitiveQueryClassifier::PUBLIC,SensitiveQueryClassifier::CLINICAL,SensitiveQueryClassifier::PII,SensitiveQueryClassifier::ABUSIVE],true)||preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$policyVersion)!==1)throw new InvariantViolation('Query plan policy metadata is invalid.');
    }
    public function normalizedQuery():string{return $this->normalizedQuery;} public function terms():array{return $this->terms;} public function expandedQueries():array{return $this->expandedQueries;} public function sensitivity():string{return $this->sensitivity;} public function policyVersion():string{return $this->policyVersion;} public function allowsRawTelemetry():bool{return $this->sensitivity===SensitiveQueryClassifier::PUBLIC;}
}

final class QueryUnderstandingPipeline
{
    public const POLICY_VERSION='1.0.0';
    public function __construct(private readonly UnicodeNormalizer $normalizer=new UnicodeNormalizer(),private readonly TransliterationService $transliteration=new TransliterationService(),private readonly SynonymRegistry $synonyms=new SynonymRegistry(),private readonly SensitiveQueryClassifier $classifier=new SensitiveQueryClassifier()){}
    public function understand(string $raw):QueryPlan
    {
        if(trim($raw)===''||strlen($raw)>2000)throw new InvariantViolation('Search query is empty or too large.');$normalized=$this->normalizer->normalizeForSearch($raw);$base=$this->normalizer->tokens($normalized,16);if($base===[])throw new InvariantViolation('Search query produced no terms.');$terms=$this->synonyms->expandTerms($base,$this->normalizer);$expanded=$this->transliteration->expand($normalized,$this->normalizer);foreach($expanded as $query)foreach($this->normalizer->tokens($query,16) as $term)$terms[]=$term;$terms=array_slice(array_values(array_unique($terms)),0,32);return new QueryPlan($normalized,$terms,$expanded,$this->classifier->classify($raw,$this->normalizer),self::POLICY_VERSION);
    }
    public function normalizer():UnicodeNormalizer{return $this->normalizer;}
}

namespace Sabri\File26\Ranking;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Query\QueryPlan;
use Sabri\File26\Query\UnicodeNormalizer;
use Sabri\File26\Support\InvariantViolation;

final class RankingPolicy
{
    /** @param array<string,int> $weights */
    public function __construct(private readonly string $version,private readonly array $weights,private readonly int $maximumPerCreator=3,private readonly int $maximumPerDomain=5,private readonly int $maximumContentTypePercent=60,private readonly DateTimeImmutable $effectiveAt=new DateTimeImmutable('2026-08-04T00:00:00+00:00'))
    {
        if(preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$version)!==1)throw new InvariantViolation('Ranking policy version is invalid.');foreach(['exact_title','title_term','body_term','authority','quality','freshness','popularity','corrected_penalty'] as $key)if(!array_key_exists($key,$weights)||!is_int($weights[$key])||abs($weights[$key])>1000)throw new InvariantViolation('Ranking policy weights are invalid.');if($maximumPerCreator<1||$maximumPerCreator>20||$maximumPerDomain<1||$maximumPerDomain>50||$maximumContentTypePercent<20||$maximumContentTypePercent>100)throw new InvariantViolation('Ranking concentration limits are invalid.');
    }
    public static function productionDefault():self{return new self('1.0.0',['exact_title'=>120,'title_term'=>30,'body_term'=>8,'authority'=>4,'quality'=>3,'freshness'=>2,'popularity'=>1,'corrected_penalty'=>-15]);}
    public function version():string{return $this->version;} public function weight(string $key):int{return $this->weights[$key]??0;} public function maximumPerCreator():int{return $this->maximumPerCreator;} public function maximumPerDomain():int{return $this->maximumPerDomain;} public function maximumContentTypePercent():int{return $this->maximumContentTypePercent;} public function effectiveAt():DateTimeImmutable{return $this->effectiveAt;}
}

final class RankedResult
{
    /** @param list<string> $explanations */
    public function __construct(private readonly SearchDocument $document,private readonly int $score,private readonly array $explanations,private readonly string $policyVersion)
    {if($score < -1000000||$score>1000000||!array_is_list($explanations)||count($explanations)>12||preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$policyVersion)!==1)throw new InvariantViolation('Ranked result is invalid.');}
    public function document():SearchDocument{return $this->document;} public function score():int{return $this->score;} public function explanations():array{return $this->explanations;} public function policyVersion():string{return $this->policyVersion;}
    public function toArray():array{return ['document'=>$this->document->toArray(),'score'=>$this->score,'explanations'=>$this->explanations,'policy_version'=>$this->policyVersion,'paid'=>false,'click_visibility_recheck_required'=>true];}
}

final class RankingEngine
{
    public function __construct(private readonly RankingPolicy $policy=new RankingPolicy('1.0.0',['exact_title'=>120,'title_term'=>30,'body_term'=>8,'authority'=>4,'quality'=>3,'freshness'=>2,'popularity'=>1,'corrected_penalty'=>-15]),private readonly UnicodeNormalizer $normalizer=new UnicodeNormalizer()){}
    public function policyVersion():string{return $this->policy->version();}
    /** @param list<SearchDocument> $documents @return list<RankedResult> */
    public function rank(array $documents,QueryPlan $plan,int $limit=20):array
    {
        if(!array_is_list($documents)||count($documents)>5000||$limit<1||$limit>2000)throw new InvariantViolation('Ranking input is invalid.');$ranked=[];foreach($documents as $document){if(!$document instanceof SearchDocument||in_array($document->state(),['suspended','retracted'],true))continue;[$score,$evidence]=$this->score($document,$plan);if($score>0)$ranked[]=new RankedResult($document,$score,$evidence,$this->policy->version());}usort($ranked,static fn(RankedResult $a,RankedResult $b):int=>($b->score()<=>$a->score())?:($a->document()->canonicalKey()<=>$b->document()->canonicalKey()));return $this->diversify($ranked,$limit);
    }
    private function score(SearchDocument $document,QueryPlan $plan):array
    {
        $fields=$document->fields();$title=$this->text($fields['title']??'');$nt=$this->normalizer->normalizeForSearch($title);$parts=[];foreach($fields as $value){if(is_scalar($value))$parts[]=(string)$value;elseif(is_array($value))foreach($value as $v)if(is_scalar($v))$parts[]=(string)$v;}$haystack=$this->normalizer->normalizeForSearch(implode(' ',$parts));$score=0;$e=[];if($nt!==''&&$nt===$plan->normalizedQuery()){$score+=$this->policy->weight('exact_title');$e[]='exact-title-match';}foreach($plan->terms() as $term){if($nt!==''&&str_contains($nt,$term)){$score+=$this->policy->weight('title_term');$e[]='title-term-match';}if(str_contains($haystack,$term)){$score+=$this->policy->weight('body_term');$e[]='content-term-match';}}$authority=$this->signal($fields['authority_score']??0);$quality=$this->signal($fields['quality_score']??0);$popularity=min(25,$this->signal($fields['popularity_score']??0));$fresh=max(0,10-intdiv(max(0,(int)floor(((new DateTimeImmutable('now',new DateTimeZone('UTC')))->getTimestamp()-$document->lastSourceEventAt()->getTimestamp())/86400)),30));$score+=intdiv($authority*$this->policy->weight('authority'),10)+intdiv($quality*$this->policy->weight('quality'),10)+intdiv($popularity*$this->policy->weight('popularity'),10)+$fresh*$this->policy->weight('freshness');if($authority>=70)$e[]='authoritative-source';if($quality>=70)$e[]='reviewed-quality';if($fresh>=5)$e[]='recent-source-update';if($document->state()==='corrected'){$score+=$this->policy->weight('corrected_penalty');$e[]='corrected-record';}return [$score,array_values(array_unique(array_slice($e,0,12)))];
    }
    private function diversify(array $ranked,int $limit):array
    {
        $accepted=[];$deferred=[];$creators=[];$domains=[];$types=[];foreach($ranked as $result){$fields=$result->document()->fields();$creator=$this->text($fields['creator_id']??'');if($creator==='')$creator='unknown:'.$result->document()->canonicalKey();$domain=(string)$result->document()->toArray()['canonical_domain'];$type=$this->text($fields['content_type']??$domain);if(($creators[$creator]??0)>=$this->policy->maximumPerCreator()||($domains[$domain]??0)>=$this->policy->maximumPerDomain())continue;$prospective=count($accepted)+1;$typeProspective=($types[$type]??0)+1;if($prospective>=5&&(int)floor(($typeProspective/$prospective)*100)>$this->policy->maximumContentTypePercent()){$deferred[]=$result;continue;}$accepted[]=$result;$creators[$creator]=($creators[$creator]??0)+1;$domains[$domain]=($domains[$domain]??0)+1;$types[$type]=($types[$type]??0)+1;if(count($accepted)>=$limit)return $accepted;}foreach($deferred as $result){$fields=$result->document()->fields();$creator=$this->text($fields['creator_id']??'');if($creator==='')$creator='unknown:'.$result->document()->canonicalKey();$domain=(string)$result->document()->toArray()['canonical_domain'];if(($creators[$creator]??0)>=$this->policy->maximumPerCreator()||($domains[$domain]??0)>=$this->policy->maximumPerDomain())continue;$accepted[]=$result;$creators[$creator]=($creators[$creator]??0)+1;$domains[$domain]=($domains[$domain]??0)+1;if(count($accepted)>=$limit)break;}return $accepted;
    }
    private function signal(mixed $value):int{if(is_string($value)&&!is_numeric($value))return 0;if(!is_int($value)&&!is_float($value)&&!is_string($value))return 0;return max(0,min(100,(int)round((float)$value)));} private function text(mixed $value):string{return is_string($value)||is_int($value)||is_float($value)?trim((string)$value):'';}
}
