<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';communityNoStore();
try{
    CommunityClient::diagnostic('website.media.start',1);
    $available=communityAvailability();if($available!==true)throw new RuntimeException('Image unavailable.',$available===false?404:503);
    $kind=$_GET['kind']??'';$id=$_GET['id']??'';
    if(!is_string($kind)||!in_array($kind,['businesses','promotions'],true)||!is_string($id)||!preg_match('/^[1-9][0-9]{0,8}$/D',$id))throw new RuntimeException('Image unavailable.',404);
    $image=communityClient()->request($kind.'/'.$id.'/image',[],true);header('Content-Type: '.$image['mime_type']);header("Content-Security-Policy: default-src 'none'; sandbox");echo $image['bytes'];CommunityClient::diagnostic('website.media.complete',1);
}catch(Throwable $error){$code=$error->getCode()===404?404:503;http_response_code($code);header('Content-Type: text/plain; charset=utf-8');CommunityClient::diagnostic('website.media.failed',1,$code===503?'error':'warning');echo 'Image unavailable.';}
