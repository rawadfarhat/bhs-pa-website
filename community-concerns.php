<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';require __DIR__.'/includes/community.php';communityNoStore();header('Content-Type: application/json');
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException('Method not allowed',405);
 if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')||(int)($_SERVER['CONTENT_LENGTH']??0)>8192)throw new RuntimeException('Invalid request',400);
 $origin=$_SERVER['HTTP_ORIGIN']??'';$expected=(string)config('community.website_origin','');if($expected===''||$origin!==$expected)throw new RuntimeException('Forbidden',403);
 $raw=file_get_contents('php://input',false,null,0,8193);if(strlen($raw)>8192)throw new RuntimeException('Invalid request',400);$input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
 if(!is_array($input)||array_diff(array_keys($input),['kind','public_id','category','details','contact','consent','request_id','website']))throw new RuntimeException('Invalid request',400);
 communityClient()->request('/concerns',[],$input);http_response_code(202);echo '{"accepted":true}';
}catch(Throwable $error){if(class_exists(\App\Services\LoggingService::class))\App\Services\LoggingService::caught('community-api-client','concern.failed',__LINE__,'warning',$error);http_response_code(in_array($error->getCode(),[400,403,405],true)?$error->getCode():503);echo '{"error":"Report unavailable"}';}
