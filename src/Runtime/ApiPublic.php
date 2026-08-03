<?php

declare(strict_types=1);

namespace Sabri\File26\Api;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Application\AdvancedSearchService;
use Sabri\File26\Application\FacetService;
use Sabri\File26\Application\RecommendationCandidateRepository;
use Sabri\File26\Application\SuggestionService;
use Sabri\File26\Governance\TelemetryRedactor;
use Sabri\File26\Governance\WordPressTelemetryStore;
use Sabri\File26\KnowledgeGraph\WordPressGraphStore;
use Sabri\File26\Recommendations\FeedbackEvent;
use Sabri\File26\Recommendations\FeedbackStoreInterface;
use Sabri\File26\Recommendations\RecommendationContext;
use Sabri\File26\Recommendations\RecommendationEngine;
use Sabri\File26\Support\InvariantViolation;
use Sabri\File26\Taxonomy\WordPressTaxonomyStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PublicApiController
{
    public function __construct(private readonly AdvancedSearchService $search,private readonly RecommendationCandidateRepository $candidates,private readonly SuggestionService $suggestions,private readonly FacetService $facets,private readonly RecommendationEngine $recommendations,private readonly FeedbackStoreInterface $feedback,private readonly TelemetryRedactor $telemetryRedactor,private readonly WordPressTelemetryStore $telemetry,private readonly WordPressTaxonomyStore $taxonomy,private readonly WordPressGraphStore $graph,private readonly WordPressAudienceFactory $audiences,private readonly WordPressRateLimiter $rateLimiter,private readonly string $actorHashSecret){if(strlen($actorHashSecret)<32)throw new InvariantViolation('Feedback actor hashing secret is too weak.');}
    public function register():void
    {
        register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/query',['methods'=>'GET','callback'=>[$this,'query'],'permission_callback'=>'__return_true']);
        register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/suggest',['methods'=>'GET','callback'=>[$this,'suggest'],'permission_callback'=>'__return_true']);
        register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/facets',['methods'=>'GET','callback'=>[$this,'facet'],'permission_callback'=>'__return_true']);
        register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/recommendations',['methods'=>'GET','callback'=>[$this,'recommend'],'permission_callback'=>'__return_true']);
        register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/recommendation-feedback',['methods'=>'POST','callback'=>[$this,'recordFeedback'],'permission_callback'=>[$this,'mustBeAuthenticated']]);
        register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/topics/(?P<concept>[a-z][a-z0-9._-]{2,63})',['methods'=>'GET','callback'=>[$this,'topic'],'permission_callback'=>'__return_true']);
    }
    public function query(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        if(!$this->rateLimiter->allow('query',60))return $this->error('rate_limited','Search request rate limit exceeded.',429);$query=$request->get_param('q');if(!is_string($query))return $this->error('invalid_query','A query string is required.',400);try{$audience=$this->audiences->current();$page=$this->search->search($query,$audience,$this->boundedInt($request->get_param('limit'),20,1,50),($cursor=$request->get_param('cursor'))&&is_string($cursor)?$cursor:null,$this->csv($request->get_param('domains'),20),$this->csv($request->get_param('locales'),20));$plan=$this->search->understanding()->understand($query);$this->telemetry->increment($this->telemetryRedactor->queryMetric($plan,'search.query',['authenticated'=>$audience->isAuthenticated()]));$response=new WP_REST_Response($page->toArray(),200);$response->header('Cache-Control',$audience->isAuthenticated()?'private, no-store':'public, max-age=30, stale-while-revalidate=30');return $response;}catch(InvariantViolation $exception){return $this->error('query_rejected',$exception->getMessage(),409);}
    }
    public function suggest(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        if(!$this->rateLimiter->allow('suggest',120))return $this->error('rate_limited','Suggestion request rate limit exceeded.',429);$prefix=$request->get_param('q');if(!is_string($prefix)||trim($prefix)==='')return $this->error('invalid_prefix','A suggestion prefix is required.',400);try{$items=$this->suggestions->suggest($prefix,$this->candidates->recent(500),$this->audiences->current(),$this->boundedInt($request->get_param('limit'),10,1,20));$response=new WP_REST_Response(['suggestions'=>$items,'raw_recent_query_echo'=>false],200);$response->header('Cache-Control','private, no-store');return $response;}catch(InvariantViolation $exception){return $this->error('suggestion_rejected',$exception->getMessage(),409);}
    }
    public function facet(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        if(!$this->rateLimiter->allow('facets',60))return $this->error('rate_limited','Facet request rate limit exceeded.',429);try{$query=$request->get_param('q');if(!is_string($query)||trim($query)==='')return $this->error('invalid_query','A query string is required for facets.',400);$audience=$this->audiences->current();$page=$this->search->search($query,$audience,50,null,$this->csv($request->get_param('domains'),20),$this->csv($request->get_param('locales'),20));$documents=array_map(static fn($ranked)=>$ranked->document(),$page->rankedResults());return new WP_REST_Response(['facets'=>$this->facets->counts($documents,$audience),'eligibility_aware'=>true,'query_snapshot'=>true],200);}catch(InvariantViolation $exception){return $this->error('facet_rejected',$exception->getMessage(),409);}
    }
    public function recommend(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        if(!$this->rateLimiter->allow('recommendations',60))return $this->error('rate_limited','Recommendation request rate limit exceeded.',429);try{$audience=$this->audiences->current();$consent=$request->get_param('personalization_consent')===true||$request->get_param('personalization_consent')==='1';if($consent&&!$audience->isAuthenticated())return $this->error('authentication_required','Personalized recommendations require authentication.',401);$context=new RecommendationContext($consent,$audience->age()!==null&&$audience->age()<18,$audience->hasVerifiedGuardianConsent(),$consent?$this->csv($request->get_param('interests'),100):[],$consent?$this->csv($request->get_param('follows'),100):[],$consent?$this->csv($request->get_param('learning_topics'),100):[],$consent?$this->csv($request->get_param('saved_topics'),100):[],$this->csv($request->get_param('hidden_items'),100),$this->csv($request->get_param('hidden_creators'),100),$this->csv($request->get_param('hidden_topics'),100),$this->boundedInt($request->get_param('limit'),20,1,50));$items=$this->recommendations->recommend($this->candidates->recent(1000),$context);$response=new WP_REST_Response(['recommendations'=>array_map(static fn($item):array=>$item->toArray(),$items),'personalized'=>$context->personalizationConsent(),'clinical_message_payment_signals_used'=>false],200);$response->header('Cache-Control',$context->personalizationConsent()?'private, no-store':'public, max-age=30');return $response;}catch(InvariantViolation $exception){return $this->error('recommendation_rejected',$exception->getMessage(),409);}
    }
    public function recordFeedback(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        if(!$this->rateLimiter->allow('feedback',120))return $this->error('rate_limited','Feedback request rate limit exceeded.',429);$target=$request->get_param('target_key');$type=$request->get_param('type');$idempotency=$request->get_param('idempotency_key');$context=$request->get_param('context_hash');if(!is_string($target)||!is_string($type)||!is_string($idempotency)||!is_string($context))return $this->error('invalid_feedback','Feedback target, type, idempotency key and context hash are required.',400);try{$actorHash=hash_hmac('sha256','user:'.(int)get_current_user_id(),$this->actorHashSecret);$stored=$this->feedback->record(new FeedbackEvent($idempotency,$actorHash,$target,$type,$context,new DateTimeImmutable('now',new DateTimeZone('UTC'))));return new WP_REST_Response(['feedback_id'=>$stored->idempotencyKey(),'state'=>$stored->reversed()?'reversed':'active'],200);}catch(InvariantViolation $exception){return $this->error('feedback_rejected',$exception->getMessage(),409);}
    }
    public function topic(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        $concept=$request->get_param('concept');if(!is_string($concept))return $this->error('invalid_concept','Topic concept is required.',400);try{$term=null;foreach($this->taxonomy->rows() as $row)if(($row['term_id']??null)===$concept&&in_array(($row['state']??null),['active','approved'],true)){$term=$row;break;}if($term===null)return $this->error('topic_not_found','Topic was not found.',404);$response=new WP_REST_Response(['topic'=>$term,'relations'=>$this->graph->outgoing('taxonomy:'.$concept,100),'canonical_owner_replacement'=>false,'generated_medical_claims'=>false],200);$response->header('Cache-Control','public, max-age=60');return $response;}catch(InvariantViolation $exception){return $this->error('topic_rejected',$exception->getMessage(),409);}
    }
    public function mustBeAuthenticated(WP_REST_Request $request):bool|WP_Error{unset($request);return function_exists('is_user_logged_in')&&is_user_logged_in()?true:$this->error('authentication_required','Authentication is required.',401);}
    private function error(string $code,string $message,int $status):WP_Error{return new WP_Error('sabri_file26_'.$code,$message,['status'=>$status]);}
    private function boundedInt(mixed $value,int $default,int $minimum,int $maximum):int{if($value===null||$value==='')return $default;if(is_string($value)&&ctype_digit($value))$value=(int)$value;if(!is_int($value)||$value<$minimum||$value>$maximum)throw new InvariantViolation('Numeric request parameter is outside allowed bounds.');return $value;}
    private function csv(mixed $value,int $maximum):array{if($value===null||$value==='')return[];$values=is_string($value)?explode(',',$value):(is_array($value)?$value:null);if($values===null)throw new InvariantViolation('List request parameter is invalid.');$result=[];foreach($values as $item){if(!is_string($item)||trim($item)===''||strlen($item)>292)throw new InvariantViolation('List request parameter contains an invalid value.');$result[trim($item)]=true;if(count($result)>$maximum)throw new InvariantViolation('List request parameter exceeds its limit.');}return array_keys($result);}
}
