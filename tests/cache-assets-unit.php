<?php
declare(strict_types=1);
function publicEvents(): array { return []; }
function communityAvailability(): bool { return false; }
function communityNoStore(): void {}
function e(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function url(string $path): string { return '/'.$path; }
ob_start();require __DIR__.'/../includes/header.php';$html=ob_get_clean();
foreach(['styles.css','site.js'] as $asset){
    $version=substr(hash_file('sha256',__DIR__.'/../assets/'.$asset),0,16);
    if(!str_contains($html,'assets/'.$asset.'?rev='.$version))throw new RuntimeException('Missing content fingerprint: '.$asset);
}
if(!str_contains(file_get_contents(__DIR__.'/../.htaccess'),'no-cache, max-age=0, must-revalidate'))throw new RuntimeException('Missing revalidation policy');
echo "Website cache checks passed: rendered CSS/JS use file-content fingerprints and Apache revalidates mutable code.\n";
