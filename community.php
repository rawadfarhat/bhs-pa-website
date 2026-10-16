<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';require __DIR__.'/includes/community.php';communityNoStore();
$pageTitle='BHS Community | BHS Parents Association';$activePage='community';$kind=$_GET['kind']??'businesses';$result=null;$problem=null;$filters=[];
try{
 if(!is_string($kind)||!in_array($kind,['businesses','promotions'],true))throw new RuntimeException('Invalid browse request',400);
 foreach(['page','search','category','locality','has_offers','business_public_id'] as $key){$v=$_GET[$key]??($key==='page'?'1':($key==='has_offers'?'0':''));if(!is_string($v)||strlen($v)>760)throw new RuntimeException('Invalid filters',400);$filters[$key]=$v;}$filters['page_size']=20;
 $result=communityClient()->request('/'.$kind,$filters);
}catch(Throwable $error){if(class_exists(\App\Services\LoggingService::class))\App\Services\LoggingService::caught('community-api-client','page.list.failed',__LINE__,'warning',$error);http_response_code($error->getCode()===400?400:503);$problem='Community listings are temporarily unavailable. Please try again later.';}
require __DIR__.'/includes/header.php'; ?>
<link rel="stylesheet" href="<?= e(url('assets/community.css')) ?>">
<section class="page-hero"><div class="shell"><p class="eyebrow">Our parent community</p><h1>BHS Community</h1><p>Discover parent businesses and current offers for eligible BHS families.</p></div></section>
<section class="section"><div class="shell"><form class="community-filters" method="get" action="<?= e(url('community.php')) ?>">
<?php if(!empty($filters['business_public_id'])): ?><input type="hidden" name="business_public_id" value="<?= e($filters['business_public_id']) ?>"><?php endif; ?>
<label>Browse<select name="kind"><option value="businesses"<?= $kind==='businesses'?' selected':'' ?>>Businesses</option><option value="promotions"<?= $kind==='promotions'?' selected':'' ?>>Current promotions</option></select></label>
<?php foreach(['search'=>'Search businesses, offers or categories','category'=>'Category name','locality'=>'Locality'] as $key=>$label): ?><label><?= e($label) ?><input type="search" name="<?= e($key) ?>" value="<?= e(is_string($_GET[$key]??null)?$_GET[$key]:'') ?>" maxlength="190"></label><?php endforeach; ?>
<label class="community-check"><input type="checkbox" name="has_offers" value="1"<?= ($_GET['has_offers']??'')==='1'?' checked':'' ?>>Businesses with current offers</label><button class="button">Search</button><a class="button button-secondary" href="<?= e(url('community.php')) ?>">Clear</a></form>
<?php if($problem): ?><p class="empty-state" role="status"><?= e($problem) ?></p><?php else: ?><p role="status">Total records: <?= (int)$result['total'] ?></p><p>Only currently effective promotions appear. Offer end times are exclusive.</p>
<div class="community-grid"><?php foreach($result['data'] as $row): ?><article class="community-card"><p class="eyebrow"><?= e($row['category']??'') ?></p><h2><a href="<?= e(communityDetailUrl($kind,$row['public_id'])) ?>"><?= e($row['name']) ?></a></h2><p><?= e($row['locality']??'') ?></p><p class="community-copy"><?= e($row['description']??'') ?></p><?php if(!empty($row['business_name'])): ?><p><?= e($row['business_name']) ?></p><?php endif; ?><a class="text-link" href="<?= e(communityDetailUrl($kind,$row['public_id'])) ?>">View <?= $kind==='businesses'?'business':'offer' ?></a></article><?php endforeach; ?></div>
<?php if(!$result['total']): ?><p class="empty-state">No matching published records.</p><?php endif; ?>
<nav class="community-pages" aria-label="Community pagination"><?php foreach([-1=>'Previous',1=>'Next'] as $delta=>$label):$p=$result['page']+$delta;if($p>=1&&($p-1)*$result['page_size']<$result['total']):$q=$filters;$q['kind']=$kind;$q['page']=$p; ?><a class="button button-secondary" href="<?= e(url('community.php?'.http_build_query($q))) ?>"><?= e($label) ?></a><?php endif;endforeach; ?><span>Page <?= (int)$result['page'] ?> of <?= max(1,(int)ceil($result['total']/$result['page_size'])) ?></span></nav>
<?php endif; ?></div></section><?php require __DIR__.'/includes/footer.php'; ?>
