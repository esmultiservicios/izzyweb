<?php
require __DIR__.'/bootstrap.php';
require_permission('health.view');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo=db();
$s=settings();
$checks=[];
$checks[]=['Logo configured',!empty($s['admin_logo_path']),'Upload the admin logo in Settings.','settings.php'];
$checks[]=['Favicon configured',!empty($s['favicon_path']),'Upload a browser tab icon.','settings.php'];
$checks[]=['WhatsApp enabled',($s['whatsapp_enabled']??'0')==='1','Enable floating WhatsApp.','settings.php'];
$filterAvailable=function_exists('filter_var')&&defined('FILTER_VALIDATE_EMAIL');
$checks[]=['Public email configured',$filterAvailable&&filter_var($s['email']??'',FILTER_VALIDATE_EMAIL)!==false,'Add a valid public email.','settings.php'];
$emailOk=(int)$pdo->query('SELECT COUNT(*) FROM correo WHERE estado=1')->fetchColumn()>0;
$checks[]=['Email delivery configured',$emailOk,'Configure and test SMTP or Microsoft Graph.','email.php'];
$areas=(int)$pdo->query('SELECT COUNT(*) FROM service_areas WHERE active=1')->fetchColumn();
$checks[]=['Service areas published',$areas>0,'Add at least one service area.','areas.php'];
$missingCovers=(int)$pdo->query("SELECT COUNT(*) FROM gallery WHERE active=1 AND (image_path IS NULL OR image_path='')")->fetchColumn();
$checks[]=['Published projects use cover images',$missingCovers===0,$missingCovers.' published project(s) need a cover image.','gallery.php'];
$seoOk=strlen(trim($s['seo_title']??''))>10&&strlen(trim($s['seo_description']??''))>40;
$checks[]=['SEO basics configured',$seoOk,'Review title and meta description.','seo.php'];
$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
$checks[]=['HTTPS detected',$https,'Production should run over HTTPS.','#'];

