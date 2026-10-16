<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';
header('Content-Type: application/javascript; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$source=rtrim((string)config('portal.root_path',''),'/\\').'/public/assets/debug.js';
if(!is_file($source)){http_response_code(503);exit;}
$settings=config('debug',[]);$safe=[];foreach(['info','warning','error'] as $severity)$safe[$severity]=($settings[$severity]??false)===true;
$safe['level']=max(1,min(10,(int)($settings['level']??1)));$safe['entities']=['browser'=>($settings['entities']['browser']??false)===true];
echo 'window.__APP_DEBUG_CONFIG__='.json_encode($safe,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';';readfile($source);
