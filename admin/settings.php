<?php
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');
$pdo=db();
$set=settings();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    try {
        if($action==='identity') {
            foreach(['admin_brand_name','phone','phone_digits','email','website','business_hours','developer_credit_text'] as $k)save_setting($k,trim((string)($_POST[$k]??'')));
            save_setting('site_language',in_array($_POST['site_language']??'en',['en','es'],true)?$_POST['site_language']:'en');
            if(!empty($_FILES['admin_logo']['name']))save_setting('admin_logo_path',upload_image($_FILES['admin_logo'],'branding','admin-logo',5));
            if(!empty($_FILES['favicon']['name']))save_setting('favicon_path',upload_image($_FILES['favicon'],'branding','favicon',3));
            if(isset($_POST['remove_favicon']))save_setting('favicon_path','');
            save_setting('developer_credit_enabled',isset($_POST['developer_credit_enabled'])?'1':'0');
            flash('success','Brand and contact settings saved.');
        } elseif($action==='system_access') {
            $systemUrl=trim((string)($_POST['system_access_url']??''));
            $systemLabel=trim((string)($_POST['system_access_label']??'Ingresar a IZZY'));
            if($systemUrl!==''&&(!filter_var($systemUrl,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$systemUrl)))throw new RuntimeException('Ingresa una URL válida para el sistema IZZY, incluyendo https://');
            if($systemLabel==='')$systemLabel='Ingresar a IZZY';
            save_setting('system_access_enabled',isset($_POST['system_access_enabled'])?'1':'0');
            save_setting('system_access_url',$systemUrl);
            save_setting('system_access_label',$systemLabel);
            save_setting('system_access_new_tab',isset($_POST['system_access_new_tab'])?'1':'0');
            flash('success','Acceso al sistema IZZY actualizado.');
        } elseif($action==='maintenance') {
            save_setting('maintenance_mode',isset($_POST['maintenance_mode'])?'1':'0');
            save_setting('maintenance_title',trim((string)($_POST['maintenance_title']??'')));
            save_setting('maintenance_text',trim((string)($_POST['maintenance_text']??'')));
            if(!empty($_FILES['maintenance_image']['name']))save_setting('maintenance_image_path',upload_image($_FILES['maintenance_image'],'maintenance','maintenance',8));
            if(isset($_POST['remove_maintenance_image']))save_setting('maintenance_image_path','');
            flash('success','Website status updated.');
        } elseif($action==='whatsapp') {
            save_setting('whatsapp_enabled',isset($_POST['whatsapp_enabled'])?'1':'0');
            save_setting('whatsapp_message',trim((string)($_POST['whatsapp_message']??'')));
            save_setting('whatsapp_position','left');
            flash('success','WhatsApp widget updated.');
        } elseif($action==='public_form') {
            $minimumCharacters=max(10,min(1000,(int)($_POST['form_message_min_characters']??30)));
            $minimumWords=max(2,min(100,(int)($_POST['form_message_min_words']??5)));
            $optionsEn=normalized_setting_lines((string)($_POST['form_referral_options_en']??''));
            $optionsEs=normalized_setting_lines((string)($_POST['form_referral_options_es']??''));
            if(!$optionsEn||!$optionsEs)throw new RuntimeException('Add at least one referral option in English and Spanish.');
            $optionKey=static fn(string $value):string=>function_exists('mb_strtolower')
                ?mb_strtolower(trim($value),'UTF-8')
                :strtolower(trim($value));
            if(!in_array('other',array_map($optionKey,$optionsEn),true))$optionsEn[]='Other';
            if(!in_array('otro',array_map($optionKey,$optionsEs),true))$optionsEs[]='Otro';

            $pdo->beginTransaction();
            foreach(['name','phone','service','referral','message'] as $field) {
                save_setting('form_required_'.$field,isset($_POST['form_required_'.$field])?'1':'0');
            }
            save_setting('form_message_min_characters',(string)$minimumCharacters);
            save_setting('form_message_min_words',(string)$minimumWords);
            save_setting('form_referral_options_en',implode("\n",$optionsEn));
            save_setting('form_referral_options_es',implode("\n",$optionsEs));
            $pdo->commit();
            log_activity('public_form_settings','Updated public request form requirements.');
            flash('success','Public form requirements saved.');
        } elseif($action==='analytics') {
            save_setting('analytics_tracking_enabled',isset($_POST['analytics_tracking_enabled'])?'1':'0');
            log_activity('anonymous_analytics_settings','Updated the anonymous website visit counter.',['enabled'=>isset($_POST['analytics_tracking_enabled'])]);
            flash('success','Private website visit settings saved.');
        } elseif($action==='form_antispam') {
            $minimumSeconds=max(0,min(30,(int)($_POST['form_minimum_seconds']??3)));
            $cooldownSeconds=max(0,min(3600,(int)($_POST['form_cooldown_seconds']??60)));
            $maximumPerHour=max(1,min(50,(int)($_POST['form_maximum_per_hour']??5)));
            $siteKey=trim((string)($_POST['turnstile_site_key']??''));
            $newSecret=trim((string)($_POST['turnstile_secret_key']??''));
            if(strlen($siteKey)>255||preg_match('/[\x00-\x1F\x7F]/',$siteKey))throw new RuntimeException('Enter a valid Turnstile Site Key.');
            if(strlen($newSecret)>255||preg_match('/[\x00-\x1F\x7F]/',$newSecret))throw new RuntimeException('Enter a valid Turnstile Secret Key.');
            $storedSecret=(string)($set['turnstile_secret_key']??'');
            if(isset($_POST['remove_turnstile_secret']))$storedSecret='';
            elseif($newSecret!=='')$storedSecret=secret_encrypt($newSecret);

            $pdo->beginTransaction();
            save_setting('form_antispam_enabled',isset($_POST['form_antispam_enabled'])?'1':'0');
            save_setting('form_block_sales_solicitation',isset($_POST['form_block_sales_solicitation'])?'1':'0');
            save_setting('form_minimum_seconds',(string)$minimumSeconds);
            save_setting('form_cooldown_seconds',(string)$cooldownSeconds);
            save_setting('form_maximum_per_hour',(string)$maximumPerHour);
            save_setting('turnstile_enabled',isset($_POST['turnstile_enabled'])?'1':'0');
            save_setting('turnstile_site_key',$siteKey);
            save_setting('turnstile_secret_key',$storedSecret);
            $pdo->commit();
            log_activity('public_form_protection','Updated public form anti-spam protection.',[
                'local_protection'=>isset($_POST['form_antispam_enabled']),
                'turnstile'=>isset($_POST['turnstile_enabled']),
            ]);
            if(isset($_POST['turnstile_enabled'])&&($siteKey===''||$storedSecret==='')) {
                flash('warning','Protection saved. Turnstile is enabled but incomplete, so public submissions will remain blocked until both keys are configured or Turnstile is disabled.');
            } else {
                flash('success','Public form protection saved.');
            }
        }
        $anchors=['system_access'=>'#system-access','maintenance'=>'#site-status','public_form'=>'#public-form','form_antispam'=>'#form-protection','analytics'=>'#private-visits'];
        header('Location: settings.php'.($anchors[$action]??''));
        exit;
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        $error=$e instanceof PDOException
            ?'The settings could not be saved. Confirm that database-update.sql has been applied.'
            :$e->getMessage();
    }
}
$set=settings();
$brandName=(string)($set['admin_brand_name']??'CMS Core Admin');
$resolvedAdminLogo = resolved_public_asset($set['admin_logo_path'] ?? '', default_admin_brand_logo($brandName));
$resolvedFavicon = resolved_public_asset($set['favicon_path'] ?? '', default_admin_brand_favicon($brandName));
$resolvedMaintenanceImage = resolved_public_asset($set['maintenance_image_path'] ?? '', '');
$pageTitle='Settings';
$active='settings';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">SETTINGS</p>
<h1>Brand, contact & website status</h1>
<p class="muted">Control the admin identity and important public website behavior from one place.</p>
</div>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>

