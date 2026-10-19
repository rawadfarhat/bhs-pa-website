<?php
declare(strict_types=1);

/** Portable public HTTP connector. Never loads portal DB/config or accepts an upstream URL from a visitor. */
final class CommunityClient
{
    private array $settings;
    public function __construct(array $settings){$this->settings=$settings;self::diagnostic('connector.created',8);}
    public static function diagnostic(string $event,int $level=3,string $severity='info'): void
    {
        // Shared-host builds may supply the central logger. Standalone builds emit no ad hoc logs.
        if(class_exists(\App\Services\LoggingService::class)){
            if($severity==='error')\App\Services\LoggingService::error('community-public',$event);
            elseif($severity==='warning')\App\Services\LoggingService::warning('community-public',$event);
            else \App\Services\LoggingService::info('community-public',$event,[],$level);
        }
    }
    public function request(string $operation,array $query=[],bool $image=false): array
    {
        self::diagnostic('website.request.start',1);
        if(!preg_match('~^(availability|choices/(categories|localities|businesses)|(businesses|promotions)(/[1-9][0-9]{0,9}(/image)?)?)$~D',$operation)||$image!==str_ends_with($operation,'/image'))throw new RuntimeException('Invalid catalog operation.',422);
        $base=rtrim((string)($this->settings['portal_base_url']??''),'/');$parts=parse_url($base);
        if(!$parts||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'],$parts['pass'])||isset($parts['user'])||isset($parts['query'])||isset($parts['fragment']))throw new RuntimeException('Community is temporarily unavailable.',503);
        if(!function_exists('curl_init'))throw new RuntimeException('Community is temporarily unavailable.',503);
        $handle=curl_init($base.'/api/v1/community/public/'.$operation.($query?'?'.http_build_query($query):''));$body='';$maximum=$image?max(1024,min(8388608,(int)($this->settings['image_max_bytes']??8388608))):max(1024,min(1048576,(int)($this->settings['json_max_bytes']??1048576)));
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT_MS=>max(100,min(2000,(int)($this->settings['connect_timeout_ms']??2000))),CURLOPT_TIMEOUT_MS=>max(500,min(5000,(int)($this->settings['timeout_ms']??5000))),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: '.($image?'image/png,image/jpeg,image/webp':'application/json'),'Cache-Control: no-cache'],CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk)use(&$body,$maximum):int{if(strlen($body)+strlen($chunk)>$maximum)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$type=strtolower(explode(';',(string)curl_getinfo($handle,CURLINFO_CONTENT_TYPE))[0]);curl_close($handle);
        if($ok===false||$status!==200){self::diagnostic('website.request.failed',1,'error');throw new RuntimeException($status===404?'Community listing unavailable.':'Community is temporarily unavailable.',$status===404?404:503);}
        if($image){if(!in_array($type,['image/png','image/jpeg','image/webp'],true)||$body==='')throw new RuntimeException('Image unavailable.',503);self::diagnostic('website.image.complete',3);return ['mime_type'=>$type,'bytes'=>$body];}
        if($type!=='application/json')throw new RuntimeException('Community is temporarily unavailable.',503);
        try{$payload=json_decode($body,true,24,JSON_THROW_ON_ERROR);}catch(Throwable){self::diagnostic('website.schema.failed',1,'error');throw new RuntimeException('Community is temporarily unavailable.',503);}
        if(!is_array($payload)||!is_array($payload['data']??null))throw new RuntimeException('Community is temporarily unavailable.',503);
        $data=$payload['data'];self::validate($operation,$data);self::diagnostic('website.request.complete',1);return $data;
    }
    public static function validate(string $operation,array $data): void
    {
        self::diagnostic('website.schema.start',8);
        if($operation==='availability'){if(array_keys($data)!==['available']||!is_bool($data['available']))throw new RuntimeException('Community is temporarily unavailable.',503);return;}
        if(isset($data['rows'])){
            if(!is_array($data['rows'])||!array_is_list($data['rows'])||count($data['rows'])>100||!is_int($data['total']??null)||$data['total']<0||!is_int($data['page']??null)||$data['page']<1||!in_array($data['page_size']??null,[25,50,100],true))throw new RuntimeException('Community is temporarily unavailable.',503);
            foreach($data['rows'] as $row){self::diagnostic('website.schema.row',10);self::record($row,str_starts_with($operation,'choices/'));}
        }elseif(isset($data['promotion'],$data['business'])){self::record($data['promotion']);self::record($data['business']);if((int)($data['promotion']['business_id']??0)!==(int)$data['business']['id'])throw new RuntimeException('Community is temporarily unavailable.',503);}
        else self::record($data);
        self::diagnostic('website.schema.complete',8);
    }
    private static function record(mixed $row,bool $choice=false): void
    {
        self::diagnostic('website.schema.record',10);
        if(!is_array($row)||!is_scalar($row['id']??null)||!is_string($row['name']??null)||strlen($row['name'])>1000)throw new RuntimeException('Community is temporarily unavailable.',503);
        if(!$choice&&(!ctype_digit((string)$row['id'])||(int)$row['id']<1))throw new RuntimeException('Community is temporarily unavailable.',503);
        foreach(['sub_text','description','address','locality','maps_url','category','business','start_date','end_date','availability'] as $field)if(isset($row[$field])&&(!is_string($row[$field])||strlen($row[$field])>30000))throw new RuntimeException('Community is temporarily unavailable.',503);
        if(isset($row['counters'])){if(!is_array($row['counters']))throw new RuntimeException('Community is temporarily unavailable.',503);foreach($row['counters'] as $key=>$value)if(!in_array($key,['redeemed','remaining','total'],true)||!is_int($value)||$value<0)throw new RuntimeException('Community is temporarily unavailable.',503);}
        foreach(['owner_user_id','parent_user_id','email','phone','credential_digest','receipt_reference','hours_json','image_id','total_cap','redeemed_count'] as $private)if(array_key_exists($private,$row))throw new RuntimeException('Community is temporarily unavailable.',503);
    }
}
function communityClient(): CommunityClient
{
    static $client;CommunityClient::diagnostic('website.client.resolve',8);return $client??=new CommunityClient((array)config('community',[]));
}
/** Request-only memoization; never retain a positive result between requests. */
function communityAvailability(): ?bool
{
    static $loaded=false,$value=null;if($GLOBALS['community_connector_failed']??false)return null;CommunityClient::diagnostic('website.availability.start',3);
    if(!$loaded){$loaded=true;try{$value=communityClient()->request('availability')['available'];}catch(Throwable){CommunityClient::diagnostic('website.availability.failed',1,'error');$value=null;}}
    return $value;
}
function communityNoStore(): void
{
    CommunityClient::diagnostic('website.cache.headers',8);header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
}
