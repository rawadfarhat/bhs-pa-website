<?php
declare(strict_types=1);
namespace App\Services;
use RuntimeException;
/** Narrow anonymous API transport: no DB queries, sessions, redirects or stale cache. */
final class CommunityApiClient
{
    public function __construct(private string $base,private bool $loopback=false)
    {
        LoggingService::info('community-api-client','client.start',[],3);
        $u=parse_url($base);
        if(!is_array($u)||isset($u['user'])||isset($u['pass'])||isset($u['query'])||isset($u['fragment'])||!isset($u['host'],$u['scheme'])||!str_ends_with($u['path']??'','/api/v1/community/public')||($u['scheme']!=='https'&&!($loopback&&$u['scheme']==='http'&&in_array($u['host'],['127.0.0.1','localhost','[::1]'],true))))throw new RuntimeException('Community unavailable',503);
        LoggingService::info('community-api-client','client.ready',[],3);
    }
    public function request(string $path,array $query=[],?array $event=null,bool $image=false): array|string
    {
        LoggingService::info('community-api-client','request.start',[],1);
        if(!preg_match('~^/(?:businesses|promotions)(?:/[a-f0-9]{32}(?:/image/[a-f0-9]{64})?)?$~D',$path)&&$path!=='/events')throw new RuntimeException('Invalid Community route',400);
        if(array_diff(array_keys($query),['page','page_size','search','category','locality','has_offers','business_public_id']))throw new RuntimeException('Invalid filters',400);
        foreach($query as $v)if(!is_string($v)&&!is_int($v))throw new RuntimeException('Invalid filters',400);
        if($event!==null&&$path!=='/events')throw new RuntimeException('Invalid event route',400);
        if(!function_exists('curl_init'))throw new RuntimeException('Community unavailable',503);
        $limit=$image?6291456:1048576;$bytes='';$handle=curl_init(rtrim($this->base,'/').$path.($query?'?'.http_build_query($query):''));
        try{
            LoggingService::trace('community-api-client','transport.prepare',__LINE__,10);
            curl_setopt_array($handle,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|($this->loopback?CURLPROTO_HTTP:0),CURLOPT_HTTPHEADER=>['Accept: '.($image?'image/png':'application/json')],CURLOPT_USERAGENT=>'BHS-Community-Website/1.0',CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$bytes,$limit):int{LoggingService::trace('community-api-client','transport.chunk',__LINE__,10);if(strlen($bytes)+strlen($chunk)>$limit)return 0;$bytes.=$chunk;return strlen($chunk);}]);
            if($event!==null){LoggingService::trace('community-api-client','transport.event',__LINE__,10);curl_setopt($handle,CURLOPT_POST,true);curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($event,JSON_THROW_ON_ERROR));curl_setopt($handle,CURLOPT_HTTPHEADER,['Accept: application/json','Content-Type: application/json']);}
            if(curl_exec($handle)===false)throw new RuntimeException('Community unavailable',503);
            $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$type=strtolower((string)curl_getinfo($handle,CURLINFO_CONTENT_TYPE));
            if($status<200||$status>=300)throw new RuntimeException($status===404?'Listing unavailable':'Community unavailable',$status===404?404:503);
            if($image){LoggingService::trace('community-api-client','image.validate',__LINE__,10);if(!str_starts_with($type,'image/png')||!str_starts_with($bytes,"\x89PNG\r\n\x1a\n"))throw new RuntimeException('Image unavailable',503);LoggingService::info('community-api-client','image.complete',[],1);return $bytes;}
            if(!str_starts_with($type,'application/json'))throw new RuntimeException('Community unavailable',503);
            $result=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);if(!is_array($result))throw new RuntimeException('Community unavailable',503);
            if($path!=='/events')self::validate($result,str_starts_with($path,'/businesses')?'businesses':'promotions',substr_count($path,'/')>1);
            LoggingService::info('community-api-client','request.complete',[],1);return $result;
        }catch(\Throwable $error){LoggingService::caught('community-api-client','request.failed',__LINE__,'error',$error);throw new RuntimeException($error instanceof RuntimeException&&$error->getCode()===404?'Listing unavailable':'Community unavailable',$error instanceof RuntimeException&&$error->getCode()===404?404:503);}
        finally{curl_close($handle);}
    }
    public static function validate(array $result,string $kind,bool $detail): void
    {
        LoggingService::trace('community-api-client','dto.validate',__LINE__,8);
        $business=['public_id','name','category','locality','description','slug','owner_display_name','public_phone','public_email','website_url','address','latitude','longitude','hours','phone_url','email_url','maps_url','image_url'];
        $offer=['public_id','name','category','locality','description','business_name','starts_at','ends_at','title','summary','terms','audience','image_alt','offer_type','percentage_value','fixed_minor_units','currency','business_public_id','display_timezone','status','redemption_policy','image_url'];
        if(array_diff(array_keys($result),$detail?['data']:['data','total','page','page_size'])||!is_array($result['data']??null))throw new RuntimeException('Invalid public projection',503);
        if(!$detail){LoggingService::trace('community-api-client','dto.list',__LINE__,10);foreach(['total','page','page_size'] as $key)if(!is_int($result[$key]??null)||$result[$key]<($key==='total'?0:1))throw new RuntimeException('Invalid pagination',503);if($result['page_size']>100||count($result['data'])>$result['page_size']||!array_is_list($result['data']))throw new RuntimeException('Invalid page',503);}
        foreach($detail?[$result['data']]:$result['data'] as $row){LoggingService::trace('community-api-client','dto.row',__LINE__,10);if(!is_array($row)||array_diff(array_keys($row),$kind==='businesses'?$business:$offer)||!is_string($row['public_id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$row['public_id']))throw new RuntimeException('Invalid public record',503);
            if(!is_string($row[$detail&&$kind==='promotions'?'title':'name']??null))throw new RuntimeException('Invalid title',503);
            foreach($row as $key=>$value){LoggingService::trace('community-api-client','dto.field',__LINE__,10);if($value!==null&&(!is_scalar($value)||is_bool($value)||strlen((string)$value)>12000))throw new RuntimeException('Invalid public field',503);}
            $numeric=['latitude','longitude','percentage_value','fixed_minor_units'];
            foreach($row as $key=>$value){LoggingService::trace('community-api-client','dto.type',__LINE__,10);if($value!==null&&!in_array($key,$numeric,true)&&!is_string($value))throw new RuntimeException('Invalid text field',503);}
            foreach($detail?['category','description']:['category','description'] as $key)if(!is_string($row[$key]??null))throw new RuntimeException('Missing published field',503);
            if($detail&&$kind==='promotions'){
                LoggingService::trace('community-api-client','dto.promotion',__LINE__,10);
                if(!is_string($row['business_public_id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$row['business_public_id'])||!in_array($row['offer_type']??null,['percentage','fixed','descriptive'],true)||!is_string($row['terms']??null))throw new RuntimeException('Invalid offer',503);
                foreach(['starts_at','ends_at'] as $key){LoggingService::trace('community-api-client','dto.date',__LINE__,10);if(!is_string($row[$key]??null)||!preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D',$row[$key])||strtotime($row[$key])===false)throw new RuntimeException('Invalid offer date',503);}
                if($row['ends_at']<=$row['starts_at'])throw new RuntimeException('Invalid offer period',503);
            }
            if(($row['image_url']??null)!==null&&!preg_match('~^/api/v1/community/public/'.$kind.'/'.$row['public_id'].'/image/[a-f0-9]{64}$~D',$row['image_url']))throw new RuntimeException('Invalid image route',503);
        }
        LoggingService::info('community-api-client','dto.complete',[],8);
    }
}
