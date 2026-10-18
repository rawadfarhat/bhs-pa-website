<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/community-view.php';
communityNoStore();$pageTitle='Community | BHS Parents Association';$activePage='community';$failure=null;$filters='';$state=[];$clamped=false;$business=null;$promotion=null;$data=null;
try{
    CommunityClient::diagnostic('website.page.start',1);$available=communityAvailability();if($available!==true)throw new RuntimeException($available===false?'Community is unavailable.':'Community is temporarily unavailable.',$available===false?404:503);
    $state=communityState($_GET);if(isset($state['promotion'])&&!isset($state['view']))$state['view']='promotions';$root=communityRoot($state);$kind=$state['view']??'businesses';
    if(isset($state['promotion'])){$detail=communityClient()->request('promotions/'.$state['promotion']);$promotion=$detail['promotion'];$business=$detail['business'];}
    elseif(isset($state['business'])){
        $business=communityClient()->request('businesses/'.$state['business']);
        if(isset($state['origin_promotion'])){$origin=communityClient()->request('promotions/'.$state['origin_promotion']);if((int)$origin['business']['id']!==(int)$business['id'])throw new RuntimeException('Community listing unavailable.',404);}
        $data=communityClient()->request('promotions',['business'=>$business['id'],'page'=>$state['related_page']??1,'page_size'=>$state['page_size']??25,'search'=>$state['related_q']??'']);$clamped=(int)($state['related_page']??1)!==$data['page'];$state['related_page']=(string)$data['page'];
    }else{$data=communityClient()->request($kind,['page'=>$state['page']??1,'page_size'=>$state['page_size']??25,'search'=>$state['q']??'','category'=>$state['category']??'','locality'=>$state['locality']??'','business'=>$state['filter_business']??'']);$clamped=(int)($state['page']??1)!==$data['page'];$state['page']=(string)$data['page'];}
    // Prepare filter lookups before any catalog output so an outage cannot expose stale/partial results.
    ob_start();if(!$promotion)communityFilters($state,$business!==null,$data['total']>$data['page_size']);$filters=(string)ob_get_clean();CommunityClient::diagnostic('website.page.prepared',1);
}catch(Throwable $error){if(ob_get_level()>0)ob_end_clean();$code=in_array($error->getCode(),[404,422],true)?$error->getCode():503;http_response_code($code);if($code===503)$GLOBALS['community_connector_failed']=true;$failure=$code===422?'Invalid Community filters.':($code===404?'This Community page is unavailable.':'Community is temporarily unavailable. Please try again.');CommunityClient::diagnostic('website.page.failed',1,$code===503?'error':'warning');}
require __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(url('assets/community.css?v=3')) ?>">
<section class="page-hero"><div class="shell"><p class="eyebrow">BHS Parents Community</p><h1>Community</h1><p>Discover businesses and offers from our parent community.</p></div></section>
<section class="section community-section" data-community-page data-status="<?= http_response_code() ?: 200 ?>"><div class="shell" id="communityContent" aria-live="polite">
<?php if($failure): ?>
<div class="event-card"><h2><?= e($failure) ?></h2><?php if(communityAvailability()===true): ?><a class="text-link" href="<?= e(communityLink(communityRoot($state))) ?>">← Back to <?= e(($state['view']??'businesses')==='promotions'?'promotions':'businesses') ?></a><?php endif; ?> <a class="text-link" href="<?= e(url()) ?>">Back to home</a></div>
<?php else:
if($clamped)echo '<p role="status">The requested page is no longer available. Showing the last matching page.</p>';$root=communityRoot($state);$rootLabel=($root['view']??'businesses')==='promotions'?'promotions':'businesses';
$back=communityLink($root);$backLabel='Back to '.$rootLabel;
if($promotion&&($state['from']??'')==='business'){$back=communityLink($state,['promotion'=>null,'business'=>$business['id'],'from'=>null]);$backLabel='Back to '.$business['name'];}
elseif($business&&!$promotion&&($state['from']??'')==='offer'&&isset($state['origin_promotion'])){$back=communityLink($state,['business'=>null,'promotion'=>$state['origin_promotion'],'origin_promotion'=>null,'from'=>null]);$backLabel='Back to selected promotion';}
?>
<?php if($business): ?>
<nav class="community-back" aria-label="Community return links"><a class="text-link" href="<?= e($back) ?>">← <?= e($backLabel) ?></a><?php if($back!==communityLink($root)): ?><a href="<?= e(communityLink($root)) ?>">Back to <?= e($rootLabel) ?></a><?php endif; ?></nav>
<?php if($promotion): ?><section class="event-card community-selected-offer"><p class="eyebrow"><?= e($business['name']) ?></p><?= communityOffer($promotion,true) ?></section><?php endif; ?>
<?php communityBusiness($business); ?>
<?php if($promotion): ?><p><a class="button" href="<?= e(communityLink($state,['promotion'=>null,'business'=>$business['id'],'from'=>'offer','origin_promotion'=>$promotion['id']])) ?>">View all promotions at <?= e($business['name']) ?></a></p>
<?php else: ?><h2>Promotions at <?= e($business['name']) ?></h2><?= $filters ?><?php communityCards($data,$state,'promotions',(int)$business['id']); ?><?php endif; ?>
<p class="community-back"><a class="text-link" href="<?= e($back) ?>">← <?= e($backLabel) ?></a></p>
<?php else: ?>
<section class="community-intro" aria-labelledby="communityIntroTitle">
    <h2 id="communityIntroTitle">Discover. Connect. Support our community.</h2>
    <p>BHS Community brings together businesses owned by BHS parents, helping families discover local services and support one another. Browse the businesses below or explore special offers for our parent community.</p>
    <p>To redeem an offer, present your BHS Community Card at the participating business. You can access your card through the PA Portal. Each promotion’s terms and availability apply.</p>
    <p>Are you a BHS parent with a business? Log in to the PA Portal to submit your business and promotions for review.</p>
</section>
<nav class="community-tabs" aria-label="Browse Community"><a href="<?= e(communityLink($root,['view'=>'businesses','page'=>1])) ?>"<?= $kind==='businesses'?' aria-current="page"':'' ?>>Businesses</a><a href="<?= e(communityLink($root,['view'=>'promotions','page'=>1])) ?>"<?= $kind==='promotions'?' aria-current="page"':'' ?>>Promotions</a></nav>
<h2><?= ucfirst($kind) ?></h2><?= $filters ?><?php communityCards($data,$state,$kind); ?>
<?php endif; endif; ?>
</div></section>
<script src="<?= e(url('assets/community.js?v=2')) ?>" defer></script>
<?php require __DIR__.'/includes/footer.php'; ?>
