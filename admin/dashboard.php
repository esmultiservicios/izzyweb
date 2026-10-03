<?php
require __DIR__.'/bootstrap.php';
require_permission('dashboard.view');
$pdo=db();
$me=current_admin();
$counts=['services'=>(int)$pdo->query('SELECT COUNT(*) FROM services WHERE active=1')->fetchColumn(),
'gallery'=>(int)$pdo->query('SELECT COUNT(*) FROM gallery WHERE active=1')->fetchColumn(),
'new'=>(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn(),
'drafts'=>(int)$pdo->query('SELECT COUNT(*) FROM content_drafts')->fetchColumn(),
'notifications'=>unread_notification_count(),
'approvals'=>(int)$pdo->query("SELECT COUNT(*) FROM content_approvals WHERE status='pending'")->fetchColumn()];
$missing=(int)$pdo->query("SELECT COUNT(*) FROM gallery WHERE active=1 AND (image_path IS NULL OR image_path='')")->fetchColumn();
$emailOk=(int)$pdo->query('SELECT COUNT(*) FROM correo WHERE estado=1')->fetchColumn()>0;
$areas=(int)$pdo->query('SELECT COUNT(*) FROM service_areas WHERE active=1')->fetchColumn();
$traffic=analytics_snapshot(settings());
if(user_can('estimates.manage_all')) {
    $due=(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')")->fetchColumn();
    $recent=$pdo->query('SELECT id,full_name,service_needed,status,priority,created_at FROM estimate_requests ORDER BY id DESC LIMIT 5')->fetchAll();
} else {
    $st=$pdo->prepare("SELECT COUNT(*) FROM estimate_requests WHERE assigned_to=? AND follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')");
    $st->execute([(int)$me['id']]);
    $due=(int)$st->fetchColumn();
    $st=$pdo->prepare('SELECT id,full_name,service_needed,status,priority,created_at FROM estimate_requests WHERE assigned_to=? ORDER BY id DESC LIMIT 5');
    $st->execute([(int)$me['id']]);
    $recent=$st->fetchAll();
}
$stats=[];
if(user_can('services.manage'))$stats[]=['Active services',
$counts['services'],
'Published',
'services.php'];
if(user_can('gallery.manage'))$stats[]=['Published projects',
$counts['gallery'],
'Case studies',
'gallery.php'];
if(user_can('estimates.view'))$stats[]=['New estimates',
$counts['new'],
'Need attention',
'estimates.php'];
$stats[]=['Notifications',
$counts['notifications'],
'Unread alerts',
'notifications.php'];
$attention=[];
if(user_can('estimates.view'))$attention[]=['Follow-ups due',
$due,
$due?'Customer follow-ups need attention.':'No overdue follow-ups.',
'estimates.php?follow=due',
'mail'];
if(user_can('content.view'))$attention[]=['Unpublished content',
$counts['drafts'],
$counts['drafts']?'A landing page draft is waiting.':'Public content is up to date.',
'content.php',
'edit'];
if(user_can('content.approve'))$attention[]=['Pending approvals',
$counts['approvals'],
$counts['approvals']?'Drafts are waiting for review.':'No content awaiting approval.',
'approvals.php',
'approval'];
if(user_can('gallery.manage'))$attention[]=['Project covers missing',
$missing,
$missing?'Add a cover to published projects.':'All published projects have cover images.',
'gallery.php',
'image'];
if(user_can('email.manage'))$attention[]=['Email delivery',
$emailOk?'Ready':'Setup',
$emailOk?'Email delivery is configured.':'Configure SMTP or Microsoft Graph.',
'email.php',
'mail'];
$cards=[['content.view','content.php','edit','Visual content editor','Draft, preview, approval and publishing.'],
['settings.manage','appearance.php','eye','Appearance','Typography, banner, navigation and visual presets.'],
['sections.manage','sections.php','dashboard','Section manager','Show, hide and reorder landing page sections.'],
['media.manage','media.php','image','Media Library','Upload, search, preview and reuse images.'],
['services.manage','services.php','tools','Services','Add, edit, order or hide services.'],
['gallery.manage','gallery.php','image','Projects / Case Studies','Create, edit, publish and order project records.'],
['areas.manage','areas.php','pin','Service areas','Manage cities, ZIP codes and coverage.'],
['social.manage','social.php','social','Redes sociales','Manage links, public placement, size and responsive visibility.'],
['seo.manage','seo.php','eye','SEO Manager','Search title, description and social image.'],
['health.view','health.php','gear','Website Health','Automatic readiness and configuration checks.'],
['notifications.view','notifications.php','bell','Activity Center','Notifications and change history.'],
['backups.manage','backups.php','dashboard','Backup & Restore','Create restore points before major changes.'],
['users.manage','users.php','users','Users','Create team accounts and assign roles.'],
['roles.manage','roles.php','shield','Roles & permissions','Configure access without changing code.'],
['content.approve','approvals.php','approval','Approval queue','Review and publish submitted drafts.'],
['security.manage','security.php','shield','Security Center','Active sessions and login activity.']];
$pageTitle='Dashboard';
$active='dashboard';
require __DIR__.'/_header.php';
?>

<div class="page-heading animate-in">
<div>
<p class="eyebrow">PANEL PRINCIPAL</p>
<h1>Administración del sitio</h1>
<p class="muted">Welcome, <?=h($me['full_name']?:$me['username'])?>
. Tu espacio muestra únicamente las herramientas disponibles para tu <?=h($me['role_name']?:'assigned')?>
.</p>
</div>
<div class="heading-actions"><?php
if(user_can('content.view')):
?>
<a class="button" href="content.php"><?=icon('edit')?>
Contenido del sitio</a><?php
endif;
?>
<a class="button secondary" href="../?preview=1" target="_blank"><?=icon('eye')?>
Ver sitio</a>
</div>
</div>

<section class="dashboard-brand-banner animate-in">
    <div class="dashboard-brand-main">
        <span class="dashboard-brand-mark"><img src="../assets/izzy/logo-mark.png" alt="IZZY"></span>
        <div>
            <p class="eyebrow">IZZY CMS</p>
            <h2>Control central del sitio público</h2>
            <p>Contenido, planes, proyectos, solicitudes, SEO y salud del sitio desde un mismo panel.</p>
        </div>
    </div>
    <div class="dashboard-brand-pills">
        <span><?=icon('edit')?> Contenido</span>
        <span><?=icon('chart')?> Métricas</span>
        <span><?=icon('shield')?> Seguridad</span>
    </div>
</section>

<div class="stat-grid"><?php
foreach($stats as $s):
?>
<a class="stat animate-in" href="<?=$s[3]?>">
<span><?=h($s[0])?>
</span>
<strong data-stat="<?=$s[1]?>"><?=$s[1]?>
</strong>
<small><?=h($s[2])?>
</small>
</a><?php
endforeach;
?>
</div>
<section class="dashboard-section website-traffic-section animate-in">
<div class="section-heading website-traffic-heading">
<div>
<p class="eyebrow">TRÁFICO DEL SITIO</p>
<h2>Visitas públicas</h2>
<p>Solo cuenta visitas públicas; las vistas previas del administrador quedan excluidas.</p>
</div>
<?php if(user_can('settings.manage')): ?>
<a class="button secondary small" href="settings.php#private-visits"><?=icon('gear')?> Configurar seguimiento</a>
<?php endif; ?>
</div>
<div class="website-traffic-grid">
<article class="traffic-card">
<span class="traffic-icon"><?=icon('chart')?></span>
<div><small>Visitas acumuladas</small><strong><?=number_format($traffic['total'])?></strong><p><?=$traffic['total']===0?'Aún no hay visitas públicas registradas.':'Visitas públicas registradas.'?></p></div>
</article>
<article class="traffic-card">
<span class="traffic-icon"><?=icon('eye')?></span>
<div><small>Visitas hoy</small><strong><?=number_format($traffic['today'])?></strong><p><?=$traffic['today']===0?'Hoy no se han registrado visitas.':'Visitas públicas registradas hoy.'?></p></div>
</article>
<article class="traffic-card traffic-last-card">
<span class="traffic-icon"><?=icon('dashboard')?></span>
<div><small>Última visita registrada</small><strong><?=h($traffic['last_visit_at']?:'Sin registro')?></strong><p><?=$traffic['last_visit_at']===''?'Esperando la primera visita pública.':'Registrada con la fecha y hora del servidor.'?></p></div>
</article>
<article class="traffic-card traffic-status-card <?=$traffic['enabled']?'is-active':'is-paused'?>">
<span class="traffic-icon"><?=icon($traffic['enabled']?'approval':'gear')?></span>
<div><small>Estado del seguimiento</small><strong><?=$traffic['enabled']?'Seguimiento activo':'Seguimiento pausado'?></strong><p><?=$traffic['enabled']?'El conteo anónimo diario está habilitado.':'Las nuevas visitas no se están contabilizando.'?></p></div>
</article>
</div>
</section>
<?php
if($attention):
?>
<section class="dashboard-section">
<div class="section-heading">
<div>
<p class="eyebrow">REQUIERE ATENCIÓN</p>
<h2>Centro de atención</h2>
</div><?php
if(user_can('health.view')):
?>
<a class="button secondary small" href="health.php">Salud del sitio</a><?php
endif;
?>
</div>
<div class="attention-grid"><?php
foreach($attention as $a):
?>
<a class="attention-card" href="<?=$a[3]?>">
<span class="manage-icon"><?=icon($a[4])?>
</span>
<div>
<strong><?=h((string)$a[1])?>
</strong>
<b><?=h($a[0])?>
</b>
<small><?=h($a[2])?>
</small>
</div>
<i>→</i>
</a><?php
endforeach;
?>
</div>
</section><?php
endif;
?>

<section class="dashboard-section">
<div class="section-heading">
<div>
<p class="eyebrow">HERRAMIENTAS</p>
<h2>Espacio de trabajo</h2>
</div>
<p>Access is automatically controlled by your.</p>
</div>
<div class="manage-grid"><?php
foreach($cards as $c):if(!user_can($c[0]))continue;
?>
<a class="manage-card animate-in" href="<?=$c[1]?>">
<span class="manage-icon"><?=icon($c[2])?>
</span>
<div>
<strong><?=h($c[3])?>
</strong>
<small><?=h($c[4])?>
</small>
</div>
<span class="manage-arrow">→</span>
</a><?php
endforeach;
?>
</div>
</section>
<?php
if(user_can('estimates.view')):
?>
<section class="dashboard-section">
<div class="section-heading">
<div>
<p class="eyebrow">SOLICITUDES DE CLIENTES</p>
<h2><?=user_can('estimates.manage_all')?'Solicitudes recientes':'Mis solicitudes asignadas'?>
</h2>
</div>
<a class="button secondary small" href="estimates.php">Abrir solicitudes</a>
</div><?php
if(!$recent):
?>
<div class="empty-state">
<strong>No hay solicitudes en tu espacio</strong>
<p>Las solicitudes asignadas aparecerán aquí.</p>
</div><?php
else:
?>
<div class="request-grid"><?php
foreach($recent as $r):
?>
<article class="request-card animate-in priority-<?=h($r['priority']??'normal')?>">
<div class="request-top">
<div>
<strong><?=h($r['full_name']?:'Website visitor')?>
</strong>
<small><?=h($r['created_at'])?>
</small>
</div>
<span class="badge <?=h($r['status'])?>"><?=h(str_replace('_',' ',$r['status']))?>
</span>
</div>
<p><?=h($r['service_needed']?:'General project')?>
</p>
<a href="estimates.php?view=<?=$r['id']?>">Open request →</a>
</article><?php
endforeach;
?>
</div><?php
endif;
?>
</section><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
