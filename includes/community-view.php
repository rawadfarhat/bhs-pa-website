<?php
declare(strict_types=1);

function communityState(array $input): array
{
    CommunityClient::diagnostic('website.state.validate',8);$state=[];
    $allowed=['view','q','category','locality','filter_business','page','page_size','business','promotion','from','origin_promotion','related_page','related_q','category_page','category_search','locality_page','locality_search'];
    foreach($allowed as $key){if(!isset($input[$key])||$input[$key]==='')continue;if(!is_scalar($input[$key]))throw new RuntimeException('Invalid Community filters.',422);$value=(string)$input[$key];
        if(in_array($key,['view','from'],true)){if(!in_array($value,$key==='view'?['businesses','promotions']:['business','offer'],true))throw new RuntimeException('Invalid Community view.',422);}
        elseif(in_array($key,['q','locality','related_q','category_search','locality_search'],true)){if(mb_strlen($value)>150||preg_match('/[\x00-\x1f\x7f]/',$value))throw new RuntimeException('Invalid Community filters.',422);}
        else{if(!preg_match('/^[1-9][0-9]{0,8}$/D',$value))throw new RuntimeException('Invalid Community filters.',422);if($key==='page_size'&&!in_array((int)$value,[25,50,100],true))throw new RuntimeException('Invalid page size.',422);}
        $state[$key]=$value;
    }
    if(isset($state['business'],$state['promotion']))throw new RuntimeException('Choose one Community record.',422);
    return $state;
}
function communityLink(array $state,array $changes=[]): string
{
    CommunityClient::diagnostic('website.link.build',10);foreach($changes as $key=>$value){if($value===null||$value==='')unset($state[$key]);else $state[$key]=(string)$value;}
    return url('community').($state?'?'.http_build_query($state):'');
}
function communityRoot(array $state): array
{
    CommunityClient::diagnostic('website.context.root',8);return array_intersect_key($state,array_flip(['view','q','category','locality','filter_business','page','page_size']));
}
function communityImage(array $row,string $kind): string
{
    CommunityClient::diagnostic('website.image.link',10);return !empty($row['has_image'])?'<img class="community-image" src="'.e(url('community-image.php?kind='.$kind.'&id='.(int)$row['id'])).'" alt="'.e($row['name']).'" loading="lazy" decoding="async">':'';
}
function communityCounters(array $row): string
{
    CommunityClient::diagnostic('website.counters.render',8);$parts=[];foreach(($row['counters']??[]) as $label=>$value)$parts[]=ucfirst($label).': '.(int)$value;return $parts?'<p class="community-counters">'.e(implode(' · ',$parts)).'</p>':'';
}
function communityOffer(array $row,bool $detail=false): string
{
    CommunityClient::diagnostic('website.offer.render',8);
    return communityImage($row,'promotions').'<p class="eyebrow">'.e($row['availability']??'Offer').'</p><h2>'.e($row['name']).'</h2><p>'.e($row['sub_text']??'').'</p>'.($detail?'<p class="community-description">'.e($row['description']??'').'</p><p>'.(($row['redemption_mode']??'')==='once'?'Once per parent identity.':'Repeat redemptions permitted.').'</p>':'').'<p class="community-meta">From '.e($row['start_date']).(!empty($row['end_date'])?' to '.e($row['end_date']):' · No end date').'</p>'.communityCounters($row);
}
function communityBusiness(array $row): void
{
    CommunityClient::diagnostic('website.business.render',3);
    echo '<section class="event-card community-business"><div class="community-business-info"><div>'.communityImage($row,'businesses').'<h2>'.e($row['name']).'</h2><p>'.e($row['sub_text']??'').'</p><p class="community-description">'.e($row['description']??'').'</p></div><div><dl><dt>Category</dt><dd>'.e($row['category']??'').'</dd>';
    foreach(['locality'=>'Locality','address'=>'Address'] as $field=>$label)if(!empty($row[$field]))echo '<dt>'.$label.'</dt><dd>'.e($row[$field]).'</dd>';
    echo '</dl>';$hours=[];foreach(['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'] as $key=>$day){$item=$row['hours'][$key]??null;if(($item['mode']??'')==='closed')$hours[$day]='Closed';elseif(($item['mode']??'')==='open'&&!empty($item['intervals'])){$values=[];foreach($item['intervals'] as $interval)if(is_string($interval['open']??null)&&is_string($interval['close']??null))$values[]=$interval['open'].' – '.$interval['close'];if($values)$hours[$day]=implode(', ',$values);}}
    if($hours){echo '<h3>Opening hours</h3><dl>';foreach($hours as $day=>$value)echo '<dt>'.$day.'</dt><dd>'.e($value).'</dd>';echo '</dl>';}echo '</div></div>';
    $map=(string)($row['maps_url']??'');$parts=parse_url($map);$host=strtolower($parts['host']??'');
    $validMap=($parts['scheme']??'')==='https'&&in_array($host,['maps.google.com','www.google.com','google.com','maps.app.goo.gl','goo.gl'],true)&&!isset($parts['user'])&&!preg_match('/[\x00-\x20\x7f]/',$map);
    if($validMap||!empty($row['address'])){
        echo '<div class="community-map">';if($validMap)echo '<a class="text-link" href="'.e($map).'" target="_blank" rel="noopener noreferrer">Open in Google Maps ↗</a>';
        // Embed only a validated Google Maps query or CID. Never embed a visitor URL.
        parse_str($validMap?($parts['query']??''):'',$query);$location=$query['q']??$query['query']??null;$cid=$query['cid']??null;$embed=['output'=>'embed'];
        if(is_scalar($cid)&&preg_match('/^[0-9]{1,20}$/D',(string)$cid))$embed['cid']=(string)$cid;
        if(!$location&&!isset($embed['cid'])&&$validMap&&preg_match('~@(-?[0-9]+(?:\.[0-9]+)?),(-?[0-9]+(?:\.[0-9]+)?)~',$parts['path']??'',$coordinates)&&abs((float)$coordinates[1])<=90&&abs((float)$coordinates[2])<=180)$location=$coordinates[1].','.$coordinates[2];
        if(!$location&&!isset($embed['cid'])&&!empty($row['address'])){echo '<p class="community-meta">'.($validMap?'Map based on the business address. Use Open in Google Maps for its saved listing.':'Map based on the business address.').'</p>';$location=$row['address'];}
        if(is_string($location)&&$location!==''&&strlen($location)<=500)$embed['q']=$location;
        if(isset($embed['q'])||isset($embed['cid']))echo '<iframe title="Business location" loading="lazy" referrerpolicy="no-referrer" src="https://maps.google.com/maps?'.e(http_build_query($embed)).'"></iframe>';
        echo '</div>';
    }
    echo '</section>';
}
function communityPager(array $data,array $state,string $key='page'): void
{
    CommunityClient::diagnostic('website.pagination.render',8);$pages=max(1,(int)ceil($data['total']/$data['page_size']));if($pages<=1)return;$page=$data['page'];
    echo '<nav class="community-pagination" aria-label="'.($key==='related_page'?'Business promotions':'Community').' pages">';
    if($page===1)echo '<span class="button button-secondary" aria-disabled="true">Previous</span>';else echo '<a class="button button-secondary" href="'.e(communityLink($state,[$key=>$page-1])).'">Previous</a>';
    echo '<span>Page '.$page.' of '.$pages.'</span>';if($page===$pages)echo '<span class="button button-secondary" aria-disabled="true">Next</span>';else echo '<a class="button button-secondary" href="'.e(communityLink($state,[$key=>$page+1])).'">Next</a>';echo '</nav>';
}
function communityCards(array $data,array $state,string $kind,?int $business=null): void
{
    CommunityClient::diagnostic('website.cards.render',3);echo '<div class="community-results-toolbar">';communityPager($data,$state,$business?'related_page':'page');echo '<p class="community-meta" role="status">Total records: '.$data['total'].'</p></div><div class="event-list-grid community-grid">';
    foreach($data['rows'] as $row){CommunityClient::diagnostic('website.cards.row',10);$changes=['business'=>null,'promotion'=>null,'from'=>null,'origin_promotion'=>null];$changes[$kind==='businesses'?'business':'promotion']=$row['id'];if($business)$changes['from']='business';
        $href=communityLink($state,$changes);echo '<article class="event-card community-card"><a href="'.e($href).'">';
        if($kind==='businesses')echo communityImage($row,$kind).'<h2>'.e($row['name']).'</h2><p>'.e($row['sub_text']??'').'</p><p class="community-meta">'.e($row['category']??'').(!empty($row['locality'])?' · '.e($row['locality']):'').'</p>';
        else echo communityOffer($row).'<p>'.e($row['business']).'</p>';
        echo '</a></article>';
    }echo '</div>';if(!$data['rows'])echo '<p class="empty-state">No matching '.e($kind).'. Try changing your filters.</p>';
}
function communityLookup(string $kind,array $state): void
{
    CommunityClient::diagnostic('website.lookup.render',8);$key=$kind==='categories'?'category':'locality';$pageKey=$key.'_page';$searchKey=$key.'_search';$data=communityClient()->request('choices/'.$kind,['page'=>$state[$pageKey]??1,'search'=>$state[$searchKey]??'']);
    $selected=null;foreach($data['rows'] as $choice)if((string)$choice['id']===(string)($state[$key]??''))$selected=$choice['name'];if($selected!==null)$GLOBALS['community_filter_labels'][$key]=$selected;
    echo '<details class="community-lookup" data-community-lookup="'.$kind.'"><summary>'.e(isset($state[$key])?ucfirst($key).': '.($selected??$state[$key]):'All '.($kind==='categories'?'categories':'localities')).'</summary><div class="community-lookup-panel"><label>Search '.e($kind).'<input id="community-'.$searchKey.'" type="search" name="'.$searchKey.'" value="'.e($state[$searchKey]??'').'" form="communityFilters" data-choice-search></label><div class="community-choice-results">';
    echo '<a href="'.e(communityLink($state,[$key=>null,'page'=>1])).'">All '.e($kind).'</a>';foreach($data['rows'] as $row)echo '<a href="'.e(communityLink($state,[$key=>$row['id'],'page'=>1,$pageKey=>null,$searchKey=>null])).'">'.e($row['name']).'</a>';
    if(!$data['rows'])echo '<p>No matches</p>';echo '</div>';communityPager($data,$state,$pageKey);echo '</div></details>';
}
function communityFilters(array $state,bool $related=false,bool $multiplePages=false): void
{
    CommunityClient::diagnostic('website.filters.render',8);$q=$related?'related_q':'q';echo '<form id="communityFilters" class="community-filters" method="get" action="'.e(url('community')).'">';
    foreach($state as $key=>$value)if(!in_array($key,[$q,'page','related_page','page_size','category_search','locality_search','category_page','locality_page'],true))echo '<input type="hidden" name="'.e($key).'" value="'.e($value).'">';
    echo '<label class="community-search">Search '.($related?'promotions':'Community').'<input id="communitySearch" type="search" maxlength="150" name="'.$q.'" value="'.e($state[$q]??'').'" placeholder="Search names, descriptions…"></label>';if($multiplePages){echo '<label>Per page<select name="page_size">';foreach([25,50,100] as $size)echo '<option'.((int)($state['page_size']??25)===$size?' selected':'').'>'.$size.'</option>';echo '</select></label>';}else echo '<input type="hidden" name="page_size" value="'.(int)($state['page_size']??25).'">';echo '<noscript><button class="button" type="submit">Search</button></noscript></form>';
    if(!$related){echo '<div class="community-lookups">';communityLookup('categories',$state);communityLookup('localities',$state);echo '</div>';}
    echo '<div class="community-filter-chips">';foreach(['q'=>'Search','category'=>'Category','locality'=>'Locality','filter_business'=>'Business','related_q'=>'Search promotions'] as $key=>$label)if(isset($state[$key]))echo '<a href="'.e(communityLink($state,[$key=>null,'page'=>1,'related_page'=>1])).'">'.e($label.': '.($GLOBALS['community_filter_labels'][$key]??$state[$key])).' <span aria-hidden="true">×</span><span class="sr-only"> Remove filter</span></a>';echo '</div>';
}
