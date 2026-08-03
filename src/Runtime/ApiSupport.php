<?php

declare(strict_types=1);

namespace Sabri\File26\Api;

use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Support\InvariantViolation;

final class WordPressAudienceFactory
{
    public function current():AudienceContext
    {
        if(!function_exists('is_user_logged_in')||!is_user_logged_in())return AudienceContext::guest();$userId=function_exists('get_current_user_id')?(int)get_current_user_id():0;if($userId<1)return AudienceContext::guest();$capabilities=[];foreach(['read','manage_options','edit_posts','publish_posts'] as $capability)if(function_exists('current_user_can')&&current_user_can($capability))$capabilities[]=str_replace('-','_',$capability);$filtered=function_exists('apply_filters')?apply_filters('sabri_file26_audience_capabilities',$capabilities,$userId):$capabilities;$entitlements=function_exists('apply_filters')?apply_filters('sabri_file26_audience_entitlements',[],$userId):[];$age=function_exists('apply_filters')?apply_filters('sabri_file26_audience_age',null,$userId):null;$guardian=function_exists('apply_filters')?apply_filters('sabri_file26_audience_guardian_consent',false,$userId):false;if(!is_array($filtered)||!is_array($entitlements)||($age!==null&&!is_int($age))||!is_bool($guardian))throw new InvariantViolation('Audience assertion provider returned invalid data.');return new AudienceContext(true,$this->machineKeys($filtered,'/^[a-z][a-z0-9_]{2,99}$/'),$this->machineKeys($entitlements,'/^[a-z][a-z0-9_.-]{2,99}$/'),$age,$guardian);
    }
    private function machineKeys(array $values,string $pattern):array{$result=[];foreach($values as $value){if(!is_string($value)||preg_match($pattern,$value)!==1)throw new InvariantViolation('Audience provider returned an invalid machine key.');$result[$value]=true;}return array_keys($result);}
}

final class WordPressRateLimiter
{
    public function __construct(private readonly string $secret){if(strlen($secret)<32)throw new InvariantViolation('Rate-limit secret is too weak.');}
    public function allow(string $bucket,int $maximum,int $windowSeconds=60):bool{if(!preg_match('/^[a-z][a-z0-9._-]{2,63}$/',$bucket)||$maximum<1||$maximum>1000||$windowSeconds<10||$windowSeconds>3600)throw new InvariantViolation('Rate-limit policy is invalid.');$window=(int)floor(time()/$windowSeconds);$key='s26_rl_'.substr(hash_hmac('sha256',$bucket."\0".$this->actorKey()."\0".$window,$this->secret),0,40);$current=function_exists('get_transient')?get_transient($key):false;$count=is_int($current)?$current:0;if($count>=$maximum)return false;if(function_exists('set_transient'))set_transient($key,$count+1,$windowSeconds+5);return true;}
    private function actorKey():string{if(function_exists('get_current_user_id')){$userId=(int)get_current_user_id();if($userId>0)return'user:'.$userId;}$ip=isset($_SERVER['REMOTE_ADDR'])&&is_string($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'unknown';return'network:'.hash_hmac('sha256',$ip,$this->secret);}
}
