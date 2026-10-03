<?php

declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/social.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['path'=>'/','secure'=>public_request_is_https(),'httponly'=>true,'samesite'=>'Lax']);session_start();}
if(!config_ready()){header('Location: install/');exit;}

try{
    $content=site_content();$settings=settings();
    $services=db()->query('SELECT * FROM services WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    $gallery=db()->query('SELECT * FROM gallery WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    $areas=db()->query('SELECT * FROM service_areas WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    try{$plans=db()->query('SELECT * FROM izzy_plans WHERE active=1 ORDER BY sort_order,id')->fetchAll();}catch(Throwable $e){$plans=[];}
}catch(Throwable $e){http_response_code(500);echo '<h1>IZZY</h1><p>Completa la instalación desde /install/.</p>';exit;}
function c(string $k,string $fallback=''):string{global $content;return trim((string)($content[$k]??$fallback));}

function ui_icon(string $name): string
{
    static $icons = [
        'invoice'=>'<path d="M7 3h10l3 3v15l-3-2-3 2-2-2-2 2-3-2-3 2V3Z"/><path d="M9 8h6"/><path d="M9 12h8"/><path d="M9 16h5"/>',
        'inventory'=>'<path d="M4 7.5 12 3l8 4.5-8 4.5L4 7.5Z"/><path d="M4 12l8 4.5 8-4.5"/><path d="M4 16.5 12 21l8-4.5"/>',
        'finance'=>'<path d="M4 19h16"/><path d="M7 15V9"/><path d="M12 15V5"/><path d="M17 15v-3"/>',
        'restaurant'=>'<path d="M7 3v7"/><path d="M10 3v7"/><path d="M7 7h3"/><path d="M8.5 10.5V21"/><path d="M15 3c2 1.6 2 5.4 0 7"/><path d="M15 10v11"/>',
        'reports'=>'<path d="M5 4h10l4 4v12H5z"/><path d="M15 4v4h4"/><path d="M9 15h6"/><path d="M9 11h6"/>',
        'team'=>'<path d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M17 12a2.5 2.5 0 1 0 0-5"/><path d="M3.5 20a6 6 0 0 1 11 0"/><path d="M15 20a4.5 4.5 0 0 1 4.5-4.5"/>',
        'growth'=>'<path d="M4 18h16"/><path d="m6 15 4-4 3 3 5-6"/><path d="M15 8h3v3"/>',
        'device'=>'<rect x="3" y="4" width="13" height="10" rx="2"/><path d="M8 20h3"/><path d="M18 8h3v9h-7"/>',
        'support'=>'<path d="M4 12a8 8 0 1 1 16 0"/><path d="M4 12v3"/><path d="M20 12v3"/><path d="M8 19h8"/>',
        'location'=>'<path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10Z"/><circle cx="12" cy="11" r="2.5"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'check'=>'<path d="M20 6 9 17l-5-5"/>',
        'whatsapp'=>'<path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.4-4A8 8 0 1 1 20 11.5Z"/><path d="M9 8.5c.5 2.5 2 4 4.5 5"/>',
        'facebook'=>'<path d="M14 8h4V3h-4c-4 0-6 2.4-6 6v3H4v5h4v4h5v-4h4l1-5h-5V9c0-.7.3-1 1-1Z"/>',
        'tiktok'=>'<path d="M14 3h4c.3 2 1.5 3.2 3 3.6v4.1a9 9 0 0 1-3-1.1V16a6 6 0 1 1-6-6v4a2 2 0 1 0 2 2V3Z"/>',
        'spark'=>'<path d="M12 3v4"/><path d="M12 17v4"/><path d="M3 12h4"/><path d="M17 12h4"/><path d="m6.3 6.3 2.8 2.8"/><path d="m14.9 14.9 2.8 2.8"/><path d="m17.7 6.3-2.8 2.8"/><path d="m9.1 14.9-2.8 2.8"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'shield'=>'<path d="M12 3 19 6v6c0 4.5-2.8 7.8-7 9-4.2-1.2-7-4.5-7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'link'=>'<path d="M10 13a4 4 0 0 0 5.7 0l2.3-2.3a4 4 0 0 0-5.7-5.7L10 6"/><path d="M14 11a4 4 0 0 0-5.7 0L6 13.3A4 4 0 0 0 11.7 19L14 17"/>',
        'phone'=>'<path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M10 18h4"/>',
        'search'=>'<circle cx="11" cy="11" r="7"/><path d="m16.2 16.2 4.3 4.3"/>',
    ];
    $path = $icons[$name] ?? $icons['spark'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$path.'</svg>';
}

function feature_icon_name(int $index, string $title=''): string
{
    $title = mb_strtolower($title);
    if (str_contains($title,'factur')) return 'invoice';
    if (str_contains($title,'invent')) return 'inventory';
    if (str_contains($title,'cobrar') || str_contains($title,'pagar') || str_contains($title,'finanza')) return 'finance';
    if (str_contains($title,'report')) return 'reports';
    if (str_contains($title,'nómina') || str_contains($title,'nomina') || str_contains($title,'asistencia') || str_contains($title,'recurso')) return 'team';
    if (str_contains($title,'restaur') || str_contains($title,'cocina')) return 'restaurant';
    $pool = ['invoice','inventory','finance','reports','team','restaurant'];
    return $pool[$index % count($pool)];
}

function plan_variant(array $plan): array
{
    $name = mb_strtolower((string)($plan['name'] ?? ''));
    $tagline = trim((string)($plan['tagline'] ?? ''));
    $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($plan['features'] ?? '')))));
    $summary = $tagline !== '' ? $tagline : 'Solución profesional lista para crecer contigo.';
    $label = 'Solución empresarial';
    $pill = 'Más control para tu negocio';
    $icon = 'growth';
    if (str_contains($name,'restaurant') || str_contains($name,'restaurante')) {
        $label = 'IZZY Restaurantes';
        $pill = 'Venta visual, cocina y atención ágil';
        $icon = 'restaurant';
        $summary = $tagline !== '' ? $tagline : 'Ideal para restaurantes, cafeterías y negocios con atención rápida.';
    } elseif (str_contains($name,'premium')) {
        $label = 'IZZY Premium';
        $pill = 'Capacidad para escalar';
        $icon = 'spark';
        $summary = $tagline !== '' ? $tagline : 'La versión más robusta para operaciones con más control y equipo.';
    } elseif (str_contains($name,'regular') || str_contains($name,'estándar') || str_contains($name,'estandar')) {
        $label = 'IZZY Crecimiento';
        $pill = 'Más capacidad para crecer';
        $icon = 'growth';
    } elseif (str_contains($name,'emprendedor') || str_contains($name,'básico') || str_contains($name,'basico')) {
        $label = 'IZZY Inicio';
        $pill = 'Ideal para comenzar';
        $icon = 'invoice';
    }
    return [$features, $summary, $label, $pill, $icon];
}

