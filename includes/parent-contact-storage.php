<?php
declare(strict_types=1);

/** Load the shared audited portal boundary; never fall back to direct writes. */
function savePublicParentContact(PDO $pdo, array $contact, array $children, string $source = 'public_website'): int
{
    if (!in_array($source, ['public_website', 'portal_registration'], true)) throw new InvalidArgumentException('Invalid contact source.');
    if(!class_exists(\App\Services\PublicRegistrationReviewService::class)){
        $websiteRoot=dirname(__DIR__);$base=trim((string)config('app.base_path',''),'/');
        $candidates=[rtrim((string)config('portal.root_path',''),'/\\')];
        if($base==='staging'||in_array(basename($websiteRoot),['staging','website_staging'],true))$candidates[]=dirname($websiteRoot).'/portal_staging';
        elseif($base==='')$candidates[]=$websiteRoot.'/portal';
        $candidates[]=dirname($websiteRoot).'/BHS-PA-Cashbox/bhs-pa-cashbox';
        $loaded=false;
        foreach(array_unique($candidates) as $root){
            if($root===''||!is_file($root.'/scripts/bootstrap.php')||!is_file($root.'/config/config.php'))continue;
            $settings=require $root.'/config/config.php';
            foreach(['host','port','database'] as $key){
                if((string)($settings['db'][$key]??($key==='port'?3306:''))!==(string)config('db.'.$key,$key==='port'?3306:''))continue 2;
            }
            require_once $root.'/scripts/bootstrap.php';$loaded=true;break;
        }
        if(!$loaded)throw new RuntimeException('Registration runtime is unavailable.');
    }
    return \App\Services\PublicRegistrationReviewService::intake($pdo,$contact,$children,$source);
}
