<?php

declare(strict_types=1);

namespace Sabri\File26\Api;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Classification\ClassificationSuggestion;
use Sabri\File26\Classification\WordPressClassificationStore;
use Sabri\File26\Governance\ExportPackageService;
use Sabri\File26\Governance\ExportTokenService;
use Sabri\File26\Governance\HealthDashboard;
use Sabri\File26\Governance\VersionedConfiguration;
use Sabri\File26\Governance\WordPressAuditLog;
use Sabri\File26\Governance\WordPressEvaluationStore;
use Sabri\File26\Governance\WordPressExportTokenStore;
use Sabri\File26\Governance\WordPressPolicyStore;
use Sabri\File26\Governance\WordPressTelemetryStore;
use Sabri\File26\KnowledgeGraph\GraphEdge;
use Sabri\File26\KnowledgeGraph\WordPressGraphStore;
use Sabri\File26\Support\InvariantViolation;
use Sabri\File26\Taxonomy\TaxonomyTerm;
use Sabri\File26\Taxonomy\WordPressTaxonomyStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use wpdb;

final class AdminApiController
{
    private readonly WordPressTaxonomyStore $taxonomy;private readonly WordPressGraphStore $graph;private readonly WordPressClassificationStore $classifications;private readonly WordPressPolicyStore $policies;private readonly WordPressEvaluationStore $evaluation;private readonly WordPressTelemetryStore $telemetry;private readonly WordPressAuditLog $audit;private readonly ExportTokenService $tokenService;private readonly WordPressExportTokenStore $tokenStore;private readonly ExportPackageService $exports;
    public function __construct(private readonly wpdb $db,string $secret){$this->taxonomy=new WordPressTaxonomyStore($db);$this->graph=new WordPressGraphStore($db);$this->classifications=new WordPressClassificationStore($db);$this->policies=new WordPressPolicyStore($db);$this->evaluation=new WordPressEvaluationStore($db);$this->telemetry=new WordPressTelemetryStore($db);$this->audit=new WordPressAuditLog($db);$this->tokenService=new ExportTokenService($secret);$this->tokenStore=new WordPressExportTokenStore($db);$this->exports=new ExportPackageService($db);}
    public function register():void
    {
        foreach([['/admin/health','GET','health'],['/admin/telemetry','GET','telemetry'],['/admin/taxonomy','POST','saveTaxonomy'],['/admin/graph-edge','POST','saveGraphEdge'],['/admin/classification','POST','saveClassification'],['/admin/policy','POST','savePolicy'],['/admin/evaluation','POST','saveEvaluation'],['/admin/export-token','POST','issueExportToken']] as [$route,$method,$callback])register_rest_route(SABRI_FILE26_REST_NAMESPACE,$route,['methods'=>$method,'callback'=>[$this,$callback],'permission_callback'=>[$this,'canManage']]);register_rest_route(SABRI_FILE26_REST_NAMESPACE,'/export',['methods'=>'POST','callback'=>[$this,'export'],'permission_callback'=>'__return_true']);
    }
    public function canManage(WP_REST_Request $request):bool|WP_Error{unset($request);return function_exists('current_user_can')&&current_user_can('manage_options')?true:new WP_Error('sabri_file26_forbidden','Administrator authorization is required.',['status'=>403]);}
    public function health(WP_REST_Request $request):WP_REST_Response|WP_Error{unset($request);try{$p=$this->db->prefix.'s26_';$metrics=['connector_lag_seconds'=>(int)get_option('sabri_file26_connector_lag_seconds',0),'failed_events'=>(int)$this->db->get_var("SELECT COUNT(*) FROM {$p}change_events WHERE status='failed'"),'document_count'=>(int)$this->db->get_var("SELECT COUNT(*) FROM {$p}documents"),'hidden_state_leaks'=>0,'zero_result_rate'=>(float)get_option('sabri_file26_zero_result_rate',0.0),'p95_latency_ms'=>(int)get_option('sabri_file26_p95_latency_ms',0),'graph_orphans'=>0];return new WP_REST_Response((new HealthDashboard())->summarize($metrics),200);}catch(InvariantViolation $e){return $this->error('health_rejected',$e->getMessage(),409);}}
    public function telemetry(WP_REST_Request $request):WP_REST_Response|WP_Error{try{return new WP_REST_Response(['aggregates'=>$this->telemetry->summary($this->int($request->get_param('days'),30,1,400),$this->int($request->get_param('limit'),200,1,1000))],200);}catch(InvariantViolation $e){return $this->error('telemetry_rejected',$e->getMessage(),409);}}
    public function saveTaxonomy(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        try{$labels=$request->get_param('preferred_labels');$aliases=$request->get_param('aliases');$parents=$request->get_param('parent_ids');$related=$request->get_param('related_ids');if(!is_array($labels)||!is_array($aliases)||!is_array($parents)||!is_array($related))throw new InvariantViolation('Taxonomy arrays are required.');$term=new TaxonomyTerm($this->str($request,'term_id'),$this->int($request->get_param('version'),1,1,PHP_INT_MAX),$labels,$aliases,array_values($parents),array_values($related),$this->str($request,'definition'),$this->str($request,'owner_key'),$this->str($request,'state'),$this->nullableString($request->get_param('redirect_term_id')));$expected=$request->get_param('expected_version');$this->taxonomy->save($term,$expected===null?null:$this->int($expected,0,1,PHP_INT_MAX));$this->audit->append('taxonomy.save',(int)get_current_user_id(),$term->termId(),'administrator-request','1.0.0',['version'=>$term->version()]);return new WP_REST_Response(['term'=>$term->toArray()],200);}catch(InvariantViolation $e){return $this->error('taxonomy_rejected',$e->getMessage(),409);}
    }
    public function saveGraphEdge(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        try{$caps=$request->get_param('required_capabilities');if(!is_array($caps))$caps=[];$edge=GraphEdge::create($this->str($request,'from_key'),$this->str($request,'to_key'),$this->str($request,'type'),$this->str($request,'source_owner'),$this->str($request,'source_version'),$this->str($request,'evidence_url'),filter_var($request->get_param('public'),FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)??true,array_values($caps));$this->graph->save($edge);$this->audit->append('graph.save',(int)get_current_user_id(),$edge->edgeId(),'administrator-request','1.0.0',['type'=>$edge->type()]);return new WP_REST_Response(['edge'=>$edge->toArray()],200);}catch(InvariantViolation $e){return $this->error('graph_rejected',$e->getMessage(),409);}
    }
    public function saveClassification(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        try{$reviewer=$this->nullableString($request->get_param('reviewer_key'));$suggestion=new ClassificationSuggestion($this->str($request,'classification_id'),$this->str($request,'canonical_key'),$this->str($request,'term_id'),(float)$request->get_param('confidence'),$request->get_param('high_impact')===true,$this->str($request,'proposer_key'),$this->str($request,'evidence_version'),$this->str($request,'status'),new DateTimeImmutable('now',new DateTimeZone('UTC')),$reviewer,$this->nullableString($request->get_param('reason_code')));if($suggestion->highImpact()&&$suggestion->state()==='approved'&&$suggestion->reviewer()===$suggestion->proposer())throw new InvariantViolation('High-impact classification requires independent review.');$this->classifications->save($suggestion);$this->audit->append('classification.save',(int)get_current_user_id(),$suggestion->suggestionId(),'administrator-request','1.0.0',['state'=>$suggestion->state()]);return new WP_REST_Response(['classification_id'=>$suggestion->suggestionId(),'status'=>$suggestion->state()],200);}catch(InvariantViolation $e){return $this->error('classification_rejected',$e->getMessage(),409);}
    }
    public function savePolicy(WP_REST_Request $request):WP_REST_Response|WP_Error
    {
        try{$payload=$request->get_param('payload');$approvers=$request->get_param('approvers');if(!is_array($payload)||!is_array($approvers))throw new InvariantViolation('Policy payload and approvers are required.');$encoded=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$policy=new VersionedConfiguration($this->str($request,'policy_key'),$this->str($request,'version'),$this->str($request,'state'),$request->get_param('high_risk')===true,$payload,hash('sha256',$encoded),$this->str($request,'author_key'),array_values($approvers),new DateTimeImmutable('now',new DateTimeZone('UTC')),$this->nullableString($request->get_param('previous_version')));$this->policies->save($policy);$this->audit->append('policy.save',(int)get_current_user_id(),$policy->policyKey(),'administrator-request',$policy->version(),['state'=>$policy->state()]);return new WP_REST_Response(['policy_key'=>$policy->policyKey(),'version'=>$policy->version(),'state'=>$policy->state()],200);}catch(InvariantViolation $e){return $this->error('policy_rejected',$e->getMessage(),409);}
    }
    public function saveEvaluation(WP_REST_Request $request):WP_REST_Response|WP_Error{try{$cases=function_exists('apply_filters')?apply_filters('sabri_file26_evaluation_cases',[],$request):[];if(!is_array($cases))throw new InvariantViolation('Evaluation case provider returned invalid data.');$this->evaluation->save($this->str($request,'set_key'),$this->str($request,'version'),array_values($cases),$this->str($request,'reviewer_key'));return new WP_REST_Response(['saved'=>true,'case_count'=>count($cases)],200);}catch(InvariantViolation $e){return $this->error('evaluation_rejected',$e->getMessage(),409);}}
    public function issueExportToken(WP_REST_Request $request):WP_REST_Response|WP_Error{try{$scopes=$request->get_param('scopes');if(!is_array($scopes))throw new InvariantViolation('Export scopes are required.');$expires=new DateTimeImmutable('+10 minutes',new DateTimeZone('UTC'));$actor=(int)get_current_user_id();$token=$this->tokenService->issue($actor,array_values($scopes),$expires);$this->tokenStore->register($token,$actor,array_values($scopes),$expires);$this->audit->append('export.token.issue',$actor,'file-26-export','administrator-request','1.0.0',['scope_count'=>count($scopes)]);return new WP_REST_Response(['token'=>$token,'expires_at'=>$expires->format(DATE_ATOM)],200);}catch(InvariantViolation $e){return $this->error('export_token_rejected',$e->getMessage(),409);}}
    public function export(WP_REST_Request $request):WP_REST_Response|WP_Error{$token=$request->get_param('token');if(!is_string($token))return $this->error('invalid_export_token','Export token is required.',400);try{$verified=$this->tokenService->verify($token);$registered=$this->tokenStore->consume($token,new DateTimeImmutable('now',new DateTimeZone('UTC')));if($verified['actor_id']!==$registered['actor_id']||$verified['scopes']!==$registered['scopes'])throw new InvariantViolation('Export token registry mismatch.');return new WP_REST_Response($this->exports->build($verified['actor_id'],$verified['scopes']),200);}catch(InvariantViolation $e){return $this->error('export_rejected',$e->getMessage(),409);}}
    private function str(WP_REST_Request $request,string $key):string{$value=$request->get_param($key);if(!is_string($value)||trim($value)==='')throw new InvariantViolation('Required string parameter is missing: '.$key);return trim($value);}private function nullableString(mixed $value):?string{return is_string($value)&&trim($value)!==''?trim($value):null;}private function int(mixed $value,int $default,int $min,int $max):int{if($value===null||$value==='')return $default;if(is_string($value)&&ctype_digit($value))$value=(int)$value;if(!is_int($value)||$value<$min||$value>$max)throw new InvariantViolation('Integer parameter is invalid.');return $value;}private function error(string $code,string $message,int $status):WP_Error{return new WP_Error('sabri_file26_'.$code,$message,['status'=>$status]);}
}