<?php if(user_can('social.manage')): ?>
<a class="settings-module-link animate-in" href="social.php">
<span class="manage-icon"><?=icon('social')?></span>
<span><strong>Redes sociales</strong><small>Configure Instagram, Facebook, TikTok, YouTube and LinkedIn with responsive public placement.</small></span>
<b>Open module →</b>
</a>
<?php endif; ?>

<div class="content-grid">
<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('image')?>
</div>
<div>
<h2>Admin branding</h2>
<p>Change the name and logo displayed throughout this administrator.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="identity">
<label>Admin name<input name="admin_brand_name" value="<?=h($set['admin_brand_name']??"CMS Core Admin")?>">
</label>
<label>Public content language<select name="site_language">
<option value="en" <?=($set['site_language']??'en')==='en'?'selected':''?>>English</option>
<option value="es" <?=($set['site_language']??'en')==='es'?'selected':''?>>Español</option>
</select>
<small class="field-hint">Projects use the selected translation when available and fall back to the default-language fields.</small>
</label>
<div class="branding-upload-grid">
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Admin logo</strong>
<small data-upload-name>Drag & drop, paste, or choose image</small>
<input type="file" name="admin_logo" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
<div class="upload-zone favicon-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Browser tab icon (favicon)</strong>
<small data-upload-name>Use a square PNG, JPG or WebP · drag, paste, or choose</small>
<input type="file" name="favicon" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
</div>
<div class="branding-current-grid">
<div class="saved-image-row saved-brand-preview">
<?php if($resolvedAdminLogo!==''): ?>
<button class="current-media-preview current-media-preview--brand" type="button" data-preview-src="../<?=h($resolvedAdminLogo)?>" data-preview-caption="Current admin logo">
<img src="../<?=h($resolvedAdminLogo)?>" alt="Current admin logo">
<span>Click to preview</span>
</button>
<?php else: ?>
<div class="saved-image-empty">No admin logo saved yet.</div>
<?php endif; ?>
<div>
<strong>Current admin logo</strong>
<small><?=h($resolvedAdminLogo!==''?$resolvedAdminLogo:'No logo saved yet.')?></small>
</div>
</div>
<div class="saved-favicon saved-brand-preview">
<?php if($resolvedFavicon!==''): ?>
<button class="favicon-preview-btn" type="button" data-preview-src="../<?=h($resolvedFavicon)?>" data-preview-caption="Current favicon">
<img src="../<?=h($resolvedFavicon)?>" alt="Current favicon">
</button>
<?php else: ?>
<div class="saved-image-empty is-compact">No favicon</div>
<?php endif; ?>
<div>
<strong>Current browser tab icon</strong>
<small><?=h($resolvedFavicon!==''?$resolvedFavicon:'No favicon saved yet.')?></small>
</div>
<label class="premium-check">
<input type="checkbox" name="remove_favicon" value="1">
<span>Remove favicon</span>
</label>
</div>
</div>
<div class="two-col">
<label>Phone<input name="phone" value="<?=h($set['phone']??'')?>">
</label>
<label>Phone digits<input name="phone_digits" value="<?=h($set['phone_digits']??'')?>">
</label>
</div>
<label>Public email<input type="email" name="email" value="<?=h($set['email']??'')?>">
</label>
<label>Website<input name="website" value="<?=h($set['website']??'')?>">
</label>
<label>Business hours<textarea name="business_hours"><?=h($set['business_hours']??'')?>
</textarea>
</label>
<label class="premium-switch">
<input type="checkbox" name="developer_credit_enabled" <?=($set['developer_credit_enabled']??'0')==='1'?'checked':''?>
>
<span class="switch-ui">
</span>
<span>
<b>Developer credit in footer</b>
<small>Optional. Enable only when the site owner wants to display a discreet developer credit.</small>
</span>
</label>
<label>Developer credit text<input name="developer_credit_text" value="<?=h($set['developer_credit_text']??'')?>">
</label>
<div class="form-actions">
<button>Save identity & contact</button>
</div>
</form>
</section>
<section class="panel animate-in" id="system-access">
<div class="panel-heading">
<div class="panel-icon"><?=icon('link')?>
</div>
<div>
<h2>Acceso al sistema IZZY</h2>
<p>Configura el enlace visible en el menú público para que tus clientes entren al sistema productivo.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="system_access">
<label class="premium-switch">
<input type="checkbox" name="system_access_enabled" <?=($set['system_access_enabled']??'1')==='1'?'checked':''?>>
<span class="switch-ui"></span>
<span><b>Mostrar acceso al sistema</b><small>Aparece en el menú de escritorio y en el menú hamburguesa de móvil.</small></span>
</label>
<label>Texto del botón<input name="system_access_label" maxlength="60" value="<?=h($set['system_access_label']??'Ingresar a IZZY')?>" placeholder="Ingresar a IZZY"></label>
<label>URL del sistema<input type="url" name="system_access_url" value="<?=h($set['system_access_url']??'https://sistema.izzycloud.app/')?>" placeholder="https://sistema.izzycloud.app/"></label>
<label class="premium-switch">
<input type="checkbox" name="system_access_new_tab" <?=($set['system_access_new_tab']??'1')==='1'?'checked':''?>>
<span class="switch-ui"></span>
<span><b>Abrir en una pestaña nueva</b><small>Recomendado para que el cliente conserve abierta la página pública de IZZY.</small></span>
</label>
<div class="system-access-preview">
<span>Vista previa</span>
<a class="button secondary" href="<?=h(($set['system_access_url']??'')!==''?($set['system_access_url']??''):'#')?>" <?=($set['system_access_new_tab']??'1')==='1'?'target="_blank" rel="noopener"':''?>><?=icon('login')?> <?=h($set['system_access_label']??'Ingresar a IZZY')?></a>
</div>
<div class="form-actions"><button>Guardar acceso al sistema</button></div>
</form>
</section>
<section class="panel animate-in" id="site-status">
<div class="panel-heading">
<div class="panel-icon"><?=icon('eye')?>
</div>
<div>
<h2>Website status</h2>
<p>Temporarily replace the public site with a polished maintenance screen while you make changes.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="maintenance">
<label class="status-switch premium-switch">
<input type="checkbox" name="maintenance_mode" <?=($set['maintenance_mode']??'0')==='1'?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Maintenance mode</b>
<small>Visitors will see the maintenance screen when enabled.</small>
</span>
</label>
<label>Maintenance title<input name="maintenance_title" value="<?=h($set['maintenance_title']??'')?>">
</label>
<label>Maintenance message<textarea name="maintenance_text"><?=h($set['maintenance_text']??'')?>
</textarea>
</label>
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Maintenance image</strong>
<small data-upload-name>Drag & drop, paste, or choose an optional image</small>
<input type="file" name="maintenance_image" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div><?php
if(!empty($set['maintenance_image_path'])):
?>
<div class="saved-image-row">
<img src="../<?=h($resolvedMaintenanceImage)?>" alt="Maintenance preview">
<div>
<strong>Current maintenance image</strong>
<small><?=h($resolvedMaintenanceImage!==''?$resolvedMaintenanceImage:($set['maintenance_image_path']??''))?>
</small>
</div>
<label class="compact-check">
<input type="checkbox" name="remove_maintenance_image" value="1"> Remove image</label>
</div><?php
endif;
?>
<p class="secret-hint">When maintenance is active, opening the normal website shows the maintenance screen even if you are logged in. Use the preview button below to inspect the real site privately.</p>
<div class="form-actions">
<button>Save website status</button>
<a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview real site</a>
<a class="button ghost" href="../" target="_blank" rel="noopener">View public status</a>
</div>
</form>
</section>
<section class="panel wide animate-in">
<div class="panel-heading">
<div class="panel-icon">💬</div>
<div>
<h2>Floating WhatsApp contact</h2>
<p>A discreet floating contact button that respects the page content on desktop and mobile.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="whatsapp">
<label class="premium-switch">
<input type="checkbox" name="whatsapp_enabled" <?=($set['whatsapp_enabled']??'1')==='1'?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Floating WhatsApp</b>
<small>Show a compact WhatsApp contact button on the public website.</small>
</span>
</label>
<div class="two-col">
<label>Default message<textarea name="whatsapp_message"><?=h($set['whatsapp_message']??'')?>
</textarea>
</label>
<label>Position<input value="Bottom left · reservado para WhatsApp" readonly><input type="hidden" name="whatsapp_position" value="left"><small class="field-hint">NIVO Web Chat usa el lado derecho para que ambos widgets nunca se superpongan.</small></label>
</div>
<div class="form-actions">
<button>Save WhatsApp widget</button>
</div>
</form>
</section>
<section class="panel wide animate-in" id="public-form">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?></div>
<div>
<h2>Public request form</h2>
<p>Choose which fields visitors must complete and define meaningful-message validation.</p>
</div>
</div>
<form method="post" class="public-form-settings">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="public_form">
<div class="settings-subheading">
<div>
<strong>Required fields</strong>
<small>Email is permanently required so the team can reply.</small>
</div>
</div>
<div class="form-requirement-grid">
<div class="form-requirement-card is-locked">
<span class="requirement-lock" aria-hidden="true">✓</span>
<span><b>Email</b><small>Always required and validated.</small></span>
</div>
<?php foreach([
    'name'=>['Name','Ask for the visitor name.'],
    'phone'=>['Phone','Require a phone number.'],
    'service'=>['Request type / Service','Require an active service selection.'],
    'referral'=>['How did you hear about us?','Require a valid configured source.'],
    'message'=>['Message','Require meaningful project details.'],
] as $field=>$meta): ?>
<label class="premium-switch form-requirement-card">
<input type="checkbox" name="form_required_<?=$field?>" <?=setting_enabled($set,'form_required_'.$field,in_array($field,['name','referral','message'],true))?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b><?=h($meta[0])?></b><small><?=h($meta[1])?></small></span>
</label>
<?php endforeach; ?>
</div>
<div class="settings-subheading">
<div>
<strong>Meaningful message limits</strong>
<small>Punctuation and spaces do not count toward these limits.</small>
</div>
</div>
<div class="two-col">
<label>Minimum meaningful characters
<input type="number" min="10" max="1000" name="form_message_min_characters" value="<?=h($set['form_message_min_characters']??'30')?>">
<small class="field-hint">Recommended starting value: 30.</small>
</label>
<label>Minimum meaningful words
<input type="number" min="2" max="100" name="form_message_min_words" value="<?=h($set['form_message_min_words']??'5')?>">
<small class="field-hint">Recommended starting value: 5.</small>
</label>
</div>
<div class="settings-subheading">
<div>
<strong>Referral options</strong>
<small>Enter one option per line. Other / Otro is preserved automatically.</small>
</div>
</div>
<div class="two-col referral-options-grid">
<label>English options
<textarea name="form_referral_options_en" rows="9"><?=h(implode("\n",referral_options($set,'en')))?></textarea>
</label>
<label>Opciones en español
<textarea name="form_referral_options_es" rows="9"><?=h(implode("\n",referral_options($set,'es')))?></textarea>
</label>
</div>
<div class="form-actions">
<button><?=icon('edit')?> Save public form</button>
</div>
</form>
</section>
<?php
$antiSpamSettings=public_form_antispam_config($set);
$turnstileHasSecret=$antiSpamSettings['turnstile_secret_stored'];
?>
<section class="panel wide animate-in" id="form-protection">
<div class="panel-heading">
<div class="panel-icon"><?=icon('shield')?></div>
<div>
<h2>Public form protection</h2>
<p>Reduce automated spam with local checks and optional Cloudflare Turnstile verification.</p>
</div>
</div>
<form method="post" class="form-protection-settings">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="form_antispam">
<div class="form-protection-switches">
<label class="premium-switch protection-card">
<input type="checkbox" name="form_antispam_enabled" <?=$antiSpamSettings['enabled']?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Enable form anti-spam</b><small>Uses a honeypot, server-issued timing token and per-session submission limits.</small></span>
</label>
<label class="premium-switch protection-card">
<input type="checkbox" name="form_block_sales_solicitation" <?=$antiSpamSettings['block_sales']?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Block obvious sales solicitation</b><small>Requires multiple promotional signals to reduce false positives.</small></span>
</label>
</div>
<div class="three-col protection-limits">
<label>Minimum form time
<input type="number" min="0" max="30" name="form_minimum_seconds" value="<?=h((string)$antiSpamSettings['minimum_seconds'])?>">
<small class="field-hint">Seconds before a form can be accepted. Recommended: 3.</small>
</label>
<label>Cooldown between sends
<input type="number" min="0" max="3600" name="form_cooldown_seconds" value="<?=h((string)$antiSpamSettings['cooldown_seconds'])?>">
<small class="field-hint">Seconds per browser session. Recommended: 60.</small>
</label>
<label>Maximum sends per hour
<input type="number" min="1" max="50" name="form_maximum_per_hour" value="<?=h((string)$antiSpamSettings['maximum_per_hour'])?>">
<small class="field-hint">Successful submissions per browser session. Recommended: 5.</small>
</label>
</div>
<div class="settings-subheading turnstile-heading">
<div>
<strong>Cloudflare Turnstile</strong>
<small>Optional managed verification. Local protections continue working when Turnstile is disabled.</small>
</div>
<span class="credential-status <?=$turnstileHasSecret?'is-ready':'is-missing'?>"><?=$turnstileHasSecret?'Secret configured':'Secret not configured'?></span>
</div>
<label class="premium-switch protection-card turnstile-switch">
<input type="checkbox" name="turnstile_enabled" <?=$antiSpamSettings['turnstile_enabled']?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Enable Cloudflare Turnstile</b><small>Both keys are required. Incomplete configuration blocks public submissions instead of pretending verification is active.</small></span>
</label>
<div class="two-col">
<label>Turnstile Site Key
<input name="turnstile_site_key" maxlength="255" autocomplete="off" value="<?=h($antiSpamSettings['turnstile_site_key'])?>" placeholder="Public site key">
<small class="field-hint">This public key is rendered in the website form.</small>
</label>
<label>Turnstile Secret Key
<input type="password" name="turnstile_secret_key" maxlength="255" autocomplete="new-password" value="" placeholder="<?=$turnstileHasSecret?'Leave blank to keep stored secret':'Enter secret key'?>">
<small class="field-hint"><?=$turnstileHasSecret?'A secret is stored securely. It is never displayed again.':'The secret is encrypted before it is stored.'?></small>
</label>
</div>
<?php if($turnstileHasSecret): ?>
<label class="premium-check remove-secret-check">
<input type="checkbox" name="remove_turnstile_secret" value="1">
<span>Remove stored secret</span>
</label>
<?php endif; ?>
<div class="privacy-note"><?=icon('shield')?> <span>No IP, fingerprint or precise location is stored by the local protection. The server does not send <code>remoteip</code> to Turnstile.</span></div>
<div class="form-actions">
<button><?=icon('shield')?> Save form protection</button>
</div>
</form>
</section>
<?php $visitStats=analytics_snapshot($set); ?>
<section class="panel wide animate-in" id="private-visits">
<div class="panel-heading">
<div class="panel-icon"><?=icon('dashboard')?></div>
<div>
<h2>Private website visits</h2>
<p>Lightweight first-party traffic estimates visible only inside the administrator.</p>
</div>
</div>
<div class="private-visits-summary">
<article><span>Estimated visits</span><strong><?=number_format($visitStats['total'])?></strong><small>One count per browser each day.</small></article>
<article><span>Visits today</span><strong><?=number_format($visitStats['today'])?></strong><small><?=$visitStats['today']===0?'No public visit counted today.':'Counted since the start of today.'?></small></article>
<article><span>Last 7 days</span><strong><?=number_format($visitStats['week'])?></strong><small>Anonymous visits counted during the last week.</small></article>
<article><span>Last 30 days</span><strong><?=number_format($visitStats['month'])?></strong><small>Anonymous visits counted during the last month.</small></article>
<article><span>Last visit</span><strong class="visit-date"><?=h($visitStats['last_visit_at']?:'Not recorded')?></strong><small>Last eligible public visit.</small></article>
</div>
<form method="post" class="private-visits-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="analytics">
<label class="premium-switch">
<input type="checkbox" name="analytics_tracking_enabled" <?=$visitStats['enabled']?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Enable anonymous visit counter</b><small>Counts once per browser/day. It stores no IP or identity; clearing cookies, changing browser or using another device can create another count.</small></span>
</label>
<div class="privacy-note"><?=icon('shield')?> <span>Administrator previews, common bots, crawlers and known uptime monitors are excluded.</span></div>
<div class="form-actions">
<button><?=icon('dashboard')?> Save visit counter</button>
</div>
</form>
</section>
</div><?php
require __DIR__.'/_footer.php';
