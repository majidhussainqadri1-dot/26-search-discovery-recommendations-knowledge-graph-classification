<?php

declare(strict_types=1);

namespace Sabri\File26\Query;

use Sabri\File26\Support\InvariantViolation;

final class UnicodeNormalizer
{
    private const DIGITS = ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9'];
    private const PRESENTATION = ['ي'=>'ی','ى'=>'ی','ئ'=>'ی','ك'=>'ک','ۀ'=>'ہ','ة'=>'ہ','ھ'=>'ہ','ؤ'=>'و'];
    public function normalizeForSearch(string $value): string
    {
        if (strlen($value)>20000 || preg_match('/\p{C}/u',$value)===1) throw new InvariantViolation('Text normalization input is invalid or too large.');
        if (class_exists('Normalizer')) { $normalized=\Normalizer::normalize($value,\Normalizer::FORM_KC); if(is_string($normalized)) $value=$normalized; }
        $value=strtr(strtr($value,self::DIGITS),self::PRESENTATION);
        $value=str_replace(["\u{0640}","\u{200C}","\u{200D}","\u{FEFF}"],' ',$value);
        $value=preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u','',$value)??'';
        $value=preg_replace('/[\p{P}\p{S}]+/u',' ',$value)??'';
        $value=preg_replace('/\s+/u',' ',trim($value))??'';
        return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
    }
    public function tokens(string $value,int $maximum=16):array
    {
        if($maximum<1||$maximum>64) throw new InvariantViolation('Token maximum must be between 1 and 64.');
        $normalized=$this->normalizeForSearch($value); if($normalized==='') return [];
        $parts=preg_split('/\s+/u',$normalized,-1,PREG_SPLIT_NO_EMPTY); if(!is_array($parts)) throw new InvariantViolation('Unicode tokenization failed.');
        $tokens=[]; foreach($parts as $part){$length=function_exists('mb_strlen')?mb_strlen($part,'UTF-8'):strlen($part);if($length>64)throw new InvariantViolation('Normalized query term is too long.');$tokens[$part]=true;if(count($tokens)>=$maximum)break;}
        return array_keys($tokens);
    }
}

final class SensitiveQueryClassifier
{
    public const PUBLIC='public',CLINICAL='clinical',PII='pii',ABUSIVE='abusive';
    private array $clinicalTerms=['prescription','patient','case record','medical record','diagnosis','نسخہ','مریض','کیس ریکارڈ','تشخیص','رپورٹ'];
    private array $abusiveTerms=['porn','suicide method','قتل کا طریقہ'];
    public function classify(string $raw,UnicodeNormalizer $normalizer):string
    {
        $normalized=$normalizer->normalizeForSearch($raw);
        if(filter_var(trim($raw),FILTER_VALIDATE_EMAIL)||preg_match('/(?:\+?\d[\d\s().-]{7,}\d)/u',$raw)===1||preg_match('/\b\d{5}-?\d{7}-?\d\b/u',$raw)===1||preg_match('/\b[A-Z]{1,3}\d{6,12}\b/i',$raw)===1)return self::PII;
        foreach($this->abusiveTerms as $term)if(str_contains($normalized,$normalizer->normalizeForSearch($term)))return self::ABUSIVE;
        foreach($this->clinicalTerms as $term)if(str_contains($normalized,$normalizer->normalizeForSearch($term)))return self::CLINICAL;
        return self::PUBLIC;
    }
}

