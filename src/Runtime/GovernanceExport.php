<?php

declare(strict_types=1);

namespace Sabri\File26\Governance;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class ExportTokenService
{
    public function __construct(private readonly string $secret){if(strlen($secret)<32)throw new InvariantViolation('Export token secret is too weak.');}
    public function issue(int $actorId,array $scopes,DateTimeImmutable $expiresAt):string
    {
        if($actorId<1||!array_is_list($scopes)||$scopes===[]||count($scopes)>10||$expiresAt<=new DateTimeImmutable('now'))throw new InvariantViolation('Export token request is invalid.');foreach($scopes as $scope)if(!is_string($scope)||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$scope))throw new InvariantViolation('Export token scope is invalid.');$payload=rtrim(strtr(base64_encode(json_encode(['actor_id'=>$actorId,'scopes'=>array_values(array_unique($scopes)),'expires_at'=>$expiresAt->getTimestamp(),'nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR)),'+/','-_'),'=');return $payload.'.'.hash_hmac('sha256',$payload,$this->secret);
    }
    public function verify(string $token):array
    {
        if(strlen($token)>2048||substr_count($token,'.')!==1)throw new InvariantViolation('Export token is malformed.');[$payload,$signature]=explode('.',$token,2);if(!hash_equals(hash_hmac('sha256',$payload,$this->secret),$signature)||preg_match('/^[A-Za-z0-9_-]+$/',$payload)!==1||preg_match('/^[a-f0-9]{64}$/',$signature)!==1)throw new InvariantViolation('Export token signature or encoding is invalid.');$padding=(4-strlen($payload)%4)%4;$decoded=base64_decode(strtr($payload.str_repeat('=',$padding),'-_','+/'),true);if(!is_string($decoded))throw new InvariantViolation('Export token decoding failed.');try{$data=json_decode($decoded,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new InvariantViolation('Export token payload is invalid JSON.');}if(!is_array($data)||array_keys($data)!==['actor_id','scopes','expires_at','nonce']||!is_int($data['actor_id'])||$data['actor_id']<1||!is_array($data['scopes'])||!array_is_list($data['scopes'])||$data['scopes']===[]||count($data['scopes'])>10||!is_int($data['expires_at'])||$data['expires_at']<time()||!is_string($data['nonce'])||preg_match('/^[a-f0-9]{32}$/',$data['nonce'])!==1)throw new InvariantViolation('Export token is expired or invalid.');foreach($data['scopes'] as $scope)if(!is_string($scope)||preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$scope)!==1)throw new InvariantViolation('Export token contains an invalid scope.');return ['actor_id'=>$data['actor_id'],'scopes'=>array_values(array_unique($data['scopes'])),'expires_at'=>$data['expires_at'],'nonce'=>$data['nonce']];
    }
}

final class WordPressExportTokenStore
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_export_tokens';}
    public function register(string $token,int $actorId,array $scopes,DateTimeImmutable $expiresAt):void{$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));if($actorId<1||!array_is_list($scopes)||$scopes===[]||count($scopes)>10||$expiresAt<=$now||$expiresAt>$now->modify('+1 hour'))throw new InvariantViolation('Export token registration is invalid.');$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (token_hash,actor_id,scopes_payload,expires_at,used_at,created_at) VALUES (%s,%d,%s,%s,NULL,%s)",hash('sha256',$token),$actorId,json_encode($scopes,JSON_THROW_ON_ERROR),$expiresAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),$now->format('Y-m-d H:i:s.u')));if($written!==1)throw new InvariantViolation('Export token registration failed.');}
    public function consume(string $token,DateTimeImmutable $at):array{$hash=hash('sha256',$token);$this->db->query('START TRANSACTION');try{$row=$this->db->get_row($this->db->prepare("SELECT actor_id,scopes_payload,expires_at,used_at FROM {$this->table} WHERE token_hash=%s FOR UPDATE",$hash),ARRAY_A);if(!is_array($row)||$row['used_at']!==null||strtotime((string)$row['expires_at'])<$at->getTimestamp())throw new InvariantViolation('Export token is unavailable, expired or already used.');$scopes=json_decode((string)$row['scopes_payload'],true,16,JSON_THROW_ON_ERROR);if(!is_array($scopes)||!array_is_list($scopes))throw new InvariantViolation('Export token scopes are corrupt.');$updated=$this->db->query($this->db->prepare("UPDATE {$this->table} SET used_at=%s WHERE token_hash=%s AND used_at IS NULL",$at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),$hash));if($updated!==1)throw new InvariantViolation('Export token consumption failed.');$this->db->query('COMMIT');return ['actor_id'=>(int)$row['actor_id'],'scopes'=>array_values($scopes)];}catch(\Throwable $exception){$this->db->query('ROLLBACK');throw $exception;}}
    public function purgeExpired(DateTimeImmutable $at):int{$deleted=$this->db->query($this->db->prepare("DELETE FROM {$this->table} WHERE expires_at < %s OR used_at IS NOT NULL",$at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')));if($deleted===false)throw new InvariantViolation('Export token retention purge failed.');return(int)$deleted;}
}

final class ExportPackageService
{
    public function __construct(private readonly wpdb $db){}
    public function build(int $actorId,array $scopes):array
    {
        if($actorId<1||!array_is_list($scopes)||$scopes===[]||count($scopes)>10)throw new InvariantViolation('Export package request is invalid.');$allowed=['policies.read','metrics.read','taxonomy.read','evaluation.read','own_search_history.read'];foreach($scopes as $scope)if(!in_array($scope,$allowed,true))throw new InvariantViolation('Export scope is not permitted.');$prefix=$this->db->prefix.'s26_';$package=['module'=>'file-26','actor_id'=>$actorId,'generated_at'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format(DATE_ATOM),'scopes'=>$scopes,'other_user_data_included'=>false];if(in_array('policies.read',$scopes,true))$package['policies']=$this->rows("SELECT policy_key,version,state,high_risk,author_key,approvers_payload,previous_version,payload_hash,effective_at,updated_at FROM {$prefix}policies ORDER BY policy_key,version LIMIT 500");if(in_array('metrics.read',$scopes,true))$package['metrics']=$this->rows("SELECT metric_day,metric_key,dimension_hash,dimensions_payload,total FROM {$prefix}telemetry_daily ORDER BY metric_day DESC,total DESC LIMIT 1000");if(in_array('taxonomy.read',$scopes,true))$package['taxonomy']=$this->rows("SELECT term_id,version,state,owner_key,redirect_term_id,payload_hash,updated_at FROM {$prefix}taxonomy_terms ORDER BY term_id LIMIT 1000");if(in_array('evaluation.read',$scopes,true))$package['evaluation_sets']=$this->rows("SELECT set_key,version,state,reviewer_key,payload_hash,updated_at FROM {$prefix}evaluation_sets ORDER BY updated_at DESC LIMIT 200");if(in_array('own_search_history.read',$scopes,true))$package['own_search_history']=['retained'=>false,'records'=>[]];$encoded=json_encode($package,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$package['package_sha256']=hash('sha256',$encoded);return $package;
    }
    private function rows(string $sql):array{$rows=$this->db->get_results($sql,ARRAY_A);return is_array($rows)?$rows:[];}
}
