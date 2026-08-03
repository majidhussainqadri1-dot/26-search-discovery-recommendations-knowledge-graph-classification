<?php

declare(strict_types=1);

use Sabri\File26\Connectors\GenericPublicOwnerConnector;
use Sabri\File26\Contracts\ConnectorManifest;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Governance\ConfigurationRegistry;
use Sabri\File26\Governance\EvaluationCase;
use Sabri\File26\Governance\EvaluationRegistry;
use Sabri\File26\Governance\ExportTokenService;
use Sabri\File26\Governance\HealthDashboard;
use Sabri\File26\Governance\VersionedConfiguration;
use Sabri\File26\KnowledgeGraph\GraphEdge;
use Sabri\File26\KnowledgeGraph\KnowledgeGraph;
use Sabri\File26\Query\QueryUnderstandingPipeline;
use Sabri\File26\Ranking\RankingEngine;
use Sabri\File26\Ranking\RankingPolicy;
use Sabri\File26\Recommendations\RecommendationContext;
use Sabri\File26\Recommendations\RecommendationEngine;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Registry\DefaultConnectorRegistrar;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Taxonomy\TaxonomyRegistry;
use Sabri\File26\Taxonomy\TaxonomyTerm;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

$assertions = 0;
function adv_assert(bool $condition, string $message): void { global $assertions; ++$assertions; if (! $condition) throw new RuntimeException($message); }
function adv_throws(callable $callback, string $message): void { global $assertions; try { $callback(); } catch (InvariantViolation) { ++$assertions; return; } throw new RuntimeException($message); }

$pipeline = new QueryUnderstandingPipeline();
$duplicatePlan = $pipeline->understand('doctor physician doctor');
adv_assert(count($duplicatePlan->terms()) === count(array_unique($duplicatePlan->terms())), 'Query plan terms must remain unique after synonym expansion.');
adv_throws(static fn() => $pipeline->understand(str_repeat('a', 2001)), 'Oversized query should fail closed.');
$clinical = $pipeline->understand('patient prescription record');
adv_assert($clinical->sensitivity() === 'clinical' && ! $clinical->allowsRawTelemetry(), 'Clinical query must disable raw telemetry.');
$pii = $pipeline->understand('35202-1234567-1');
adv_assert($pii->sensitivity() === 'pii', 'CNIC-like value must be classified as PII.');

$public = VisibilityEnvelope::public();
$documents=[];
for($i=1;$i<=8;$i++){
    $documents[] = new SearchDocument('file21:post:'.$i,'file21','v'.$i,'en','published','https://sabrihomeopathy.com/post/'.$i,['title'=>'Liver lesson '.$i,'content_type'=>'lesson','creator_id'=>$i<=6?'creator-a':'creator-b','topics'=>['liver'],'authority_score'=>80,'quality_score'=>80],$public,new DateTimeImmutable('-'.$i.' day'));
}
$ranked=(new RankingEngine(new RankingPolicy('1.0.0',['exact_title'=>120,'title_term'=>30,'body_term'=>8,'authority'=>4,'quality'=>3,'freshness'=>2,'popularity'=>1,'corrected_penalty'=>-15],2,5,60)))->rank($documents,$pipeline->understand('liver'),8);
$creatorACount=0;foreach($ranked as $result)if(($result->document()->fields()['creator_id']??null)==='creator-a')++$creatorACount;
adv_assert($creatorACount<=2,'Ranking diversity must cap a dominant creator.');

$hidden=(new RecommendationEngine())->recommend($documents,new RecommendationContext(false,false,false,[],[],[],[],['file21:post:1'],['creator-a'],['blocked'],10));
foreach($hidden as $result)adv_assert(($result->document()->fields()['creator_id']??null)!=='creator-a','Hidden creator must be excluded from recommendations.');
adv_throws(static fn()=>new RecommendationContext(true,true,false,['liver']), 'Minor personalization without guardian consent should fail.');
adv_throws(static fn()=>new RecommendationContext(false,false,false,['liver']), 'Signals without personalization consent should fail.');

