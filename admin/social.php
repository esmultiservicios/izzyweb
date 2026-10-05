<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('social.manage');
require_once __DIR__.'/../core/social.php';

$pdo=db();
$catalog=social_platform_catalog();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $size=(string)($_POST['social_size']??'medium');
        $style=(string)($_POST['social_style']??'icon_name');
        $location=(string)($_POST['social_location']??'footer');
        if(!in_array($size,['small','medium','large'],true))throw new RuntimeException('Select a valid social icon size.');
        if(!in_array($style,['icon','icon_name'],true))throw new RuntimeException('Select a valid social link style.');
        if(!in_array($location,['footer','below_hero','floating_left','floating_right','footer_floating_left','footer_floating_right'],true))throw new RuntimeException('Select a valid social network location.');
        $showDesktop=isset($_POST['social_show_desktop']);
        $showMobile=isset($_POST['social_show_mobile']);
        if(!$showDesktop&&!$showMobile)throw new RuntimeException('Enable social networks for desktop/tablet, mobile, or both.');

        $platforms=is_array($_POST['platform']??null)?$_POST['platform']:[];
        $urls=is_array($_POST['url']??null)?$_POST['url']:[];
        $orders=is_array($_POST['sort_order']??null)?$_POST['sort_order']:[];
        $actives=is_array($_POST['active']??null)?$_POST['active']:[];
        if(count($platforms)!==5)throw new RuntimeException('The Core requires exactly five configurable social network rows.');
        $rows=[];
        $used=[];
        foreach($platforms as $index=>$rawPlatform) {
            $platform=strtolower(trim((string)$rawPlatform));
            $url=trim((string)($urls[$index]??''));
            $sortOrder=max(0,min(9999,(int)($orders[$index]??(($index+1)*10))));
            $active=isset($actives[$index])?1:0;
            if(!isset($catalog[$platform]))throw new RuntimeException('Choose a supported platform in row '.($index+1).'.');
            if(isset($used[$platform]))throw new RuntimeException($catalog[$platform]['label'].' is selected more than once.');
            $used[$platform]=true;
            if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$url))) {
                throw new RuntimeException('Enter a complete http:// or https:// URL in row '.($index+1).'.');
            }
            if($active&&$url==='')throw new RuntimeException('Add the URL before activating '.$catalog[$platform]['label'].'.');
            if(strlen($url)>700)throw new RuntimeException('The URL in row '.($index+1).' is too long.');
            $rows[]=['platform'=>$platform,'url'=>$url,'sort_order'=>$sortOrder,'active'=>$active];
        }

        $pdo->beginTransaction();
        save_setting('social_size',$size);
        save_setting('social_style',$style);
        save_setting('social_location',$location);
        save_setting('social_show_desktop',$showDesktop?'1':'0');
        save_setting('social_show_mobile',$showMobile?'1':'0');
        save_setting('social_configured','1');
        $pdo->exec('DELETE FROM social_links');
        $insert=$pdo->prepare('INSERT INTO social_links(platform,url,sort_order,active) VALUES(?,?,?,?)');
        foreach($rows as $row)$insert->execute([$row['platform'],$row['url']!==''?$row['url']:null,$row['sort_order'],$row['active']]);
        $pdo->commit();
        log_activity('social_networks_update','Updated public social network links and placement.',[
            'active_links'=>count(array_filter($rows,static fn(array $row):bool=>$row['active']===1)),
            'location'=>$location,
        ]);
        flash('success','Social networks updated.');
        header('Location: social.php');
        exit;
    } catch(Throwable $exception) {
        if($pdo->inTransaction())$pdo->rollBack();
        $error=$exception instanceof PDOException
            ?'Social networks could not be saved. Run database-update.sql and try again.'
            :$exception->getMessage();
    }
}

try {
    $links=$pdo->query('SELECT * FROM social_links ORDER BY sort_order,id')->fetchAll();
} catch(Throwable $schemaError) {
    $links=[];
    $error=$error?:'Social network storage is not available. Run database-update.sql first.';
}
$settings=settings();
$byPlatform=[];
foreach($links as $link)$byPlatform[(string)$link['platform']]=$link;
if((string)($settings['social_configured']??'0')!=='1') {
    foreach(social_default_links() as $defaultLink) {
        $platform=(string)$defaultLink['platform'];
        $existing=$byPlatform[$platform]??null;
        if(!$existing || (trim((string)($existing['url']??''))==='' && (int)($existing['active']??0)===0)) {
            $byPlatform[$platform]=[
                'platform'=>$platform,
                'url'=>$defaultLink['url'],
                'sort_order'=>$defaultLink['sort_order'],
                'active'=>1,
            ];
        }
    }
}
$rows=[];
foreach(array_keys($catalog) as $index=>$platform) {
    $rows[]=$byPlatform[$platform]??['platform'=>$platform,'url'=>'','sort_order'=>($index+1)*10,'active'=>0];
}

$display=social_display_config($settings);
$pageTitle='Redes sociales';
$active='social';
require __DIR__.'/_header.php';
?>

