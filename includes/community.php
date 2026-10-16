<?php
declare(strict_types=1);
// Reuse the central diagnostics classes, with website configuration only. No portal config or DB is loaded.
$communityRuntime=rtrim((string)config('portal.root_path',''),'/\\');
foreach(['Core/Config','Services/LoggingService','Services/DebugRuntime'] as $communityClass){
    $communityFile=$communityRuntime.'/src/'.$communityClass.'.php';
    if(is_file($communityFile))require_once $communityFile;
}
if(class_exists(\App\Core\Config::class)&&class_exists(\App\Services\LoggingService::class)&&class_exists(\App\Services\DebugRuntime::class)){
    \App\Core\Config::load(dirname(__DIR__).'/config.php');\App\Services\DebugRuntime::start($_SERVER['SCRIPT_FILENAME']??__FILE__);
}
require_once __DIR__.'/CommunityApiClient.php';
function communityClient(): \App\Services\CommunityApiClient
{
    if(config('community.enabled',false)!==true||!class_exists(\App\Services\LoggingService::class))throw new RuntimeException('Community unavailable',503);
    return new \App\Services\CommunityApiClient((string)config('community.portal_public_api_base_url',''),config('app.env')==='staging'&&config('community.allow_loopback_http',false)===true);
}
function communityNoStore(): void {header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');}
function communitySafeUrl(mixed $value,array $schemes=['https','http']): ?string
{
    if(class_exists(\App\Services\LoggingService::class))\App\Services\LoggingService::trace('community-api-client','view.url.validate',__LINE__,8);
    if(!is_string($value)||strlen($value)>2048||preg_match('/[\x00-\x20\x7f]/',$value)||!in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),$schemes,true)||parse_url($value,PHP_URL_USER)!==null||parse_url($value,PHP_URL_PASS)!==null)return null;
    if(in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true)&&!filter_var($value,FILTER_VALIDATE_URL))return null;
    return $value;
}
function communityDetailUrl(string $kind,string $id): string {return url('community-detail.php?kind='.rawurlencode($kind).'&id='.rawurlencode($id));}
function communityReportShell(): void {
    echo '<form id="community-concern" class="community-report" data-endpoint="'.e(url('community-concerns.php')).'"><h2>Report a concern</h2><p>Details are private to authorized administrators. Owners receive redacted notices. Do not include child details or sensitive documents.</p><label>Category<select name="category"><option value="incorrect_information">Incorrect information</option><option value="offer_unavailable">Offer unavailable</option><option value="conduct">Conduct</option><option value="other">Other</option></select></label><label>Concern details<textarea name="details" minlength="5" maxlength="1000" required></textarea></label><label>Optional email for follow-up<input name="contact" type="email" maxlength="190"></label><label><input name="consent" type="checkbox"> I consent to administrators using this email to follow up</label><input name="website" hidden tabindex="-1" autocomplete="off"><button class="button">Submit report</button><p role="status" aria-live="polite"></p></form>';
}
