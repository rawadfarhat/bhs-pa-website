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
const SOCIAL_FIXTURE_URL = 'https://www.instagram.com/synthetic.fixture/';
$profiles = ['instagram'=>SOCIAL_FIXTURE_URL,'facebook'=>'','linkedin'=>'https://linkedin.com.evil.test/example','youtube'=>'javascript:alert(1)'];
$links = communitySocialLinks(['social_profiles'=>$profiles]);
check(str_contains($links,SOCIAL_FIXTURE_URL)&&str_contains($links,'aria-label="Instagram"')&&str_contains($links,'<rect ')&&substr_count($links,'<a ')===1,'Published icons omit empty and untrusted profiles');
check(communitySocialLinks(['social_profiles'=>[]])==='','No empty social profiles group');
$website = 'https://example.test/?a=1&b=2';
$links = communitySocialLinks(['website_url'=>$website,'social_profiles'=>$profiles]);
check(str_contains($links,'href="https://example.test/?a=1&amp;b=2" aria-label="Website"')&&strpos($links,'Website')<strpos($links,'Instagram'),'Approved website is escaped and shown before social links');
foreach (['','http://example.test','javascript:alert(1)','https://user@example.test','https://example.test\\contact',[]] as $invalid) {
    check(communitySocialLinks(['website_url'=>$invalid])==='','Empty and invalid website links are hidden');
}
$contactFixture = ['id'=>1,'name'=>'Fixture','website_url'=>$website,'business_phone'=>'+9613123456','social_profiles'=>[
    'instagram'=>SOCIAL_FIXTURE_URL,
    'facebook'=>'https://www.facebook.com/synthetic.fixture',
    'linkedin'=>'https://www.linkedin.com/company/synthetic-fixture',
    'youtube'=>'https://www.youtube.com/@SyntheticFixture',
    'whatsapp'=>'https://wa.me/9613123456',
]];
$catalog = ['rows'=>[$contactFixture],'total'=>1,'page'=>1,'page_size'=>25];
CommunityClient::validate('businesses/1',$contactFixture);
CommunityClient::validate('businesses',$catalog);
CommunityClient::validate('businesses/1',array_replace($contactFixture,['business_phone'=>'']));
reject(fn()=>CommunityClient::validate('businesses/1',$contactFixture+['phone'=>'+9613123456']),503);
reject(fn()=>CommunityClient::validate('businesses/1',array_replace($contactFixture,['business_phone'=>'tel:+9613123456'])),503);

ob_start();communityBusiness($contactFixture);$detail=ob_get_clean();
ob_start();communityCards($catalog,[],'businesses');$cards=ob_get_clean();
foreach ([$detail,$cards] as $html) {
    foreach (['Website'=>e($website),'Call +9613123456'=>'tel:+9613123456','Instagram'=>SOCIAL_FIXTURE_URL,
        'Facebook'=>$contactFixture['social_profiles']['facebook'],'LinkedIn'=>$contactFixture['social_profiles']['linkedin'],
        'YouTube'=>$contactFixture['social_profiles']['youtube'],'WhatsApp'=>'https://wa.me/9613123456'] as $label=>$href) {
        check(str_contains($html,'href="'.$href.'" aria-label="'.$label.'"'),'Card and detail show approved '.$label);
    }
}
check(communitySocialLinks(['business_phone'=>'','website_url'=>'','social_profiles'=>['whatsapp'=>'']])==='','Blank contacts leave no placeholder');
check(communitySocialLinks(['business_phone'=>'javascript:alert(1)','phone'=>'+9613123456'])==='','Invalid and private phone values stay hidden');
foreach (['https://wa.me/','https://wa.me.evil.test/9613123456','https://private@wa.me/9613123456','https://api.whatsapp.com:443/send?phone=9613123456'] as $invalid) {
    check(communitySocialLinks(['social_profiles'=>['whatsapp'=>$invalid]])==='','Unsafe or empty WhatsApp destinations stay hidden');
}
check(str_contains(communitySocialLinks(['social_profiles'=>['whatsapp'=>'https://api.whatsapp.com/send?phone=9613123456']]),'aria-label="WhatsApp"'),'Existing API WhatsApp links still work');
echo "Community website unit checks passed: bounded context/transport/schema, privacy, escaped content/maps/hours and pagination.\n";
