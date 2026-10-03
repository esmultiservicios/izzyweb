<?php
require __DIR__.'/bootstrap.php';
if(admin_count()===0) {
    header('Location: ../install/', true, 303);
    exit;
}
if(is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
// Keep the login URL canonical. The install-completed notice is session based, never query-string based.
if(isset($_GET['installed'])) {
    header('Location: login.php', true, 303);
    exit;
}
$error='';
$set=settings();
$favicon=trim((string)($set['favicon_path']??($set['admin_logo_path']??'')));if($favicon==='')$favicon='assets/izzy/logo-mark.png';
$brand=$set['admin_brand_name']??"CMS Core Admin";
$logo=trim((string)($set['admin_logo_path']??''));if($logo===''||$logo==='assets/izzy/logo-mark.png')$logo='assets/izzy/logo-full-dark.png';
$prefill=trim((string)($_SESSION['cms_login_prefill']??''));
$loginNotice=is_array($_SESSION['cms_login_notice']??null)?$_SESSION['cms_login_notice']:null;
$showInstalledNotice=!empty($_SESSION['cms_install_completed_notice']);
unset($_SESSION['cms_login_prefill'],$_SESSION['cms_login_notice'],$_SESSION['cms_install_completed_notice']);
$identity=trim((string)($_POST['identity']??$prefill));
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $u=$identity;
    $p=(string)($_POST['password']??'');
    $st=db()->prepare('SELECT id,username,email,password_hash,active,two_factor_enabled,two_factor_secret_enc FROM admin_users WHERE username=? OR email=? ORDER BY CASE WHEN username=? THEN 0 ELSE 1 END,id LIMIT 1');
    $st->execute([$u,$u,$u]);
    $row=$st->fetch();
    if($row&&(int)$row['active']===1&&password_verify($p,$row['password_hash'])) {
        if((int)($row['two_factor_enabled']??0)===1&&!empty($row['two_factor_secret_enc'])) {
            session_regenerate_id(true);
            $_SESSION['escms_2fa_pending_id']=(int)$row['id'];
            $_SESSION['escms_2fa_pending_user']=$row['username'];
            $_SESSION['escms_2fa_remember']=isset($_POST['remember_me'])?1:0;
            bind_session_to_current_installation();
            header('Location: two-factor.php');
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['escms_admin_id']=(int)$row['id'];
        $_SESSION['escms_admin_user']=$row['username'];
        bind_session_to_current_installation();
        db()->prepare('UPDATE admin_users SET last_login_at=NOW(),last_login_ip=?,last_user_agent=? WHERE id=?')->execute([request_ip(),request_user_agent(),(int)$row['id']]);
        record_login_event((int)$row['id'],$u,true);
        log_activity('login','Administrator signed in');
        admin_notify('info','Administrator login',($row['username']??'Administrator').' signed in to the CMS.','security.php');
        if(isset($_POST['remember_me'])) create_remember_token((int)$row['id']);
        else clear_remember_cookie();
        sync_admin_session();
        header('Location: dashboard.php');
        exit;
    }
    record_login_event($row?(int)$row['id']:null,$u,false);
    $error=($row&&(int)($row['active']??0)!==1)?'This account is disabled. Contact an administrator.':'El usuario, correo o contraseña no coincide con la cuenta administradora instalada.';
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if($favicon!==''): ?><link rel="icon" href="../<?=h($favicon)?>"><link rel="shortcut icon" href="../<?=h($favicon)?>"><?php endif; ?>
<title>Admin Login</title>
<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css">
<link rel="stylesheet" href="../assets/vendor/cms-modal/cmsModal.css">
<link rel="stylesheet" href="<?=h(versioned_asset('admin.css','admin/admin.css'))?>">
<link rel="stylesheet" href="../assets/vendor/select2/select2.local.css">
<link rel="stylesheet" href="../assets/ui-standards.css">
<link rel="stylesheet" href="../assets/action-icons.css">

<style>
.swal2-popup.izzy-login-install-success{width:min(520px,calc(100vw - 32px))!important;border-radius:20px!important;border:1px solid #d8e7f0!important;box-shadow:0 30px 90px rgba(6,35,63,.28)!important;padding:28px 28px 24px!important}.izzy-login-install-success .swal2-title{color:#082f57!important;font-size:25px!important}.izzy-login-install-success .swal2-html-container{font-size:14px!important}.izzy-login-install-success .swal2-confirm{min-height:46px!important;max-width:100%!important;border-radius:10px!important;font-size:14px!important;line-height:1.22!important;font-weight:900!important;padding:10px 17px!important;white-space:normal!important;overflow-wrap:anywhere!important;text-align:center!important}

/* v1.0.28 - login alignment and one-time install notice */
.auth-options{align-items:stretch!important}
.remember-check{display:grid!important;grid-template-columns:34px minmax(0,1fr)!important;align-items:center!important;min-height:52px!important;padding:8px 12px!important;gap:10px!important}
.remember-check input{width:24px!important;height:24px!important;min-height:24px!important;justify-self:center!important;align-self:center!important;margin:0!important}
.cms-check-text{align-self:center!important}
.auth-options>a{display:flex!important;align-items:center!important;justify-content:center!important;min-height:52px!important;padding:0 4px!important;text-align:center!important}
@media(max-width:640px){.auth-options{align-items:stretch!important}.remember-check,.auth-options>a{width:100%!important}}

/* v1.0.30 - deterministic centered Remember me control */
.remember-check{position:relative!important;display:grid!important;grid-template-columns:30px minmax(0,1fr)!important;align-items:center!important;column-gap:10px!important}
.remember-check input[type="checkbox"]{position:absolute!important;opacity:0!important;pointer-events:none!important;width:1px!important;height:1px!important;min-height:1px!important;margin:0!important}
.remember-check .cms-check-box{display:grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:50%!important;border:1px solid #a9bfdc!important;background:#fff!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.75);justify-self:center!important;align-self:center!important;transition:.16s ease!important}
.remember-check .cms-check-box::after{content:"";width:10px;height:10px;border-radius:50%;background:#fff;opacity:0;transform:scale(.5);transition:.16s ease}
.remember-check input[type="checkbox"]:checked + .cms-check-box{background:#2f69ef!important;border-color:#2f69ef!important;box-shadow:0 0 0 4px rgba(47,105,239,.12)!important}
.remember-check input[type="checkbox"]:checked + .cms-check-box::after{opacity:1;transform:scale(1)}
.remember-check input[type="checkbox"]:focus-visible + .cms-check-box{outline:3px solid rgba(47,105,239,.20);outline-offset:2px}

</style>
</head>
<body>
<main class="auth-wrap">
<div class="auth-card auth-card-premium">
<div class="auth-brand-mark">
<?php if($logo!==''): ?><img src="../<?=h($logo)?>" alt=""><?php else: ?><span>CMS</span><?php endif; ?>
</div>
<p class="eyebrow">SECURE ADMINISTRATION</p>
<h1><?=h($brand)?>

</h1>
<p>Manage website content, customer requests and system settings.</p><?php
if(isset($_GET['setup'])):
?>

<div class="alert success">Administrator created. You can log in now.</div><?php
endif;
?>
<?php
if(isset($_GET['reset'])):
?>

<div class="alert success">Password updated. Sign in with your new password.</div><?php
endif;
?>
<?php
if(isset($_GET['revoked'])):
?>

<div class="alert warning">That administrator session was signed out from the Security Center.</div><?php
endif;
?>
<?php
if(isset($_GET['disabled'])):
?>

<div class="alert warning">Your administrator account is disabled.</div><?php
endif;
?>
<?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>
<?php if($loginNotice): ?>
<div class="alert <?=h(in_array(($loginNotice['type']??''),['success','error','info','warning'],true)?$loginNotice['type']:'info')?>"><?=h($loginNotice['message']??'')?></div>
<?php endif; ?>

<form method="post" id="admin-login-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label>Username or email<input type="text" name="identity" required autocomplete="username" autofocus value="<?=h($identity)?>">
</label>
<label>Password<input type="password" name="password" required autocomplete="current-password">
</label>
<div class="auth-options">
<label class="remember-check cms-check">
<input type="checkbox" name="remember_me" value="1" <?=isset($_POST['remember_me'])?'checked':''?>>
<span class="cms-check-box" aria-hidden="true">
</span>
<span class="cms-check-text"><strong>Recordarme</strong><small>Mantener esta sesión iniciada de forma segura durante 30 días.</small></span>
</label>
<a href="forgot-password.php<?=filter_var($identity,FILTER_VALIDATE_EMAIL)?'?email='.rawurlencode($identity):''?>">¿Olvidaste tu contraseña?</a>
</div>
<button type="submit">Iniciar sesión</button>
</form>
<div class="auth-footer-note">Protected administrator access · CMS Core</div>
</div>
</main>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js">
</script>
<script src="../assets/vendor/show-notify/showNotify.js">
</script>
<script src="../assets/vendor/cms-modal/cmsModal.js"></script>
<script src="../assets/action-icons.js"></script>
<script>
document.querySelectorAll(".alert.success,.alert.error,.alert.info,.alert.warning").forEach(function(el){
  var t=el.classList.contains("error")?"error":el.classList.contains("warning")?"warning":el.classList.contains("success")?"success":"info";
  if(window.showNotify){showNotify(el.textContent.trim(),t,{title:t==='success'?'Operación completada':'Información'});el.hidden=true;}
});
// v1.0.77: Recordarme = sesión persistente segura + identidad recordada (nunca guarda contraseña).
(function(){
  var form=document.getElementById('admin-login-form');
  if(!form)return;
  var identity=form.querySelector('input[name="identity"]');
  var remember=form.querySelector('input[name="remember_me"]');
  if(!identity||!remember)return;
  var key='izzy_admin_remember_identity';
  var pref='izzy_admin_remember_enabled';
  try{
    if(!identity.value && localStorage.getItem(pref)==='1'){
      identity.value=localStorage.getItem(key)||'';
    }
    if(localStorage.getItem(pref)==='1')remember.checked=true;
  }catch(e){}
  remember.addEventListener('change',function(){
    try{
      if(this.checked){
        localStorage.setItem(pref,'1');
        if(identity.value.trim())localStorage.setItem(key,identity.value.trim());
      }else{
        localStorage.removeItem(pref);
        localStorage.removeItem(key);
      }
    }catch(e){}
  });
  identity.addEventListener('input',function(){
    try{if(remember.checked)localStorage.setItem(key,identity.value.trim());}catch(e){}
  });
  form.addEventListener('submit',function(){
    try{
      if(remember.checked){
        localStorage.setItem(pref,'1');
        localStorage.setItem(key,identity.value.trim());
      }else{
        localStorage.removeItem(pref);
        localStorage.removeItem(key);
      }
    }catch(e){}
  });
})();

// v1.0.29: never leave ?installed=1 (or any installed flag) in the address bar.
(function(){
  try{
    var cleanUrl=new URL(window.location.href);
    if(cleanUrl.searchParams.has('installed')){
      cleanUrl.searchParams.delete('installed');
      window.history.replaceState({},document.title,cleanUrl.pathname+(cleanUrl.searchParams.toString()?'?'+cleanUrl.searchParams.toString():'')+cleanUrl.hash);
    }
  }catch(e){}
})();
<?php if($showInstalledNotice): ?>
window.addEventListener('DOMContentLoaded',function(){
  if(!window.Swal?.fire)return;
  Swal.fire({
    icon:'success',
    title:'Instalación completada',
    html:<?=json_encode('<div style="text-align:center;line-height:1.55;color:#60758a">IZZY CMS Core quedó instalado y protegido correctamente.<br><strong style="color:#173d60">Ya puedes iniciar sesión con la cuenta administradora creada durante el asistente.</strong></div>',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
    confirmButtonText:'Continuar al inicio de sesión',
    allowOutsideClick:false,
    allowEscapeKey:true,
    customClass:{popup:'izzy-login-install-success'},
    didOpen:function(popup){ window.ESActionIcons?.scan?.(popup||document); }
  }).then(function(){document.querySelector('input[name="identity"]')?.focus();});
});
<?php endif; ?>
</script>
<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/select2/select2.local.js"></script>
<script src="../assets/ui-standards.js"></script>
</body>
</html>