$phone=trim((string)($settings['phone']??'+504 8913-6844'));
$digits=preg_replace('/\D+/','',(string)($settings['phone_digits']??$phone));
$email=trim((string)($settings['email']??'evelasquezn@esmultiservicios.com'));
$whatsappEnabled=($settings['whatsapp_enabled']??'1')==='1'&&$digits!=='';
$waMessage=trim((string)($settings['whatsapp_message']??'Hola, quiero conocer más sobre IZZY.'));
$logo=trim((string)($settings['public_logo_path']??''));if($logo===''||$logo==='assets/izzy/logo-mark.png')$logo='assets/izzy/logo-full-dark.png';
$logoOnDark='assets/izzy/logo-full-light.png';
$favicon=trim((string)($settings['favicon_path']??''));if($favicon==='')$favicon='assets/izzy/logo-mark.png';
$seoTitle=trim((string)($settings['seo_title']??'IZZY | Sistema de facturación y gestión empresarial'));
$seoDesc=trim((string)($settings['seo_description']??'IZZY simplifica facturación, inventario, compras, cuentas por cobrar, recursos humanos y operación de restaurantes.'));
$mapEnabled=($settings['service_map_enabled']??'1')==='1';
$mapQuery=trim((string)($settings['service_map_query']??'San Pedro Sula, Cortés, Honduras'));if($mapQuery==='')$mapQuery='San Pedro Sula, Cortés, Honduras';
$mapLabel=trim((string)($settings['service_map_label']??'Ubicación y cobertura IZZY'));
$anti=public_form_antispam_config($settings);
$turnstileReady=$anti['turnstile_enabled']&&$anti['turnstile_site_key']!==''&&$anti['turnstile_secret_stored'];
$formGuard=issue_public_form_token();
$adminPreview=!empty($_SESSION['escms_admin_id'])&&($_GET['preview']??'')==='1';
$catalog=social_platform_catalog();
$systemAccessEnabled=($settings['system_access_enabled']??'1')==='1';
$systemAccessUrl=trim((string)($settings['system_access_url']??'https://sistema.izzycloud.app/'));
$systemAccessLabel=trim((string)($settings['system_access_label']??'Ingresar a IZZY'));if($systemAccessLabel==='')$systemAccessLabel='Ingresar a IZZY';
$systemAccessNewTab=($settings['system_access_new_tab']??'1')==='1';
$plansShowImages=($settings['plans_show_images']??'0')==='1';
$chatWidgetEnabled=($settings['chat_widget_enabled']??$settings['nivo_widget_enabled']??'0')==='1';
$chatWidgetProvider=trim((string)($settings['chat_widget_provider']??$settings['nivo_widget_title']??'NIVO Web Chat'));
$chatWidgetTitle=trim((string)($settings['chat_widget_title']??$settings['nivo_widget_greeting']??'¿Necesitas ayuda?'));
$chatWidgetSubtitle=trim((string)($settings['chat_widget_subtitle']??'Chatea con nosotros'));
$chatWidgetModeRaw=(string)($settings['chat_widget_mode']??'url');
$chatWidgetMode=in_array($chatWidgetModeRaw,['url','embed'],true)?$chatWidgetModeRaw:'url';
$chatWidgetUrl=trim((string)($settings['chat_widget_url']??$settings['nivo_widget_url']??''));
$chatWidgetEmbed=trim((string)($settings['chat_widget_embed_code']??''));
$whatsappPosition=($settings['whatsapp_position']??'left')==='right'?'right':'left';
$chatWidgetPosition=$settings['chat_widget_resolved_position']??($whatsappPosition==='right'?'left':'right');
if($chatWidgetPosition===$whatsappPosition)$chatWidgetPosition=$whatsappPosition==='right'?'left':'right';
$chatWidgetReady=$chatWidgetEnabled && (
    ($chatWidgetMode==='url' && $chatWidgetUrl!=='' && filter_var($chatWidgetUrl,FILTER_VALIDATE_URL) && str_starts_with(strtolower($chatWidgetUrl),'https://'))
    || ($chatWidgetMode==='embed' && $chatWidgetEmbed!=='')
);
$socialLinks=social_public_links();
if(!$socialLinks){
    $socialLinks=[
        ['platform'=>'facebook','label'=>$catalog['facebook']['label'],'icon'=>$catalog['facebook']['icon'],'url'=>'https://www.facebook.com/esmultiserv'],
        ['platform'=>'tiktok','label'=>$catalog['tiktok']['label'],'icon'=>$catalog['tiktok']['icon'],'url'=>'https://www.tiktok.com/@evelasquez91'],
    ];
}
$landingSectionDefaults = [
    'inicio' => ['label'=>'Inicio','anchor_id'=>'inicio','navigation_label'=>'Inicio','show_in_navigation'=>1,'navigation_style'=>'link','sort_order'=>10,'active'=>1],
    'soluciones' => ['label'=>'Soluciones','anchor_id'=>'soluciones','navigation_label'=>'Soluciones','show_in_navigation'=>1,'navigation_style'=>'link','sort_order'=>20,'active'=>1],
    'modalidades' => ['label'=>'Modalidades','anchor_id'=>'modalidades','navigation_label'=>'Modalidades','show_in_navigation'=>1,'navigation_style'=>'link','sort_order'=>30,'active'=>1],
    'planes' => ['label'=>'Planes','anchor_id'=>'planes','navigation_label'=>'Planes','show_in_navigation'=>1,'navigation_style'=>'link','sort_order'=>40,'active'=>1],
    'sistema' => ['label'=>'El sistema','anchor_id'=>'sistema','navigation_label'=>'El sistema','show_in_navigation'=>1,'navigation_style'=>'link','sort_order'=>50,'active'=>1],
    'ubicacion' => ['label'=>'Ubicación','anchor_id'=>'ubicacion','navigation_label'=>'Ubicación','show_in_navigation'=>1,'navigation_style'=>'link','sort_order'=>60,'active'=>1],
    'contacto' => ['label'=>'Quiero IZZY','anchor_id'=>'contacto','navigation_label'=>'Quiero IZZY','show_in_navigation'=>1,'navigation_style'=>'cta','sort_order'=>70,'active'=>1],
];
$landingSections = $landingSectionDefaults;
try {
    $storedSections = site_sections();
    foreach ($landingSectionDefaults as $sectionKey => $defaultSection) {
        if (!isset($storedSections[$sectionKey])) {
            continue;
        }
        $stored = $storedSections[$sectionKey];
        $landingSections[$sectionKey] = array_merge($defaultSection, [
            'label' => trim((string)($stored['label'] ?? $defaultSection['label'])) ?: $defaultSection['label'],
            'anchor_id' => trim((string)($stored['anchor_id'] ?? $defaultSection['anchor_id'])) ?: $defaultSection['anchor_id'],
            'navigation_label' => trim((string)($stored['navigation_label'] ?? $defaultSection['navigation_label'])) ?: $defaultSection['navigation_label'],
            'show_in_navigation' => (int)($stored['show_in_navigation'] ?? $defaultSection['show_in_navigation']),
            'navigation_style' => (($stored['navigation_style'] ?? 'link') === 'cta') ? 'cta' : 'link',
            'sort_order' => (int)($stored['sort_order'] ?? $defaultSection['sort_order']),
            'active' => (int)($stored['active'] ?? $defaultSection['active']),
        ]);
    }
} catch (Throwable $e) {
    $landingSections = $landingSectionDefaults;
}
uasort($landingSections, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
$landingNavigation = array_values(array_filter(
    $landingSections,
    static fn(array $section): bool => (int)$section['active'] === 1 && (int)$section['show_in_navigation'] === 1
));
$landingSectionOrder = array_keys($landingSections);
$landingSectionActive = [];
foreach ($landingSections as $sectionKey => $sectionData) {
    $landingSectionActive[$sectionKey] = (int)$sectionData['active'] === 1;
}

if(($settings['maintenance_mode']??'0')==='1'&&!$adminPreview){http_response_code(503);?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>IZZY · Mantenimiento</title><style>body{font-family:system-ui;margin:0;display:grid;place-items:center;min-height:100vh;background:#f4f9fd;color:#082259}.c{text-align:center;background:#fff;padding:40px;border-radius:24px;box-shadow:0 20px 60px #0b396622}.c img{width:140px}.c p{color:#68768d}</style><div class="c"><img src="<?=h($logo)?>"><h1><?=h($settings['maintenance_title']??'Estamos mejorando IZZY')?></h1><p><?=h($settings['maintenance_text']??'Volvemos pronto.')?></p></div></html><?php exit;}
if(!$services){$services=[
 ['title'=>'Facturación electrónica con el SAR','details'=>'Emite facturas, tickets y documentos con una operación ágil y centralizada.'],
 ['title'=>'Inventario y bodegas','details'=>'Controla productos, existencias, compras y transferencias entre bodegas.'],
 ['title'=>'Cuentas por cobrar y pagar','details'=>'Da seguimiento a clientes, proveedores y movimientos financieros desde un solo lugar.'],
 ['title'=>'Reportes y análisis','details'=>'Consulta ventas, compras, productos y tendencias para tomar mejores decisiones.'],
 ['title'=>'Nómina y asistencia','details'=>'Administra colaboradores, contratos, nómina y control de asistencia.'],
 ['title'=>'Restaurantes y cocina','details'=>'Mesas, comandas, cocina, promociones, combos, cuentas abiertas y pedidos para llevar.']
];}
if(!$plans){$plans=[
 ['name'=>'Plan Básico','tagline'=>'Ideal para comenzar','price'=>1099,'billing_label'=>'/mes','features'=>"Facturación electrónica con el SAR\nControl de caja\nFormatos ticket y carta\nReporte de ventas\nRegistro de productos e inventario\nCuentas por cobrar a clientes\n1 usuario administrador\nSoporte técnico",'featured'=>0],
 ['name'=>'Plan Restaurantes','tagline'=>'Facturación ágil para restaurantes y negocios con venta visual','price'=>2499,'billing_label'=>'/mes','features'=>"Facturación recurrente automática\nPantalla de cocina\nMesas y reservaciones\nVenta visual por iconos\n1 usuario administrador\n4 usuarios adicionales\nPromociones y combos\nSoporte técnico",'featured'=>1],
 ['name'=>'Plan Regular','tagline'=>'Más usuarios, compras y control financiero','price'=>1610,'billing_label'=>'/mes','features'=>"Facturación electrónica con el SAR\nControl de caja\nCuentas por cobrar a clientes\nCuentas por pagar a proveedores\nReportes de ventas\nRegistro de productos y compras\n1 usuario administrador\n3 usuarios adicionales",'featured'=>0],
];}
$galleryDefaults=[
 ['title'=>'Dashboard IZZY','description'=>'Indicadores, reportes y control operativo desde computadora.','image_path'=>'assets/izzy/dashboard-desktop.png'],
 ['title'=>'Acceso seguro','description'=>'Nuevo acceso IZZY con experiencia premium, responsive y modo demo identificado.','image_path'=>'assets/izzy/login-desktop.png'],
 ['title'=>'IZZY en móvil','description'=>'Consulta el negocio desde tu teléfono con una interfaz adaptada.','image_path'=>'assets/izzy/mobile-dashboard.jpeg'],
 ['title'=>'Recuperación de acceso','description'=>'Flujo claro y seguro para restablecer la contraseña y recuperar el acceso a IZZY.','image_path'=>'assets/izzy/password-reset.png'],
 ['title'=>'Crear cuenta IZZY','description'=>'Registro guiado con datos esenciales, confirmación de contraseña y recomendaciones de seguridad.','image_path'=>'assets/izzy/create-account.png']
];
$showcase=$gallery?:$galleryDefaults;
$primarySolutions = [
    ['title'=>'Facturación','subtitle'=>'Electrónica y lista para operar','icon'=>'invoice'],
    ['title'=>'Inventario','subtitle'=>'Productos, compras y bodegas','icon'=>'inventory'],
    ['title'=>'Finanzas','subtitle'=>'CXC, CXP y reportes','icon'=>'finance'],
    ['title'=>'Restaurantes','subtitle'=>'Mesas, comandas y cocina','icon'=>'restaurant'],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#079bd0">
<title><?=h($seoTitle)?></title><meta name="description" content="<?=h($seoDesc)?>"><meta name="robots" content="<?=h($settings['seo_robots']??'index,follow')?>"><link rel="sitemap" type="application/xml" title="Sitemap" href="sitemap.xml">
<?php if(!empty($settings['google_site_verification'])): ?><meta name="google-site-verification" content="<?=h($settings['google_site_verification'])?>"><?php endif; ?>
<link rel="icon" type="image/png" href="<?=h($favicon ?: 'assets/izzy/logo-mark.png')?>"><link rel="shortcut icon" type="image/png" href="<?=h($favicon ?: 'assets/izzy/logo-mark.png')?>">
<link rel="stylesheet" href="<?=h(versioned_asset('assets/izzy-site.css','assets/izzy-site.css'))?>">
<link rel="stylesheet" href="assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="assets/vendor/show-notify/showNotify.css">
<link rel="stylesheet" href="assets/vendor/select2/select2.local.css">
<link rel="stylesheet" href="assets/ui-standards.css">
<link rel="stylesheet" href="assets/action-icons.css">
</head>
<body>
<header class="izzy-header">
    <div class="izzy-container izzy-nav">
        <a class="izzy-brand" href="#inicio"><img src="<?=h($logo)?>" alt="IZZY"><span><strong>IZZY</strong><small>Sistema de facturación</small></span></a>
        <button class="menu-toggle" data-menu-toggle aria-expanded="false" aria-label="Abrir menú">☰</button>
        <nav class="izzy-menu" data-menu aria-label="Menú principal">
            <?php foreach($landingNavigation as $navigationItem): ?>
                <?php if(($navigationItem['navigation_style'] ?? 'link') !== 'cta'): ?>
                    <a href="#<?=h((string)$navigationItem['anchor_id'])?>"><?=h((string)$navigationItem['navigation_label'])?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if($systemAccessEnabled&&$systemAccessUrl!==''): ?><a class="portal-link" href="<?=h($systemAccessUrl)?>" <?=$systemAccessNewTab?'target="_blank" rel="noopener"':''?>>Ingresar a IZZY</a><?php endif; ?>
            <?php foreach($landingNavigation as $navigationItem): ?>
                <?php if(($navigationItem['navigation_style'] ?? 'link') === 'cta'): ?>
                    <a class="cta" href="#<?=h((string)$navigationItem['anchor_id'])?>"><?=h((string)$navigationItem['navigation_label'])?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main data-izzy-section-root>
    <script>window.IZZY_SECTION_CONFIG=<?=json_encode(['order'=>$landingSectionOrder,'active'=>$landingSectionActive],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
    <section class="hero" id="inicio" data-managed-section="inicio" <?=!$landingSectionActive['inicio']?'hidden':''?>>
        <div class="izzy-container hero-grid">
            <div class="hero-copy reveal">
                <span class="izzy-kicker"><?=h(c('hero_eyebrow','Facturación + gestión para negocios reales'))?></span>
                <h1><?=h(c('hero_title','Factura, controla y haz crecer tu negocio con'))?> <span class="brand-word">IZZY</span>.</h1>
                <div class="hero-rotator" aria-live="polite"><span>Potencia tu negocio con</span><strong data-rotating-word data-words="Facturación|Inventario|Restaurantes|Finanzas|Reportes">Facturación</strong><i></i></div>
                <p><?=h(c('hero_text','IZZY reúne facturación electrónica, inventario, compras, cuentas, personal y reportes en un solo lugar. Ideal para negocios que quieren trabajar con más orden, rapidez y control.'))?></p>
                <div class="hero-business-fit" aria-label="Negocios que pueden usar IZZY">
                    <span class="hero-fit-label">Hecho para</span>
                    <span>Tiendas y comercios</span>
                    <span>Restaurantes</span>
                    <span>Empresas de servicios</span>
                    <span>Distribuidoras</span>
                    <span>Emprendedores y PYMES</span>
                </div>
                <div class="hero-actions">
                    <a class="btn primary" href="#planes">Ver planes</a>
                    <a class="btn secondary" href="#sistema">Conocer IZZY</a>
                    <?php if($whatsappEnabled): ?><a class="btn whatsapp" target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>?text=<?=rawurlencode($waMessage)?>">Hablar por WhatsApp</a><?php endif; ?>
                </div>
                <div class="hero-proof">
                    <span><?=ui_icon('device')?> Compatible con computadora, tablet y celular</span>
                    <span><?=ui_icon('support')?> Soporte técnico cercano</span>
                    <span><?=ui_icon('growth')?> Ideal para crecer</span>
                    <span><?=ui_icon('restaurant')?> Modo empresarial y restaurantes</span>
                </div>
                <img class="es-badge" loading="lazy" decoding="async" src="assets/izzy/es-multiservicios-badge.png" alt="Una solución de ES MULTISERVICIOS">
            </div>
            <div class="hero-visual reveal">
                <div class="hero-stage">
                    <div class="hero-window">
                        <div class="window-bar"><span></span><span></span><span></span></div>
                        <img src="assets/izzy/dashboard-desktop.png" data-zoom="assets/izzy/dashboard-desktop.png" alt="Dashboard IZZY">
                    </div>
                    <div class="hero-chip chip-a"><?=ui_icon('invoice')?><strong>Facturación</strong><small>Ágil y lista para operar</small></div>
                    <div class="hero-chip chip-b"><?=ui_icon('inventory')?><strong>Inventario</strong><small>Control de productos y compras</small></div>
                    <div class="hero-chip chip-c"><?=ui_icon('restaurant')?><strong>Restaurantes</strong><small>Venta visual y cocina</small></div>
                </div>
            </div>
        </div>
    </section>

    <section class="solution-strip" data-section-companion="inicio" <?=!$landingSectionActive['inicio']?'hidden':''?>>
        <div class="izzy-container">
            <div class="solution-strip-head reveal">
                <span class="izzy-kicker">Una solución de ES MULTISERVICIOS</span>
            </div>
            <div class="solution-grid">
                <?php foreach($primarySolutions as $solution): ?>
                    <article class="solution-card reveal">
                        <div class="solution-icon"><?=ui_icon($solution['icon'])?></div>
                        <h3><?=h($solution['title'])?></h3>
                        <p><?=h($solution['subtitle'])?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="izzy-section soft" id="soluciones" data-managed-section="soluciones" <?=!$landingSectionActive['soluciones']?'hidden':''?>>
        <div class="izzy-container">
            <div class="izzy-section-head reveal">
                <span class="izzy-kicker">Un sistema que crece contigo</span>
                <h2><?=h(c('intro_title','Lo que tu negocio necesita, conectado.'))?></h2>
                <p><?=h(c('intro_text','IZZY reúne las funciones clave de tu operación para que reduzcas pasos, tengas mayor control y tomes decisiones con información clara.'))?></p>
            </div>
            <div class="feature-grid">
                <?php foreach($services as $i=>$s): ?>
                    <article class="feature-card reveal">
                        <div class="feature-icon"><?=ui_icon(feature_icon_name($i, (string)$s['title']))?></div>
                        <h3><?=h($s['title'])?></h3>
                        <div class="rich-display public-rich-text"><?=cms_sanitize_rich_html((string)$s['details'])?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="izzy-section" id="modalidades" data-managed-section="modalidades" <?=!$landingSectionActive['modalidades']?'hidden':''?>>
        <div class="izzy-container">
            <div class="izzy-section-head reveal">
                <span class="izzy-kicker">Dos experiencias · Un mismo <span class="brand-mini">IZZY</span></span>
                <h2>Trabaja como tu negocio necesita.</h2>
                <p>Utiliza la gestión empresarial tradicional o activa una experiencia visual diseñada para restaurantes y negocios de atención rápida.</p>
            </div>
            <div class="mode-grid">
                <article class="mode-card reveal">
                    <div class="mode-media"><img loading="lazy" decoding="async" src="assets/izzy/dashboard-desktop.png" data-zoom="assets/izzy/dashboard-desktop.png" alt="IZZY empresarial"></div>
                    <div class="mode-copy">
                        <span class="izzy-kicker">IZZY Empresas</span>
                        <h3>Control integral</h3>
                        <p>Ventas, compras, inventario, finanzas, nómina, asistencia y reportes en una sola plataforma.</p>
                        <ul>
                            <li><?=ui_icon('check')?> Operación administrativa completa</li>
                            <li><?=ui_icon('check')?> Paneles, reportes y métricas</li>
                            <li><?=ui_icon('check')?> Ideal para comercios y empresas</li>
                        </ul>
                    </div>
                </article>
                <article class="mode-card reveal">
                    <div class="mode-media mode-media-mobile-journey" aria-label="Flujo móvil real de IZZY Punto de Venta">
                        <div class="mobile-journey-stage">
                            <figure class="mobile-shot mobile-shot-1">
                                <img loading="lazy" decoding="async" src="assets/izzy/pos-mobile-mesas.png" data-zoom="assets/izzy/pos-mobile-mesas.png" alt="IZZY móvil selección de mesas">
                                <figcaption>1 · Mesas</figcaption>
                            </figure>
                            <figure class="mobile-shot mobile-shot-2">
                                <img loading="lazy" decoding="async" src="assets/izzy/pos-mobile-productos.png" data-zoom="assets/izzy/pos-mobile-productos.png" alt="IZZY móvil selección de productos">
                                <figcaption>2 · Productos</figcaption>
                            </figure>
                            <figure class="mobile-shot mobile-shot-3">
                                <img loading="lazy" decoding="async" src="assets/izzy/pos-mobile-pedido.png" data-zoom="assets/izzy/pos-mobile-pedido.png" alt="IZZY móvil revisión de pedido">
                                <figcaption>3 · Pedido</figcaption>
                            </figure>
                        </div>
                        <div class="mobile-journey-caption">
                            <span>Experiencia móvil real</span>
                            <small>Mesas → productos → pedido</small>
                        </div>
                    </div>
                    <div class="mode-copy">
                        <span class="izzy-kicker">IZZY Punto de Venta Visual</span>
                        <h3>Con mesas o sin mesas: tú decides</h3>
                        <p>La misma experiencia puede trabajar como restaurante con mesas y comandas, o configurarse sin mesas para tiendas, comercios y negocios que solo necesitan vender, facturar y controlar inventario.</p>
                        <ul>
                            <li><?=ui_icon('check')?> Mesas y comandas opcionales</li>
                            <li><?=ui_icon('check')?> Venta rápida para llevar o mostrador</li>
                            <li><?=ui_icon('check')?> Cocina / comanda cuando tu operación la necesita</li>
                        </ul>
                    </div>
                </article>
            </div>

            <div class="operation-showcase reveal">
                <div class="operation-showcase-media">
                    <img loading="lazy" decoding="async"
                         src="assets/izzy/pos-visual-restaurante.png"
                         data-zoom="assets/izzy/pos-visual-restaurante.png"
                         alt="Pantalla real de IZZY para venta visual, mesas y comandas">
                    <button type="button" class="operation-zoom-hint operation-zoom-action" data-zoom-trigger="assets/izzy/pos-visual-restaurante.png"><?=ui_icon('search')?> Ver pantalla completa</button>
                </div>
                <div class="operation-showcase-copy">
                    <span class="izzy-kicker">Así se ve IZZY trabajando</span>
                    <h3>Una sola interfaz que se adapta a tu forma de vender.</h3>
                    <p>En restaurantes puedes habilitar mesas, comandas, cuentas abiertas y cocina. Si tu negocio no usa mesas, simplemente trabajas con venta directa, productos, código de barras, inventario y facturación.</p>
                    <div class="operation-mode-pills">
                        <span><?=ui_icon('check')?> Con mesas</span>
                        <span><?=ui_icon('check')?> Sin mesas</span>
                        <span><?=ui_icon('check')?> Para llevar</span>
                        <span><?=ui_icon('check')?> Cocina / comanda</span>
                    </div>
                    <div class="operation-businesses">
                        <strong>Ideal para:</strong>
                        <span>Restaurantes</span>
                        <span>Cafeterías</span>
                        <span>Tiendas</span>
                        <span>Ferreterías</span>
                        <span>Farmacias</span>
                        <span>Repuestos</span>
                        <span>Servicios y PYMES</span>
                    </div>
                    <p class="operation-note">IZZY no obliga a tu negocio a trabajar de una sola manera: activas únicamente las funciones que realmente necesitas.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="izzy-section soft" id="planes" data-managed-section="planes" <?=!$landingSectionActive['planes']?'hidden':''?>>
        <div class="izzy-container">
            <div class="izzy-section-head reveal">
                <span class="izzy-kicker">Planes <span class="brand-mini">IZZY</span></span>
                <h2>Planes premium que muestran valor desde el primer vistazo.</h2>
                <p>Precios mensuales en lempiras con una presentación profesional, clara y atractiva para impulsar la decisión de compra.</p>
            </div>
            <div class="plan-grid" data-plan-grid>
                <?php foreach($plans as $planIndex=>$p): [$features,$summary,$planLabel,$planPill,$planIcon]=plan_variant($p); ?>
                    <article class="plan-card <?=!empty($p['featured'])?'featured':''?> <?=$planIndex>=3?'plan-extra':''?> reveal" <?=$planIndex>=3?'hidden':''?>>
                        <?php if(!empty($p['featured'])): ?><span class="featured-label">Plan destacado</span><?php endif; ?>
                        <div class="plan-topline">
                            <span class="plan-mini-logo"><img src="assets/izzy/logo-mark.png" alt="IZZY"></span>
                            <span class="plan-chip"><?=h($planPill)?></span>
                        </div>
                        <?php if($plansShowImages && !empty($p['show_image']) && !empty($p['image_path'])): ?>
                            <button class="plan-promo-media" type="button" data-zoom="<?=h($p['image_path'])?>" aria-label="Ampliar imagen de <?=h($p['name'])?>">
                                <img loading="lazy" decoding="async" src="<?=h($p['image_path'])?>" alt="<?=h($p['name'])?>">
                                <span class="plan-promo-zoom" aria-hidden="true"><?=ui_icon('search')?></span>
                            </button>
                        <?php endif; ?>
                        <div class="plan-head">
                            <div>
                                <span class="plan-category"><?=h($planLabel)?></span>
                                <h3><?=h($p['name'])?></h3>
                                <p class="plan-summary"><?=h($summary)?></p>
                            </div>
                            <div class="plan-icon-badge"><?=ui_icon($planIcon)?></div>
                        </div>
                        <div class="plan-price-wrap">
                            <div class="plan-price">L. <?=number_format((float)$p['price'],0)?></div>
                            <small><?=h($p['billing_label']??'/mes')?></small>
                        </div>
                        <div class="plan-value-banner">
                            <span class="plan-value-icon"><?=ui_icon($planIcon)?></span>
                            <div><strong><?=h($planPill)?></strong><small><?=h($summary)?></small></div>
                        </div>
                        <div class="plan-feature-grid">
                            <?php foreach(array_slice($features,0,6) as $f): ?><span><?=ui_icon('check')?> <?=h($f)?></span><?php endforeach; ?>
                        </div>
                        <div class="plan-balance-fill" aria-hidden="true">
                            <span class="plan-balance-line"></span>
                            <small>IZZY · Tecnología preparada para crecer contigo</small>
                            <span class="plan-balance-line"></span>
                        </div>
                        <div class="plan-compatibility"><?=ui_icon('device')?> Compatible con computadora, tablet, celular y monitores táctiles</div>
                        <div class="plan-cta-box">
                            <?php if($whatsappEnabled): ?><a class="plan-wa" target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>?text=<?=rawurlencode('Hola, me interesa el '.$p['name'].' de IZZY.')?>"><?=ui_icon('whatsapp')?> <?=h($phone)?></a><?php endif; ?>
                            <a class="btn primary" href="#contacto">Solicitar este plan</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if(count($plans)>3): ?><div class="plans-more-wrap"><button class="plans-more-btn" type="button" data-toggle-plans aria-expanded="false"><span class="plans-more-icon">+</span><span data-toggle-plans-label>Ver todos los planes</span></button></div><?php endif; ?>
        </div>
    </section>

    <section class="izzy-section" id="sistema" data-managed-section="sistema" <?=!$landingSectionActive['sistema']?'hidden':''?>>
        <div class="izzy-container">
            <div class="izzy-section-head reveal">
                <span class="izzy-kicker">Míralo funcionando</span>
                <h2>Una experiencia visual limpia, moderna y con enfoque comercial.</h2>
                <p>Explora capturas reales del sistema y amplíalas para ver el detalle. Todo se muestra claro, bien ordenado y sin efectos oscuros que resten presencia al producto.</p>
            </div>
            <div class="showcase-grid">
                <?php foreach(array_slice($showcase,0,3) as $showcaseIndex=>$g): ?>
                    <article class="showcase-card showcase-card-<?=$showcaseIndex?> reveal">
                        <div class="showcase-media">
                            <img loading="lazy" decoding="async" src="<?=h($g['image_path'])?>" data-zoom="<?=h($g['image_path'])?>" alt="<?=h($g['title'])?>">
                            <button type="button" class="media-zoom-button" data-zoom-trigger="<?=h($g['image_path'])?>" aria-label="Ampliar <?=h($g['title'])?>" data-no-action-icon><?=ui_icon('search')?></button>
                        </div>
                        <div class="showcase-copy">
                            <h3><?=h($g['title'])?></h3>
                            <div class="public-rich-text"><?=cms_sanitize_rich_html((string)($g['description']??'Vista del sistema IZZY.'))?></div>
                            <span class="zoom-link"><?=ui_icon('search')?> <span>Ampliar</span></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if(count($showcase)>3): ?>
                <div class="gallery-extra">
                    <?php foreach(array_slice($showcase,3,6) as $g): ?>
                        <article class="gallery-card reveal">
                            <div class="gallery-card-media">
                                <img loading="lazy" decoding="async" src="<?=h($g['image_path'])?>" data-zoom="<?=h($g['image_path'])?>" alt="<?=h($g['title'])?>">
                                <button type="button" class="media-zoom-button" data-zoom-trigger="<?=h($g['image_path'])?>" aria-label="Ampliar <?=h($g['title'])?>" data-no-action-icon><?=ui_icon('search')?></button>
                            </div>
                            <div class="gallery-card-copy">
                                <h3><?=h($g['title'])?></h3>
                                <div class="public-rich-text"><?=cms_sanitize_rich_html((string)($g['description']??''))?></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="mobile-billing-showcase reveal" aria-labelledby="mobile-billing-title">
                <div class="mobile-billing-copy">
                    <span class="izzy-kicker">IZZY FACTURACIÓN MÓVIL</span>
                    <h3 id="mobile-billing-title">Factura desde tu celular sin perder el control.</h3>
                    <p>Cuando necesitas vender rápido, IZZY también puede trabajar desde el teléfono. Caja, cliente, vendedor, productos y tipo de factura quedan disponibles en una experiencia clara y adaptada a pantallas pequeñas.</p>

                    <div class="mobile-billing-points">
                        <span><?=ui_icon('check')?> Apertura y cierre de caja</span>
                        <span><?=ui_icon('check')?> Cliente y vendedor</span>
                        <span><?=ui_icon('check')?> Escaneo y selección de productos</span>
                        <span><?=ui_icon('check')?> Venta al contado o crédito</span>
                    </div>

                    <div class="mobile-billing-note">
                        <span><?=ui_icon('phone')?></span>
                        <div>
                            <strong>Tu negocio sigue contigo</strong>
                            <small>Ideal para atención rápida, mostrador, ventas móviles y operaciones que no siempre están frente a una computadora.</small>
                        </div>
                    </div>
                </div>

                <div class="mobile-billing-media">
                    <img
                        loading="lazy"
                        decoding="async"
                        src="assets/izzy/facturacion-movil-premium.png"
                        alt="Facturación móvil IZZY en celular"
                        data-zoom="assets/izzy/facturacion-movil-premium.png"
                        role="button"
                        tabindex="0"
                        aria-label="Ampliar imagen de Facturación Móvil IZZY"
                    >
                    <button
                        type="button"
                        class="media-zoom-button"
                        data-zoom-trigger="assets/izzy/facturacion-movil-premium.png"
                        aria-label="Ampliar imagen de Facturación Móvil IZZY"
                        data-no-action-icon
                    ><?=ui_icon('search')?></button>
                </div>
            </section>
        </div>
    </section>

    <section class="izzy-section soft" id="ubicacion" data-managed-section="ubicacion" <?=!$landingSectionActive['ubicacion']?'hidden':''?>>
        <div class="izzy-container">
            <div class="location-grid">
                <div class="location-copy reveal">
                    <span class="izzy-kicker">ES MULTISERVICIOS</span>
                    <div class="location-brand-premium">
                        <span class="location-brand-mark"><img src="assets/izzy/logo-mark.png" alt="" aria-hidden="true"></span>
                        <span class="location-brand-copy">
                            <b>IZZY</b>
                            <small>cerca de tu negocio</small>
                            <em>Una solución de ES MULTISERVICIOS</em>
                        </span>
                    </div>
                    <h2 class="sr-only"><?=h(c('areas_title','IZZY cerca de tu negocio.'))?></h2>
                    <p><?=h(c('areas_text','Atendemos empresas que buscan simplificar su operación con una plataforma práctica, escalable y acompañada por soporte técnico.'))?></p>
                    <div class="location-list"><?php if($areas): foreach($areas as $a): ?><span><?=ui_icon('location')?> <?=h($a['area_name'])?></span><?php endforeach; else: ?><span><?=ui_icon('location')?> San Pedro Sula</span><span><?=ui_icon('location')?> Honduras</span><span><?=ui_icon('support')?> Atención remota</span><?php endif; ?></div>
                </div>
                <?php if($mapEnabled): ?><div class="map-shell reveal"><iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?=h($mapLabel)?>" src="https://www.google.com/maps?q=<?=rawurlencode($mapQuery)?>&output=embed"></iframe></div><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="izzy-section" id="contacto" data-managed-section="contacto" <?=!$landingSectionActive['contacto']?'hidden':''?>>
        <div class="izzy-container">
            <div class="izzy-section-head reveal">
                <span class="izzy-kicker">Hablemos de tu operación</span>
                <h2><?=h(c('estimate_title','¿Quieres implementar IZZY en tu negocio?'))?></h2>
                <p><?=h(c('estimate_text','Cuéntanos qué necesitas y te ayudamos a identificar la modalidad y el plan que mejor se adapte a tu operación.'))?></p>
            </div>
            <div class="contact-grid">
                <aside class="contact-panel reveal">
                    <div class="contact-brand-premium">
                        <span class="contact-brand-mark"><img src="assets/izzy/logo-mark.png" alt="" aria-hidden="true"></span>
                        <span><b>IZZY</b><small>Tu negocio, más simple y bajo control</small></span>
                    </div>
                    <span class="izzy-kicker">Contacto directo</span>
                    <h2><?=h(c('contact_title','Estamos listos para ayudarte.'))?></h2>
                    <p>IZZY es una solución de ES MULTISERVICIOS. Te orientamos para que elijas la mejor versión según tu negocio y tus procesos.</p>
                    <div class="contact-highlights">
                        <div><strong><?=ui_icon('clock')?> Respuesta rápida</strong><span>Atendemos solicitudes por formulario, correo y WhatsApp.</span></div>
                        <div><strong><?=ui_icon('shield')?> Acompañamiento real</strong><span>Te guiamos en la selección, implementación y soporte.</span></div>
                        <div><strong><?=ui_icon('growth')?> Solución escalable</strong><span>Empieza con lo necesario y crece con IZZY.</span></div>
                    </div>
                    <div class="contact-links">
                        <?php if($whatsappEnabled): ?><a target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>?text=<?=rawurlencode($waMessage)?>"><span><?=ui_icon('whatsapp')?> WhatsApp</span><strong><?=h($phone)?></strong></a><?php endif; ?>
                        <?php if($email&&filter_var($email,FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?=h($email)?>"><span><?=ui_icon('mail')?> Correo</span><strong><?=h($email)?></strong></a><?php endif; ?>
                    </div>
                </aside>
                <form class="contact-form reveal" id="estimateForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="form_guard_token" value="<?=h($formGuard)?>">
                    <div class="public-form-trap" aria-hidden="true"><input name="website_url" tabindex="-1" autocomplete="off"></div>
                    <div class="form-grid contact-form-grid">
                        <label class="span-2">
                            <span class="field-label">Nombre o empresa <span class="required">*</span></span>
                            <input name="name" required maxlength="150" placeholder="Tu nombre o el nombre de tu negocio">
                        </label>

                        <label>
                            <span class="field-label">Teléfono</span>
                            <input name="phone" maxlength="80" placeholder="+504 ...">
                        </label>

                        <label>
                            <span class="field-label">Plan o solución</span>
                            <select name="service"><option value="">Selecciona una opción</option><?php foreach($plans as $p): ?><option><?=h($p['name'])?></option><?php endforeach; ?><option>IZZY Empresarial</option><option>IZZY Restaurantes</option></select>
                        </label>

                        <label class="span-2">
                            <span class="field-label">Correo electrónico <span class="required">*</span></span>
                            <input type="email" name="email" required maxlength="180" placeholder="tu@correo.com">
                        </label>

                        <div class="span-2 public-rich-field">
                            <span class="field-label">¿Qué necesitas? <span class="required">*</span></span>
                            <div class="public-rich-editor" data-public-rich-editor>
                                <div class="public-rich-toolbar" role="toolbar" aria-label="Formato del mensaje">
                                    <button type="button" data-rich-command="bold" data-no-action-icon title="Negrita"><strong>B</strong></button>
                                    <button type="button" data-rich-command="italic" data-no-action-icon title="Cursiva"><em>I</em></button>
                                    <button type="button" data-rich-command="insertUnorderedList" data-no-action-icon title="Lista con viñetas">• Lista</button>
                                    <button type="button" data-rich-command="insertOrderedList" data-no-action-icon title="Lista numerada">1. Lista</button>
                                    <button type="button" data-rich-clear data-no-action-icon title="Limpiar formato">Limpiar</button>
                                </div>
                                <div
                                    class="public-rich-content"
                                    contenteditable="true"
                                    role="textbox"
                                    aria-multiline="true"
                                    data-rich-content
                                    data-placeholder="Cuéntanos sobre tu negocio y lo que deseas mejorar."
                                ></div>
                                <textarea name="message" maxlength="5000" data-rich-value hidden></textarea>
                            </div>
                            <small class="public-rich-help">Puedes usar negrita, cursiva y listas para explicar mejor lo que necesitas.</small>
                        </div>

                        <input type="hidden" name="referral" value="Sitio web IZZY">
                        <?php if($turnstileReady): ?><div class="span-2 cf-turnstile" data-sitekey="<?=h($anti['turnstile_site_key'])?>" data-theme="light"></div><?php elseif($anti['turnstile_enabled']): ?><div class="span-2 form-note">La verificación Cloudflare Turnstile está activada pero falta completar las llaves en el administrador.</div><?php endif; ?>
                    </div>
                    <div class="form-actions">
                        <button class="btn primary" type="submit" <?=$anti['turnstile_enabled']&&!$turnstileReady?'disabled':''?>>Enviar solicitud</button>
                        <?php if($whatsappEnabled): ?><a class="btn whatsapp" target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>?text=<?=rawurlencode($waMessage)?>">Hablar por WhatsApp</a><?php endif; ?>
                    </div>
                    <p class="form-note">Tus datos se utilizan únicamente para responder tu solicitud.</p>
                </form>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="izzy-container footer-main">
        <div class="footer-brand">
            <img src="<?=h($logoOnDark)?>" alt="IZZY">
            <p><strong>IZZY</strong> es la solución de gestión empresarial de <strong>ES MULTISERVICIOS</strong>, diseñada para vender mejor, controlar más y crecer con una imagen profesional.</p>
        </div>
        <div class="footer-col">
            <h3>Navegación</h3>
            <?php foreach($landingNavigation as $navigationItem): ?>
                <a href="#<?=h((string)$navigationItem['anchor_id'])?>"><?=h((string)$navigationItem['navigation_label'])?></a>
            <?php endforeach; ?>
        </div>
        <div class="footer-col">
            <h3>Contacto</h3>
            <?php if($whatsappEnabled): ?><a href="https://wa.me/<?=$digits?>?text=<?=rawurlencode($waMessage)?>" target="_blank" rel="noopener"><?=ui_icon('whatsapp')?> <?=h($phone)?></a><?php endif; ?>
            <?php if($email&&filter_var($email,FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?=h($email)?>"><?=ui_icon('mail')?> <?=h($email)?></a><?php endif; ?>
            <span><?=ui_icon('location')?> San Pedro Sula, Honduras</span>
        </div>
        <div class="footer-col">
            <h3>Redes sociales</h3>
            <div class="footer-social" aria-label="Redes sociales"><?php foreach($socialLinks as $link): ?><a href="<?=h($link['url'])?>" target="_blank" rel="noopener" aria-label="<?=h($link['label'])?>" class="footer-social-link social-<?=h($link['platform'])?>"><span class="social-svg"><?=$link['icon']?></span></a><?php endforeach; ?></div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="izzy-container footer-bottom-wrap">
            <p>© <?=date('Y')?> IZZY · Una solución de ES MULTISERVICIOS.</p>
            <p>Simplifica. Controla. Crece.</p>
        </div>
    </div>
</footer>

<?php if($whatsappEnabled): ?><a class="floating-wa" target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>?text=<?=rawurlencode($waMessage)?>" aria-label="WhatsApp"><?=ui_icon('whatsapp')?></a><?php endif; ?>
<?php if($chatWidgetReady): ?>
<?php if($chatWidgetMode==='url'): ?>
<div class="nivo-floating chat-widget-position-<?=h($chatWidgetPosition)?>" data-nivo-widget>
<button class="nivo-launcher" type="button" data-nivo-toggle aria-expanded="false" aria-controls="nivo-chat-panel" data-no-action-icon>
<span class="nivo-pulse" aria-hidden="true"></span><span class="nivo-launcher-copy"><b><?=h($chatWidgetTitle)?></b><small><?=h($chatWidgetSubtitle)?></small></span><span class="nivo-launcher-mark" aria-hidden="true"><?=h(mb_strtoupper(mb_substr($chatWidgetProvider,0,1)))?></span>
</button>
<section class="nivo-panel" id="nivo-chat-panel" data-nivo-panel hidden aria-label="<?=h($chatWidgetProvider)?>"><header><div><b><?=h($chatWidgetProvider)?></b><small><?=h($chatWidgetTitle)?></small></div><button type="button" data-nivo-close data-no-action-icon aria-label="Cerrar chat">×</button></header><iframe src="<?=h($chatWidgetUrl)?>" title="<?=h($chatWidgetProvider)?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></section>
</div>
<?php else: ?>
<div class="external-chat-embed chat-widget-position-<?=h($chatWidgetPosition)?>" data-external-chat-provider="<?=h($chatWidgetProvider)?>"><?=$chatWidgetEmbed?></div>
<?php endif; ?>
<?php endif; ?>
<?php if($socialLinks): ?><nav class="social-dock floating-right" aria-label="Redes sociales"><?php foreach($socialLinks as $link): ?><a href="<?=h($link['url'])?>" target="_blank" rel="noopener" aria-label="<?=h($link['label'])?>" class="social-<?=h($link['platform'])?>"><span class="social-svg"><?=$link['icon']?></span><span class="dock-label"><?=h($link['label'])?></span></a><?php endforeach; ?></nav><?php endif; ?>

<div class="lightbox" data-lightbox aria-hidden="true"><div class="lightbox-inner"><button class="lightbox-close" type="button" aria-label="Cerrar" data-no-action-icon>×</button><img alt="Vista ampliada"></div></div><div id="toast" class="toast" role="status" aria-live="polite"></div>
<?php if($turnstileReady): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/select2/select2.local.js"></script>
<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="assets/vendor/show-notify/showNotify.js"></script>
<script src="assets/ui-standards.js"></script>
<script src="<?=h(versioned_asset('assets/izzy-site.js','assets/izzy-site.js'))?>"></script>
<script src="assets/action-icons.js"></script>
</body></html>