final class TransliterationService
{
    public const POLICY_VERSION='1.0.0'; private array $approvedAliases;
    public function __construct(?array $approvedAliases=null)
    {
        $this->approvedAliases=$approvedAliases??['jigar'=>['جگر'],'sozish'=>['سوزش'],'jigar ki sozish'=>['جگر کی سوزش'],'ilaaj'=>['علاج'],'ilaj'=>['علاج'],'dawa'=>['دوا'],'dawai'=>['دوائی'],'marz'=>['مرض'],'doctor'=>['ڈاکٹر'],'kitab'=>['کتاب'],'lesson'=>['سبق'],'remedy'=>['ریمیڈی','دوا'],'homeopathy'=>['ہومیوپیتھی'],'homeopathic'=>['ہومیوپیتھک']];
        foreach($this->approvedAliases as $source=>$aliases){if(!is_string($source)||trim($source)===''||!array_is_list($aliases)||count($aliases)>8)throw new InvariantViolation('Transliteration policy is invalid.');foreach($aliases as $alias)if(!is_string($alias)||trim($alias)===''||strlen($alias)>200)throw new InvariantViolation('Transliteration alias is invalid.');}
    }
    public function expand(string $normalizedQuery,UnicodeNormalizer $normalizer):array
    {
        $expansions=[$normalizedQuery=>true];foreach($this->approvedAliases as $source=>$aliases){$sourceNormalized=$normalizer->normalizeForSearch($source);if($sourceNormalized===$normalizedQuery||str_contains($normalizedQuery,$sourceNormalized)){foreach($aliases as $alias){$candidate=$normalizer->normalizeForSearch($sourceNormalized===$normalizedQuery?$alias:str_replace($sourceNormalized,$alias,$normalizedQuery));if($candidate!=='')$expansions[$candidate]=true;if(count($expansions)>=8)break 2;}}}return array_keys($expansions);
    }
}

final class SynonymRegistry
{
    public const POLICY_VERSION='1.0.0'; private array $approved; private array $prohibitedPairs=[];
    public function __construct(?array $approved=null,array $prohibitedPairs=[])
    {
        $this->approved=$approved??['physician'=>['doctor'],'doctor'=>['physician','معالج'],'book'=>['کتاب'],'lesson'=>['سبق','lecture'],'video'=>['ویڈیو'],'clinic'=>['کلینک'],'research'=>['تحقیق'],'homeopathy'=>['ہومیوپیتھی']];
        foreach($prohibitedPairs as $pair){if(!is_array($pair)||count($pair)!==2||!is_string($pair[0])||!is_string($pair[1]))throw new InvariantViolation('Prohibited synonym pair is invalid.');$this->prohibitedPairs[$this->pairKey($pair[0],$pair[1])]=true;}
    }
    public function expandTerms(array $terms,UnicodeNormalizer $normalizer):array
    {
        $expanded=[];foreach($terms as $term){$normalized=$normalizer->normalizeForSearch($term);if($normalized==='')continue;$expanded[$normalized]=true;foreach($this->approved[$normalized]??[] as $candidate){$candidate=$normalizer->normalizeForSearch($candidate);if($candidate!==''&&!isset($this->prohibitedPairs[$this->pairKey($normalized,$candidate)]))$expanded[$candidate]=true;if(count($expanded)>=32)break 2;}}return array_keys($expanded);
    }
    private function pairKey(string $left,string $right):string{$pair=[trim($left),trim($right)];sort($pair,SORT_STRING);return hash('sha256',implode("\0",$pair));}
}

final class QueryPlan
{
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
    public function understand(string $rawQuery):QueryPlan
    {
        if(trim($rawQuery)===''||strlen($rawQuery)>2000)throw new InvariantViolation('Search query is empty or too large.');$normalized=$this->normalizer->normalizeForSearch($rawQuery);$baseTerms=$this->normalizer->tokens($normalized,16);if($baseTerms===[])throw new InvariantViolation('Search query produced no terms.');$terms=$this->synonyms->expandTerms($baseTerms,$this->normalizer);$expanded=$this->transliteration->expand($normalized,$this->normalizer);foreach($expanded as $query)foreach($this->normalizer->tokens($query,16) as $term)$terms[]=$term;$terms=array_slice(array_values(array_unique($terms)),0,32);return new QueryPlan($normalized,$terms,$expanded,$this->classifier->classify($rawQuery,$this->normalizer),self::POLICY_VERSION);
    }
    public function normalizer():UnicodeNormalizer{return $this->normalizer;}
}