$taxonomy=new TaxonomyRegistry();
$taxonomy->register(new TaxonomyTerm('topic.root',1,['en'=>'Root'],[],[],[],'Root.','file26-curated','active'));
$taxonomy->register(new TaxonomyTerm('topic.a',1,['en'=>'Alpha'],[],['topic.root'],[],'A.','file26-curated','active'));
$taxonomy->register(new TaxonomyTerm('topic.b',1,['en'=>'Beta'],[],['topic.a'],[],'B.','file26-curated','active'));
adv_throws(static fn()=> $taxonomy->register(new TaxonomyTerm('topic.root',2,['en'=>'Root'],[],['topic.b'],[],'Cycle.','file26-curated','active')), 'Taxonomy parent cycle should fail.');
adv_throws(static fn()=> $taxonomy->register(new TaxonomyTerm('topic.c',1,['en'=>'Alpha'],[],['topic.root'],[],'Collision.','file26-curated','active')), 'Active taxonomy label collision should fail.');
$preview=$taxonomy->previewMerge('topic.b','topic.a');$taxonomy->applyMerge($preview,new DateTimeImmutable('now'));
adv_assert($taxonomy->get('topic.b')->state()==='merged','Applied taxonomy merge must mark source merged.');

$restricted=new SearchDocument('file21:restricted:1','file21','v1','en','restricted','https://sabrihomeopathy.com/restricted',['title'=>'Restricted node'],new VisibilityEnvelope(VisibilityEnvelope::VISIBILITY_RESTRICTED,['view_restricted'],[],18,false,false,false),new DateTimeImmutable('now'));
$visible=new SearchDocument('file21:public:1','file21','v1','en','published','https://sabrihomeopathy.com/public',['title'=>'Public node'],$public,new DateTimeImmutable('now'));
$graph=new KnowledgeGraph();$graph->putNode($visible);$graph->putNode($restricted);$graph->putEdge(GraphEdge::create($visible->canonicalKey(),$restricted->canonicalKey(),'post-references','file21','v1','https://sabrihomeopathy.com/public'));
$guestTraversal=$graph->traverse($visible->canonicalKey(),AudienceContext::guest(),['post-references'])->toArray();
adv_assert(count($guestTraversal['nodes'])===1&&count($guestTraversal['edges'])===0,'Graph traversal must not leak restricted target nodes.');
$authorized=new AudienceContext(true,['view_restricted'],[],30,false);$authorizedTraversal=$graph->traverse($visible->canonicalKey(),$authorized,['post-references'])->toArray();
adv_assert(count($authorizedTraversal['nodes'])===2,'Authorized traversal should include restricted target node.');
adv_throws(static fn()=>GraphEdge::create('file21:a','file10:b','post-references','file10','v1','https://sabrihomeopathy.com/evidence'), 'Graph source owner must not contradict source endpoint owner.');

$registry=new ConnectorRegistry();(new DefaultConnectorRegistrar())->registerInto($registry);$health=$registry->health();adv_assert(count($health)===9,'Default connector registrar must remain idempotent per registry construction.');
adv_throws(static fn()=> (new DefaultConnectorRegistrar())->registerInto($registry), 'Registering defaults twice must reject duplicate connector keys.');

$badProvider=new class implements SourceBatchProviderInterface{public function fetch(?string $cursor,int $limit):array{return['records'=>[['canonical_key'=>'file09:doctor:1','owner_key'=>'file09','object_version'=>'v1','locale'=>'en','state'=>'published','destination_url'=>'https://evil.example/doctor/1','last_source_event_at'=>'2026-08-04T00:00:00+00:00','fields'=>['title'=>'Doctor']]],'tombstones'=>[],'next_cursor'=>null,'complete'=>true];}};
$connector=new GenericPublicOwnerConnector(new ConnectorManifest('file09.doctors','file09','1.0.0','1.0.0','1.0.0',[ConnectorManifest::MODE_FULL],200),$badProvider,['title']);
adv_throws(static fn()=> $connector->fetch(null,200), 'Connector must reject off-domain destination URL.');