<div class="page-heading animate-in">
<div>
<p class="eyebrow">PUBLIC PRESENCE</p>
<h1>Redes sociales</h1>
<p class="muted">Configure five reusable social channels, their public order, presentation and responsive placement.</p>
</div>
<div class="heading-actions"><a class="button secondary" href="../?preview=1" target="_blank" rel="noopener"><?=icon('eye')?> Preview site</a></div>
</div>

<?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<form method="post" class="social-admin-form" data-social-form data-unsaved-form>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

<section class="panel animate-in">
<div class="panel-heading"><div class="panel-icon"><?=icon('social')?></div><div><h2>Public presentation</h2><p>Choose how and where the configured networks appear. Nothing is hardcoded in the public website.</p></div></div>
<div class="social-global-grid">
<label>Size<select name="social_size" data-social-setting>
<option value="small" <?=$display['size']==='small'?'selected':''?>>Small</option>
<option value="medium" <?=$display['size']==='medium'?'selected':''?>>Medium</option>
<option value="large" <?=$display['size']==='large'?'selected':''?>>Large</option>
</select></label>
<label>Style<select name="social_style" data-social-setting>
<option value="icon" <?=$display['style']==='icon'?'selected':''?>>Icon only</option>
<option value="icon_name" <?=$display['style']==='icon_name'?'selected':''?>>Icon + name</option>
</select></label>
<label>Location<select name="social_location" data-social-setting>
<option value="footer" <?=$display['location']==='footer'?'selected':''?>>Footer</option>
<option value="below_hero" <?=$display['location']==='below_hero'?'selected':''?>>Below Hero</option>
<option value="floating_left" <?=$display['location']==='floating_left'?'selected':''?>>Floating left</option>
<option value="floating_right" <?=$display['location']==='floating_right'?'selected':''?>>Floating right</option>
<option value="footer_floating_left" <?=$display['location']==='footer_floating_left'?'selected':''?>>Footer + floating left</option>
<option value="footer_floating_right" <?=$display['location']==='footer_floating_right'?'selected':''?>>Footer + floating right</option>
</select></label>
</div>
<div class="social-visibility-grid">
<label class="premium-switch"><input type="checkbox" name="social_show_desktop" value="1" <?=$display['desktop']?'checked':''?> data-social-setting><span class="switch-ui" aria-hidden="true"></span><span><b>Desktop and tablet</b><small>Show on larger viewports.</small></span></label>
<label class="premium-switch"><input type="checkbox" name="social_show_mobile" value="1" <?=$display['mobile']?'checked':''?> data-social-setting><span class="switch-ui" aria-hidden="true"></span><span><b>Mobile</b><small>Show on small screens.</small></span></label>
</div>
</section>

<section class="panel animate-in">
<div class="panel-heading"><div class="panel-icon"><?=icon('edit')?></div><div><h2>Configured networks</h2><p>Activate only complete links. Public output always follows the numeric order.</p></div></div>
<div class="social-row-head" aria-hidden="true"><span>#</span><span>Enabled</span><span>Platform</span><span>URL</span><span>Order</span></div>
<div class="social-admin-rows">
<?php foreach($rows as $index=>$row): $platform=(string)$row['platform']; ?>
<article class="social-admin-row" data-social-row>
<span class="social-row-number"><?=($index+1)?></span>
<label class="social-row-active" title="Enable network"><input type="checkbox" name="active[<?=$index?>]" value="1" <?=((int)($row['active']??0)===1)?'checked':''?> data-social-input><span aria-hidden="true"></span><b>Enabled</b></label>
<label class="social-row-field"><span>Platform</span><select name="platform[<?=$index?>]" data-social-platform data-social-input>
<?php foreach($catalog as $key=>$platformData): ?><option value="<?=h($key)?>" <?=$platform===$key?'selected':''?>><?=h($platformData['label'])?></option><?php endforeach; ?>
</select></label>
<label class="social-row-field social-url-field"><span>URL</span><input type="url" name="url[<?=$index?>]" maxlength="700" placeholder="https://" value="<?=h($row['url']??'')?>" data-social-url data-social-input></label>
<label class="social-row-field social-order-field"><span>Order</span><input type="number" name="sort_order[<?=$index?>]" min="0" max="9999" value="<?=(int)($row['sort_order']??(($index+1)*10))?>" data-social-order data-social-input></label>
</article>
<?php endforeach; ?>
</div>
</section>

<section class="panel animate-in social-preview-panel">
<div class="panel-heading"><div class="panel-icon"><?=icon('eye')?></div><div><h2>Live preview</h2><p>The preview updates before saving and uses the same local SVG icons as the public website.</p></div></div>
<div class="social-preview-stage" data-social-preview></div>
<?php foreach($catalog as $key=>$platformData): ?><template data-social-icon="<?=h($key)?>"><?=$platformData['icon']?></template><?php endforeach; ?>
</section>

<section class="panel animate-in social-save-panel">
    <div class="social-save-copy">
        <span class="social-save-icon" aria-hidden="true">✓</span>
        <div>
            <h2>Guardar configuración de redes sociales</h2>
            <p>Se guardarán juntos los enlaces, el orden, la visibilidad y la ubicación seleccionada.</p>
        </div>
    </div>
    <button type="submit">Guardar redes sociales</button>
</section>
</form>

<?php require __DIR__.'/_footer.php'; ?>