$pdoDrivers=class_exists('PDO')?PDO::getAvailableDrivers():[];
$activePhpSeries=PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
$easyApache='WHM > Software > EasyApache 4 > Customize > PHP Extensions';
$requirements=[
    [
        'name'=>'PHP 8+',
        'available'=>version_compare(PHP_VERSION,'8.0.0','>='),
        'detail'=>'Detected PHP '.PHP_VERSION.'.',
        'purpose'=>'Runs the CMS with supported language features, security fixes and predictable typing.',
        'whm'=>'WHM > Software > MultiPHP Manager > select the domain > choose PHP 8.0 or newer > Apply.',
    ],
    [
        'name'=>'PDO',
        'available'=>class_exists('PDO')&&extension_loaded('pdo'),
        'detail'=>class_exists('PDO')?'PDO core is loaded.':'PDO core is not loaded.',
        'purpose'=>'Provides the secure database abstraction used by the installer and CMS.',
        'whm'=>$easyApache.' > search PDO for PHP '.$activePhpSeries.' > enable the matching package > Review > Provision.',
    ],
    [
        'name'=>'PDO MySQL',
        'available'=>extension_loaded('pdo_mysql')&&in_array('mysql',$pdoDrivers,true),
        'detail'=>in_array('mysql',$pdoDrivers,true)?'MySQL is an available PDO driver.':'The MySQL PDO driver is unavailable.',
        'purpose'=>'Connects PDO to MySQL or MariaDB databases.',
        'whm'=>$easyApache.' > search mysqlnd for PHP '.$activePhpSeries.' > enable the matching MySQL/PDO package > Review > Provision.',
    ],
    [
        'name'=>'OpenSSL',
        'available'=>extension_loaded('openssl')&&function_exists('openssl_encrypt')&&function_exists('openssl_decrypt'),
        'detail'=>extension_loaded('openssl')?'OpenSSL encryption functions are loaded.':'OpenSSL is not loaded.',
        'purpose'=>'Encrypts stored SMTP and Microsoft Graph secrets and supports secure connections.',
        'whm'=>$easyApache.' > search openssl for PHP '.$activePhpSeries.' > enable the available OpenSSL package > Review > Provision.',
    ],
    [
        'name'=>'cURL',
        'available'=>extension_loaded('curl')&&function_exists('curl_init'),
        'detail'=>extension_loaded('curl')?'cURL requests are available.':'cURL is not loaded.',
        'purpose'=>'Sends HTTPS requests required by Microsoft Graph and external integrations.',
        'whm'=>$easyApache.' > search curl for PHP '.$activePhpSeries.' > enable the matching cURL package > Review > Provision.',
    ],
    [
        'name'=>'Fileinfo',
        'available'=>extension_loaded('fileinfo')&&class_exists('finfo')&&defined('FILEINFO_MIME_TYPE'),
        'detail'=>class_exists('finfo')?'Secure MIME inspection is available.':'The finfo class is unavailable.',
        'purpose'=>'Validates the real MIME type of uploaded images, videos and documents.',
        'whm'=>$easyApache.' > search fileinfo for PHP '.$activePhpSeries.' > enable the matching Fileinfo package > Review > Provision.',
    ],
    [
        'name'=>'ZIP / ZipArchive',
        'available'=>extension_loaded('zip')&&class_exists('ZipArchive'),
        'detail'=>class_exists('ZipArchive')?'ZipArchive is available.':'The ZipArchive class is unavailable.',
        'purpose'=>'Creates and restores complete CMS backup archives.',
        'whm'=>$easyApache.' > search zip for PHP '.$activePhpSeries.' > enable the matching ZIP package > Review > Provision.',
    ],
    [
        'name'=>'JSON',
        'available'=>function_exists('json_encode')&&function_exists('json_decode'),
        'detail'=>function_exists('json_encode')?'JSON encoding and decoding are available.':'JSON functions are unavailable.',
        'purpose'=>'Processes API payloads, settings metadata, notifications and asynchronous responses.',
        'whm'=>'JSON is included with PHP 8. If missing, use WHM > Software > EasyApache 4 to repair or rebuild the active PHP '.$activePhpSeries.' profile.',
    ],
    [
        'name'=>'Session',
        'available'=>extension_loaded('session')&&function_exists('session_start'),
        'detail'=>extension_loaded('session')?'PHP sessions are available.':'PHP sessions are unavailable.',
        'purpose'=>'Maintains authenticated administrator sessions, CSRF tokens and flash messages.',
        'whm'=>$easyApache.' > search session or common for PHP '.$activePhpSeries.' > enable the matching package > Review > Provision.',
    ],
    [
        'name'=>'Filter',
        'available'=>extension_loaded('filter')&&$filterAvailable,
        'detail'=>function_exists('filter_var')?'Input filtering functions are available.':'Input filtering functions are unavailable.',
        'purpose'=>'Validates email addresses and other structured input safely.',
        'whm'=>'Filter is included with PHP 8. If missing, use WHM > Software > EasyApache 4 to repair or rebuild the active PHP '.$activePhpSeries.' profile.',
    ],
    [
        'name'=>'Hash',
        'available'=>extension_loaded('hash')&&function_exists('hash')&&function_exists('hash_hmac')&&function_exists('hash_equals'),
        'detail'=>function_exists('hash_hmac')?'Hash and HMAC functions are available.':'Hash functions are unavailable.',
        'purpose'=>'Protects login tokens, sessions, two-factor authentication and integrity comparisons.',
        'whm'=>'Hash is included with PHP 8. If missing, use WHM > Software > EasyApache 4 to repair or rebuild the active PHP '.$activePhpSeries.' profile.',
    ],
];

