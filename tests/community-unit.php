<?php
declare(strict_types=1);
require __DIR__.'/../includes/bootstrap.php';require __DIR__.'/../includes/community-view.php';
function check(bool $value,string $label):void{if(!$value)throw new RuntimeException($label);}
function reject(callable $work,int $code):void{try{$work();}catch(RuntimeException $e){check($e->getCode()===$code,'Correct safe rejection');return;}throw new RuntimeException('Expected rejection');}
check(communityState(['q'=>'Bakery','category'=>'2','page'=>'2','return'=>'https://evil.test'])===['q'=>'Bakery','category'=>'2','page'=>'2'],'Bounded allowlisted context');
foreach([['q'=>['a']],['page'=>'0'],['page_size'=>'1000'],['view'=>'owners'],['business'=>'1','promotion'=>'2'],['q'=>str_repeat('x',151)]] as $input)reject(fn()=>communityState($input),422);
check(!str_contains(communityLink(['q'=>'<script>']),'<script>'),'Safe query encoding');
check(communityRoot(['promotion'=>'1','from'=>'business','view'=>'promotions','page'=>'2'])===['view'=>'promotions','page'=>'2'],'Root context not recursive');
CommunityClient::validate('availability',['available'=>true]);reject(fn()=>CommunityClient::validate('availability',['available'=>'true']),503);
reject(fn()=>CommunityClient::validate('businesses',['rows'=>[['id'=>1,'name'=>'Private','owner_user_id'=>1]],'total'=>1,'page'=>1,'page_size'=>25]),503);
reject(fn()=>CommunityClient::validate('promotions/1',['promotion'=>['id'=>1,'name'=>'Offer','business_id'=>2],'business'=>['id'=>3,'name'=>'Wrong']]),503);
check(communityCounters(['counters'=>['remaining'=>0]])==='<p class="community-counters">Remaining: 0</p>','Chosen zero visible');check(communityCounters(['counters'=>[]])==='','No hidden counters');
ob_start();communityBusiness(['id'=>1,'name'=>'<script>alert(1)</script>','description'=>'Plain','category'=>'Food','hours'=>['mon'=>['mode'=>'not_supplied']],'maps_url'=>'javascript:alert(1)']);$html=ob_get_clean();check(!str_contains($html,'<script>')&&!str_contains($html,'iframe')&&!str_contains($html,'Opening hours'),'Escape and omit unspecified hours/unsafe map');
ob_start();communityPager(['total'=>0,'page_size'=>25,'page'=>1],[]);check(ob_get_clean()==='','Hide empty pagination');
ob_start();communityPager(['total'=>26,'page_size'=>25,'page'=>1],[]);$html=ob_get_clean();check(str_contains($html,'Page 1 of 2')&&!str_contains($html,'26 records'),'Count separate from page status');
foreach(['http://example.test','https://user:secret@example.test','https://example.test/?q=1'] as $base)reject(fn()=>(new CommunityClient(['portal_base_url'=>$base]))->request('availability'),503);
reject(fn()=>(new CommunityClient([]))->request('../users'),422);
$rewrites=file_get_contents(__DIR__.'/../.htaccess');check(!str_contains($rewrites,'^(community|event|events|pa-members|subscribe|terms|privacy|festival-of-peace-2026)\\.'),'Raw PHP reaches availability guard before canonical redirect');check(str_contains($rewrites,'^(community|event|events|pa-members|subscribe|terms|privacy|festival-of-peace-2026)$'),'Clean Community rewrite exists');
echo "Community website unit checks passed: bounded context/transport/schema, privacy, escaped content/maps/hours and pagination.\n";
