<?php

declare(strict_types=1);

namespace Sabri\File26\Application;

use Sabri\File26\Api\AdminApiController;
use Sabri\File26\Api\PublicApiController;
use Sabri\File26\Api\WordPressAudienceFactory;
use Sabri\File26\Api\WordPressRateLimiter;
use Sabri\File26\Governance\TelemetryRedactor;
use Sabri\File26\Governance\WordPressTelemetryStore;
use Sabri\File26\KnowledgeGraph\WordPressGraphStore;
use Sabri\File26\Operations\WordPressRuntime;
use Sabri\File26\Query\QueryUnderstandingPipeline;
use Sabri\File26\Ranking\RankingEngine;
use Sabri\File26\Recommendations\RecommendationEngine;
use Sabri\File26\Recommendations\WordPressFeedbackStore;
use Sabri\File26\Search\PersistentQueryService;
use Sabri\File26\Taxonomy\WordPressTaxonomyStore;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class WordPressApplication
{
    private static ?self $instance=null;
    private readonly WordPressRuntime $runtime;private readonly PublicApiController $publicApi;private readonly AdminApiController $adminApi;
    private function __construct(private readonly wpdb $db)
    {
        $this->runtime=WordPressRuntime::instance($db);$secret=$this->secret();$understanding=new QueryUnderstandingPipeline();$ranking=new RankingEngine();$search=new AdvancedSearchService($this->runtime->activeRepository(),$this->runtime->cursorCodec(),$understanding,$ranking);$candidates=new RecommendationCandidateRepository($db);$audiences=new WordPressAudienceFactory();$this->publicApi=new PublicApiController($search,$candidates,new SuggestionService(),new FacetService(),new RecommendationEngine(),new WordPressFeedbackStore($db),new TelemetryRedactor($secret),new WordPressTelemetryStore($db),new WordPressTaxonomyStore($db),new WordPressGraphStore($db),$audiences,new WordPressRateLimiter($secret),$secret);$this->adminApi=new AdminApiController($db,$secret);
    }
    public static function boot(wpdb $db):self{if(self::$instance===null)self::$instance=new self($db);return self::$instance;}
    public function registerHooks():void{add_action('rest_api_init',[$this,'registerRoutes']);add_action('sabri_file26_retention',[$this,'retention']);if(!wp_next_scheduled('sabri_file26_retention'))wp_schedule_event(time()+3600,'daily','sabri_file26_retention');}
    public function registerRoutes():void{$this->publicApi->register();$this->adminApi->register();}
    public function retention():void{$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));(new WordPressTelemetryStore($this->db))->purgeBefore($now->modify('-90 days'));(new WordPressFeedbackStore($this->db))->purgeActor(hash_hmac('sha256','deleted-user-sentinel',$this->secret()));}
    private function secret():string
    {
        if(defined('SABRI_FILE26_SECRET')&&is_string(SABRI_FILE26_SECRET)&&strlen(SABRI_FILE26_SECRET)>=32)return SABRI_FILE26_SECRET;$material=(defined('AUTH_KEY')?(string)AUTH_KEY:'').(defined('SECURE_AUTH_KEY')?(string)SECURE_AUTH_KEY:'').(defined('LOGGED_IN_KEY')?(string)LOGGED_IN_KEY:'');if(strlen($material)<32)throw new InvariantViolation('File 26 requires a WordPress secret source of at least 32 characters.');return hash_hmac('sha256','sabri-file26-runtime',$material);
    }
}

final class WordPressCliApplication
{
    public function __construct(private readonly WordPressRuntime $runtime,private readonly wpdb $db){}
    public function register():void
    {
        if(!defined('WP_CLI')||WP_CLI!==true||!class_exists('WP_CLI'))return;
        \WP_CLI::add_command('sabri-file26 reconcile',function(array $args,array $assoc):void{unset($args);$limit=isset($assoc['limit'])?(int)$assoc['limit']:100;if($limit<1||$limit>1000)\WP_CLI::error('Limit must be between 1 and 1000.');$result=$this->reconcile($limit);\WP_CLI::log(json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));if($result['failed_events']>0||$result['overdue_purges']>0)\WP_CLI::warning('Reconciliation found pending integrity work.');else \WP_CLI::success('Reconciliation completed without pending defects.');});
        \WP_CLI::add_command('sabri-file26 telemetry-purge',function(array $args,array $assoc):void{unset($args);$days=isset($assoc['days'])?(int)$assoc['days']:90;if($days<1||$days>400)\WP_CLI::error('Days must be between 1 and 400.');$deleted=(new WordPressTelemetryStore($this->db))->purgeBefore((new \DateTimeImmutable('today',new \DateTimeZone('UTC')))->modify('-'.$days.' days'));\WP_CLI::success('Deleted '.$deleted.' telemetry aggregates.');});
    }
    public function reconcile(int $limit):array
    {
        $prefix=$this->db->prefix.'s26_';$failed=(int)$this->db->get_var("SELECT COUNT(*) FROM {$prefix}change_events WHERE status='failed'");$overdue=(int)$this->db->get_var("SELECT COUNT(*) FROM {$prefix}purge_ledger WHERE completed_at IS NULL AND requested_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE");$duplicates=(int)$this->db->get_var("SELECT COUNT(*) FROM (SELECT canonical_key,COUNT(*) c FROM {$prefix}documents d INNER JOIN {$prefix}generation_aliases a ON a.alias_key='active' AND a.generation_id=d.generation_id GROUP BY canonical_key HAVING c>1) x");return['failed_events'=>$failed,'overdue_purges'=>$overdue,'duplicate_active_keys'=>$duplicates,'queue'=>$this->runtime->diagnostics()['queue'],'limit'=>$limit];
    }
}