$missingRequirements=array_values(array_filter($requirements,static fn(array $requirement):bool=>!$requirement['available']));
$availableRequirements=count($requirements)-count($missingRequirements);
$serverReady=count($missingRequirements)===0;
$passed=count(array_filter($checks,static fn(array $check):bool=>$check[1]));
$score=(int)round($passed/count($checks)*100);
$rechecked=isset($_GET['recheck']);
$checkedAt=date('Y-m-d H:i:s T');

$pageTitle='Website Health';
$active='health';
require __DIR__.'/_header.php';
?>
<div class="page-heading health-page-heading">
<div>
<p class="eyebrow">WEBSITE HEALTH</p>
<h1>Site and server readiness</h1>
<p class="muted">Live checks for website setup and the PHP capabilities this CMS actually uses.</p>
</div>
<form method="get" action="health.php" class="health-recheck-form" data-server-recheck>
<input type="hidden" name="recheck" value="1">
<input type="hidden" name="_" value="<?=time()?>" data-recheck-token>
<button type="submit" class="button" data-recheck-button><span aria-hidden="true">↻</span> <span data-recheck-label>Recheck server</span></button>
</form>
</div>

<?php if(!$serverReady): ?>
<div class="health-requirements-alert" role="alert">
<span aria-hidden="true">!</span>
<div>
<strong><?=count($missingRequirements)?> required server component<?=count($missingRequirements)===1?' is':'s are'?> missing</strong>
<p>Enable <?=h(implode(', ',array_column($missingRequirements,'name')))?> using the WHM instructions below. Features that depend on them may remain unavailable until the server is rechecked.</p>
</div>
</div>
<?php elseif($rechecked): ?>
<div class="health-requirements-alert is-ready" role="status">
<span aria-hidden="true">✓</span>
<div>
<strong>Server requirements rechecked successfully</strong>
<p>All required PHP components are available.</p>
</div>
</div>
<?php endif; ?>

<section class="server-health-panel">
<div class="server-health-heading">
<div>
<p class="eyebrow">PHP REQUIREMENTS</p>
<h2>Server capability check</h2>
<p>Checked directly on this request at <?=h($checkedAt)?>. Installing an extension and pressing Recheck server refreshes these results immediately.</p>
</div>
<div class="server-health-summary <?=$serverReady?'is-ready':'has-missing'?>">
<strong><?=$availableRequirements?> / <?=count($requirements)?></strong>
<span><?=$serverReady?'All available':'Requirements available'?></span>
</div>
</div>

<div class="php-requirements-grid">
<?php foreach($requirements as $requirement): ?>
<article class="php-requirement-card <?=$requirement['available']?'is-available':'is-missing'?>">
<header>
<div>
<span class="requirement-indicator" aria-hidden="true"><?=$requirement['available']?'✓':'!'?></span>
<strong><?=h($requirement['name'])?></strong>
</div>
<span class="requirement-status"><?=$requirement['available']?'Available':'Missing'?></span>
</header>
<p class="requirement-detail"><?=h($requirement['detail'])?></p>
<div class="requirement-purpose">
<strong>What it does</strong>
<p><?=h($requirement['purpose'])?></p>
</div>
<div class="requirement-whm">
<strong>Enable in WHM</strong>
<p><?=h($requirement['whm'])?></p>
</div>
</article>
<?php endforeach; ?>
</div>
</section>

<div class="health-hero">
<div class="health-score">
<strong><?=$score?>%</strong>
<span><?=$passed?> of <?=count($checks)?> website checks passed</span>
</div>
<div class="health-meter" role="progressbar" aria-label="Website readiness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$score?>">
<i style="width:<?=$score?>%"></i>
</div>
</div>
<div class="health-grid">
<?php foreach($checks as $check): ?>
<article class="health-card <?=$check[1]?'ok':'warn'?>">
<span><?=$check[1]?'✓':'!'?></span>
<div>
<strong><?=h($check[0])?></strong>
<p><?=h($check[2])?></p>
</div>
<?php if($check[3]!=='#'): ?>
<a href="<?=h($check[3])?>">Fix →</a>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
