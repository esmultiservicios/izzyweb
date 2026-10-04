<?php
require __DIR__.'/bootstrap.php';
require_permission('security.manage');
require_once __DIR__.'/../core/EmailService.php';
$pdo=db();
$me=current_admin();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if($action==='revoke') {
            $id=(int)($_POST['session_id']??0);
            $st=$pdo->prepare('SELECT s.admin_id,s.session_hash,r.role_key FROM admin_sessions s JOIN admin_users u ON u.id=s.admin_id LEFT JOIN admin_roles r ON r.id=u.role_id WHERE s.id=?');
            $st->execute([$id]);
            $sess=$st->fetch();
            if(!$sess)throw new RuntimeException('Session not found.');
            if(($sess['role_key']??'')==='owner'&&!role_is_owner($me))throw new RuntimeException('Only the Owner can revoke an Owner session.');
            $pdo->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE id=?')->execute([$id]);
            revoke_remember_tokens_for_admin((int)$sess['admin_id']);
            log_activity('session_revoke','Revoked an administrator session',['session_id'=>$id]);
            if(hash_equals($sess['session_hash'],session_fingerprint())) {
                clear_admin_authentication_state(true,true);
                header('Location: login.php?revoked=1',true,303);
                exit;
            }
            flash('success','Session signed out.');
        } elseif($action==='revoke_others') {
            $pdo->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE admin_id=? AND session_hash<>? AND revoked_at IS NULL')->execute([(int)$me['id'],session_fingerprint()]);
            revoke_remember_tokens_for_admin((int)$me['id']);
            clear_remember_cookie(false);
            flash('success','All your other sessions were signed out. Persistent remembered access was also revoked.');
        } elseif($action==='cleanup') {
            $pdo->exec('DELETE FROM admin_sessions WHERE last_seen_at < DATE_SUB(NOW(),INTERVAL 60 DAY) OR revoked_at IS NOT NULL');
            flash('success','Old session records cleaned up.');
        } elseif($action==='prepare_fresh_install') {
            if(!role_is_owner($me))throw new RuntimeException('Solo el Owner puede iniciar una instalación desde cero.');

            $currentPassword=(string)($_POST['current_password']??'');
            $confirmation=strtoupper(trim((string)($_POST['confirmation_phrase']??'')));
            if($currentPassword==='')throw new RuntimeException('Ingresa tu contraseña actual para continuar.');
            if($confirmation!=='BORRAR IZZY')throw new RuntimeException('Escribe exactamente BORRAR IZZY para confirmar.');

            $st=$pdo->prepare('SELECT password_hash FROM admin_users WHERE id=? AND active=1 LIMIT 1');
            $st->execute([(int)$me['id']]);
            $passwordHash=(string)($st->fetchColumn()?:'');
            if($passwordHash===''||!password_verify($currentPassword,$passwordHash))throw new RuntimeException('La contraseña actual no es correcta.');

            $configFile=database_config_file();
            if(!is_file($configFile))throw new RuntimeException('No se encontró la configuración activa de la base de datos.');
            $activeDbConfig=require $configFile;
            if(!is_array($activeDbConfig))throw new RuntimeException('La configuración activa de la base de datos no es válida.');
            $dbHost=trim((string)($activeDbConfig['host']??''));
            $dbName=trim((string)($activeDbConfig['dbname']??''));
            $dbUser=(string)($activeDbConfig['username']??'');
            $dbPassword=(string)($activeDbConfig['password']??'');
            $dbPort=max(1,min(65535,(int)($activeDbConfig['port']??3306)));
            if($dbHost===''||$dbName===''||$dbUser==='')throw new RuntimeException('La configuración de la base de datos está incompleta.');

            // La alerta se envía ANTES de cualquier acción destructiva.
            $ownerEmail=trim((string)($me['email']??''));
            if(!filter_var($ownerEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('El Owner no tiene un correo válido para recibir la alerta de seguridad. Actualízalo antes de continuar.');
            $securitySettings=settings();
            $securityMail=(new EmailService())->sendWithFallback(
                [2,1],
                $ownerEmail,
                'Alerta crítica · IZZY se preparará para instalación desde cero',
                EmailTemplates::freshInstallPreparationAlert(
                    (string)($me['full_name']?:$me['username']),
                    (string)$me['username'],
                    request_ip(),
                    date('Y-m-d H:i:s'),
                    $securitySettings,
                    $dbName
                )
            );
            if(empty($securityMail['success']))throw new RuntimeException('No fue posible enviar el correo de seguridad. No se eliminó nada. Revisa la configuración de correo e inténtalo nuevamente.');

            log_activity('fresh_install_prepare','Owner authorized a destructive clean install from Security Center',['database'=>$dbName]);

            $root=dirname(__DIR__);
            $configDir=$root.'/config';
            $backupDir=$configDir.'/installer-backups';
            if(!is_dir($backupDir)&&!@mkdir($backupDir,0700,true)&&!is_dir($backupDir))throw new RuntimeException('No fue posible crear el respaldo seguro de la configuración.');

            $stamp=date('Ymd-His');
            $targets=[
                $configDir.'/config.php',
                $configDir.'/database.php',
                $configDir.'/install.lock',
            ];
            $moved=[];
            try {
                // Primero retirar y respaldar la configuración activa. Si DROP DATABASE falla,
                // estos archivos se restauran y el sitio conserva su estado anterior.
                foreach($targets as $source) {
                    if(!is_file($source))continue;
                    $destination=$backupDir.'/'.basename($source).'.'.$stamp.'.bak';
                    if(!@rename($source,$destination))throw new RuntimeException('No fue posible retirar la configuración activa. Revisa permisos de escritura en /config/.');
                    $moved[$destination]=$source;
                }
                if(is_file($configDir.'/config.php')||is_file($configDir.'/database.php')||is_file($configDir.'/install.lock')) {
                    throw new RuntimeException('La configuración activa no pudo retirarse completamente.');
                }

                // Conexión al servidor SIN seleccionar la base para poder eliminarla por completo.
                $serverDsn=sprintf('mysql:host=%s;port=%d;charset=%s',$dbHost,$dbPort,(string)($activeDbConfig['charset']??'utf8mb4'));
                $serverPdo=new PDO($serverDsn,$dbUser,$dbPassword,[
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES=>false,
                ]);
                $quotedDb='`'.str_replace('`','``',$dbName).'`';
                $serverPdo->exec('DROP DATABASE IF EXISTS '.$quotedDb);
                $serverPdo=null;
            } catch(Throwable $e) {
                foreach(array_reverse($moved,true) as $destination=>$source) {
                    if(is_file($destination)&&!is_file($source))@rename($destination,$source);
                }
                throw new RuntimeException('No se pudo completar la instalación desde cero. No se dejó la configuración retirada. Detalle: '.$e->getMessage(),0,$e);
            }

            // La base ya no existe. Eliminar del navegador cualquier acceso previo y cerrar la sesión actual.
            clear_remember_cookie(false);
            $_SESSION=[];
            delete_php_session_cookie();
            if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
            header('Location: ../install/',true,303);
            exit;
        }
        header('Location: security.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$sessions=$pdo->query('SELECT s.*,u.username,u.full_name,r.role_key,r.role_name FROM admin_sessions s JOIN admin_users u ON u.id=s.admin_id LEFT JOIN admin_roles r ON r.id=u.role_id ORDER BY (s.revoked_at IS NULL) DESC,s.last_seen_at DESC LIMIT 100')->fetchAll();
$events=$pdo->query('SELECT e.*,u.username FROM admin_login_events e LEFT JOIN admin_users u ON u.id=e.admin_id ORDER BY e.id DESC LIMIT 80')->fetchAll();
$pageTitle='Security Center';
$active='security';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">SECURITY CENTER</p>
<h1>Sessions & login activity</h1>
<p class="muted">See where administrator accounts are signed in and immediately revoke access when needed.</p>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<button class="button secondary" name="action" value="revoke_others">Sign out my other sessions</button>
</form>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>



<?php if(role_is_owner($me)): ?>
<section class="panel security-fresh-install-panel" id="fresh-install">
<div class="section-heading">
<div>
<p class="eyebrow">INSTALADOR · BORRADO CONTROLADO</p>
<h2>Instalación desde cero</h2>
<p class="muted">Esta acción prepara IZZY como una instalación completamente nueva. <strong>Eliminará permanentemente la base de datos actual de IZZY</strong>, retirará la configuración activa y <strong>install.lock</strong>, cerrará las sesiones y después abrirá <strong>/install/</strong>.</p>
</div>
</div>
<div class="security-fresh-install-warning">
<span><?=icon('shield')?></span>
<div><strong>Acción destructiva protegida para Owner</strong><small>Primero se valida tu contraseña y se envía el correo de seguridad. Si el correo falla, no se elimina nada. Si se confirma correctamente, la base de datos actual se eliminará de forma permanente y la configuración activa se moverá a un respaldo protegido.</small></div>
</div>
<form method="post" class="security-fresh-install-form" data-swal-confirm="¿Eliminar IZZY e iniciar desde cero?" data-swal-text="Esta acción eliminará permanentemente la base de datos actual de IZZY, retirará la configuración activa, cerrará las sesiones y abrirá el instalador como Instalación nueva.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="prepare_fresh_install">
<div class="two-col">
<label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password" placeholder="Confirma tu identidad"></label>
<label>Escribe <span class="confirmation-required-phrase">BORRAR IZZY</span><input type="text" name="confirmation_phrase" required autocomplete="off" spellcheck="false" placeholder="BORRAR IZZY"></label>
</div>
<div class="form-actions">
<button type="submit" class="button danger-lite"><?=icon('refresh')?> Borrar e iniciar desde cero</button>
</div>
</form>
</section>
<?php endif; ?>

<div class="security-layout">
<section class="panel">
<div class="section-heading">
<div>
<p class="eyebrow">ACTIVE SESSIONS</p>
<h2>Administrator devices</h2>
</div>
</div>
<div class="session-list"><?php
foreach($sessions as $s):
?>

<article class="session-card <?=$s['revoked_at']?'revoked':''?>">
<span class="session-device"><?=icon('shield')?>

</span>
<div>
<strong><?=h($s['full_name']?:$s['username'])?>

</strong>
<small><?=h($s['role_name']?:'User')?>

 · <?=h($s['ip_address']?:'Unknown IP')?>

 · <?=h($s['last_seen_at'])?>

</small>
<p><?=h($s['user_agent']?:'Unknown browser/device')?>

</p>
</div>
<div><?php
if(!$s['revoked_at']):
?>

<span class="badge success">Active</span>
<form method="post" data-swal-confirm="Sign out this session?" data-swal-text="The user will need to sign in again on this device.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="revoke">
<input type="hidden" name="session_id" value="<?=$s['id']?>">
<button class="button danger-lite small">Sign out</button>
</form><?php
else:
?>

<span class="badge closed">Revoked</span><?php
endif;
?>

</div>
</article><?php
endforeach;
?>

</div>
</section>
<section class="panel">
<div class="section-heading">
<div>
<p class="eyebrow">LOGIN HISTORY</p>
<h2>Recent sign-in attempts</h2>
</div>
</div>
<div class="login-event-list"><?php
foreach($events as $e):
?>

<article>
<span class="login-event-dot <?=$e['success']?'success':'error'?>">
</span>
<div>
<strong><?=h($e['username']?:$e['username_attempt']?:'Unknown')?>

</strong>
<small><?=h($e['created_at'])?>

 · <?=h($e['ip_address']?:'Unknown IP')?>

</small>
</div>
<span class="badge <?=$e['success']?'success':'closed'?>"><?=$e['success']?'Successful':'Failed'?>

</span>
</article><?php
endforeach;
?>

</div>
</section>
</div>
<?php
require __DIR__.'/_footer.php';
