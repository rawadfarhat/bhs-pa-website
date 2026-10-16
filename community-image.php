<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';require __DIR__.'/includes/community.php';communityNoStore();
try{
 $kind=$_GET['kind']??'';$id=$_GET['id']??'';$key=$_GET['key']??'';
 if(!is_string($kind)||!in_array($kind,['businesses','promotions'],true)||!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Not found',404);
 $bytes=communityClient()->request('/'.$kind.'/'.$id.'/image/'.$key,[],null,true);header('Content-Type: image/png');header('Content-Disposition: inline');echo $bytes;
}catch(Throwable $error){if(class_exists(\App\Services\LoggingService::class))\App\Services\LoggingService::caught('community-api-client','image.failed',__LINE__,'warning',$error);http_response_code($error->getCode()===404?404:503);header('Content-Type: text/plain');echo 'Image unavailable';}
