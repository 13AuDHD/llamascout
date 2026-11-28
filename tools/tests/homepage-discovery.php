<?php
declare(strict_types=1);

$homepageLinks = [
    'places' => [['name'=>'Camp <Test> & RV','slug'=>'camp-test','city'=>'Town','state'=>'CO']],
    'resources' => [['name'=>'Water fill','slug'=>'water-fill','city'=>'','state'=>'NM']],
];
ob_start();
require dirname(__DIR__,2).'/partials/homepage-discovery.php';
$html=ob_get_clean();
$assert=static function(bool $ok,string $message):void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
};
$assert(str_contains($html,'href="/place.php?slug=camp-test"'),'Place link is an ordinary HTML anchor');
$assert(str_contains($html,'href="/resource.php?slug=water-fill"'),'Resource link is an ordinary HTML anchor');
$assert(str_contains($html,'Camp &lt;Test&gt; &amp; RV'),'Database values escaped');
$assert(!str_contains($html,'Camp <Test>'),'Raw database markup not rendered');
$assert(str_contains($html,'href="/field-guides/dispersed-camping-rules-you-need-to-know"'),'Field guide link present');
$homepageLinks=['places'=>[],'resources'=>[]];
ob_start();
require dirname(__DIR__,2).'/partials/homepage-discovery.php';
$empty=ob_get_clean();
$assert(!str_contains($empty,'id="home-directory-heading"'),'Empty directory omitted');
$assert(str_contains($empty,'Camping, overlanding and RV travel'),'Helpful content survives empty database result');
echo "PASS: homepage links, escaping, and empty results.\n";