$externalProvider=new class implements SourceBatchProviderInterface{public function fetch(?string $cursor,int $limit):array{return['records'=>[['canonical_key'=>'file09:doctor:1','owner_key'=>'file09','object_version'=>'v1','locale'=>'en','state'=>'published','destination_url'=>'https://external.example/doctor/1','last_source_event_at'=>'2026-08-04T00:00:00+00:00','fields'=>['title'=>'Doctor']]],'tombstones'=>[],'next_cursor'=>null,'complete'=>true];}};
if (! defined('SABRI_FILE26_CANONICAL_HOST')) define('SABRI_FILE26_CANONICAL_HOST','sabrihomeopathy.com');
$externalConnector=new GenericPublicOwnerConnector(new ConnectorManifest('file09.external','file09','1.0.0','1.0.0','1.0.0',[ConnectorManifest::MODE_FULL],200),$externalProvider,['title']);
adv_throws(static fn()=> $externalConnector->fetch(null,200), 'External host should fail unless represented by a safe canonical wrapper contract.');

$highRisk=VersionedConfiguration::draft('rank-policy','1.0.0',true,['weight'=>10],'author',new DateTimeImmutable('now'))->approve('reviewer-a',new DateTimeImmutable('now'));
adv_throws(static fn()=> $highRisk->activate(null,new DateTimeImmutable('now')), 'High-risk policy with one approval must fail activation.');
$registryConfig=new ConfigurationRegistry();$active=$highRisk->approve('reviewer-b',new DateTimeImmutable('now'))->activate(null,new DateTimeImmutable('now'));$registryConfig->put($active);
$badNext=VersionedConfiguration::draft('rank-policy','1.1.0',true,['weight'=>12],'author',new DateTimeImmutable('now'))->approve('reviewer-a',new DateTimeImmutable('now'))->approve('reviewer-b',new DateTimeImmutable('now'))->activate('0.9.0',new DateTimeImmutable('now'));
adv_throws(static fn()=> $registryConfig->put($badNext), 'Configuration activation must enforce exact previous-version identity.');

$evaluation=new EvaluationRegistry('1.0.0','reviewer');$evaluation->add(new EvaluationCase('safety-one','safe','en','lesson',['file21:public:1'],['file21:restricted:1'],true));$report=$evaluation->evaluate(['safety-one'=>['file21:restricted:1']]);adv_assert($report['release_pass']===false&&$report['critical_failures']===['safety-one'],'Safety-critical evaluation failure must block release.');
$critical=(new HealthDashboard())->summarize(['connector_lag_seconds'=>1000,'failed_events'=>1,'document_count'=>10,'hidden_state_leaks'=>1,'zero_result_rate'=>0.4,'p95_latency_ms'=>1500,'graph_orphans'=>1]);adv_assert($critical['status']==='critical'&&in_array('visibility-leak-blocker',$critical['alerts'],true),'Visibility leak must make health critical.');

$tokens=new ExportTokenService(str_repeat('e',32));$token=$tokens->issue(7,['policies.read'],new DateTimeImmutable('+5 minutes'));$verified=$tokens->verify($token);adv_assert($verified['actor_id']===7&&$verified['scopes']===['policies.read'],'Export token should carry bounded actor and scopes.');
$parts=explode('.',$token);$tampered=$parts[0].'.'.str_repeat('0',64);adv_throws(static fn()=> $tokens->verify($tampered),'Tampered export signature must fail.');
adv_throws(static fn()=> $tokens->issue(0,['policies.read'],new DateTimeImmutable('+5 minutes')),'Export token actor must be valid.');

foreach([GenericPublicOwnerConnector::class,DefaultConnectorRegistrar::class] as $class){$source=file_get_contents((new ReflectionClass($class))->getFileName());adv_assert(!str_contains($source,'private_messages')&&!str_contains($source,'clinical_records')&&!str_contains($source,'payment_secret'),'Connector code must not index prohibited private domains.');}
$publicApiSource=file_get_contents(__DIR__.'/../src/Runtime/ApiPublic.php');adv_assert(str_contains($publicApiSource,'click_visibility_recheck_required'),'Public search/topic responses must preserve click-time visibility recheck law.');adv_assert(str_contains($publicApiSource,'clinical_message_payment_signals_used')&&str_contains($publicApiSource,'false'),'Recommendation API must declare prohibited signals unused.');

echo "Phase 26E fresh adversarial review round 2 tests passed: {$assertions} assertions.\n";
