<?php
require __DIR__.'/bootstrap.php';
require_permission('seo.manage');

function seo_public_base_url(array $settings): string {
    $raw=trim((string)($settings['website']??'https://izzycloud.app'));
    if($raw==='')$raw='https://izzycloud.app';
    if(!preg_match('~^https?://~i',$raw))$raw='https://'.$raw;

    $parts=@parse_url($raw);
    $host=strtolower((string)($parts['host']??''));
    if($host==='izzycloud.app' || $host==='www.izzycloud.app'){
        return 'https://izzycloud.app';
    }

    return rtrim($raw,'/');
}
function seo_write_public_file(string $name,string $contents): void {
    $root=dirname(__DIR__);
    $path=$root.DIRECTORY_SEPARATOR.$name;
    $written=@file_put_contents($path,$contents,LOCK_EX);
    if($written===false)throw new RuntimeException('No se pudo escribir '.$name.'. Revisa permisos de la raíz del sitio.');
}
$set=settings();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $action=(string)($_POST['action']??'save_seo');

        if($action==='generate_robots' || $action==='generate_sitemap'){
            $current=settings();
            $base=seo_public_base_url($current);

            if($action==='generate_robots'){
                $robots="User-agent: *\n";
                $robots.="Allow: /\n";
                $robots.="Disallow: /admin/\n";
                $robots.="Disallow: /install/\n";
                $robots.="Disallow: /config/\n";
                $robots.="Disallow: /core/\n";
                $robots.="Disallow: /uploads/backups/\n";
                $robots.="Disallow: /email-validate.php\n";
                $robots.="Disallow: /estimate-submit.php\n";
                $robots.="Sitemap: ".$base."/sitemap.xml\n";
                seo_write_public_file('robots.txt',$robots);
                log_activity('seo_robots_generate','Generated robots.txt',['url'=>$base.'/robots.txt']);
                flash('success','robots.txt generado correctamente.');
            }else{
                $today=date('Y-m-d');
                $loc=htmlspecialchars($base.'/',ENT_XML1|ENT_QUOTES,'UTF-8');
                $xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
                $xml.="<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
                $xml.="  <url>\n";
                $xml.="    <loc>".$loc."</loc>\n";
                $xml.="    <lastmod>".$today."</lastmod>\n";
                $xml.="    <changefreq>weekly</changefreq>\n";
                $xml.="    <priority>1.0</priority>\n";
                $xml.="  </url>\n";
                $xml.="</urlset>\n";
                seo_write_public_file('sitemap.xml',$xml);
                log_activity('seo_sitemap_generate','Generated sitemap.xml',['url'=>$base.'/sitemap.xml']);
                flash('success','sitemap.xml generado correctamente.');
            }
            header('Location: seo.php'); exit;
        }

        foreach(['seo_title','seo_description','seo_robots','google_site_verification'] as $k)save_setting($k,trim((string)($_POST[$k]??'')));
        if(!empty($_FILES['seo_social_image']['name'])) {
            $p=upload_image($_FILES['seo_social_image'],'seo','social',8);
            save_setting('seo_social_image',$p);
            media_add($p,'SEO social image');
        }
        if(isset($_POST['remove_social']))save_setting('seo_social_image','');
        log_activity('seo_update','Updated SEO settings');
        flash('success','Configuración SEO guardada.');
        header('Location: seo.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$set=settings();
$seoTitleValue=trim((string)($set['seo_title']??''));
$seoDescValue=trim((string)($set['seo_description']??''));
$seoSocialImage=trim((string)($set['seo_social_image']??''));
$seoRobots=(string)($set['seo_robots']??'index,follow');
$seoVerification=trim((string)($set['google_site_verification']??''));
$seoChecks=[
 ['label'=>'Título SEO definido','ok'=>mb_strlen($seoTitleValue)>=35 && mb_strlen($seoTitleValue)<=70],
 ['label'=>'Descripción optimizada','ok'=>mb_strlen($seoDescValue)>=100 && mb_strlen($seoDescValue)<=180],
 ['label'=>'Indexación habilitada','ok'=>$seoRobots==='index,follow'],
 ['label'=>'Imagen social configurada','ok'=>$seoSocialImage!==''],
 ['label'=>'Google Search Console','ok'=>$seoVerification!==''],
];
$seoPassed=count(array_filter($seoChecks,fn($item)=>$item['ok']));
$seoScore=(int)round(($seoPassed/max(1,count($seoChecks)))*100);

$publicRoot=dirname(__DIR__);
$robotsPath=$publicRoot.DIRECTORY_SEPARATOR.'robots.txt';
$sitemapPath=$publicRoot.DIRECTORY_SEPARATOR.'sitemap.xml';
$robotsExists=is_file($robotsPath);
$sitemapExists=is_file($sitemapPath);
$seoBaseUrl=seo_public_base_url($set);
$robotsUpdated=$robotsExists?date('d/m/Y H:i',(int)filemtime($robotsPath)):'Sin generar';
$sitemapUpdated=$sitemapExists?date('d/m/Y H:i',(int)filemtime($sitemapPath)):'Sin generar';
$pageTitle='SEO Manager';
$active='seo';
require __DIR__.'/_header.php';
?>
<div class="page-heading animate-in">
<div><p class="eyebrow">SEO MANAGER</p><h1>Visibilidad en buscadores</h1><p class="muted">Controla cómo aparece IZZY en Google y cuando compartes el sitio en redes sociales.</p></div>
<div class="seo-score-card"><span class="seo-score-ring" style="--score:<?=$seoScore?>"><b><?=$seoScore?>%</b></span><span><b>Estado SEO</b><small><?=$seoPassed?> de <?=count($seoChecks)?> controles completos</small></span></div>
</div>
<?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<div class="seo-workspace seo-workspace-final">
    <section class="panel seo-editor-panel">
        <div class="panel-heading">
            <div class="panel-icon"><?=icon('search')?></div>
            <div>
                <h2>Configuración principal</h2>
                <p>Define cómo se presenta IZZY en buscadores y redes sociales.</p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" data-unsaved-form>
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="action" value="save_seo">

            <label>Título para navegador y Google
                <input name="seo_title" maxlength="70" value="<?=h($set['seo_title']??'')?>" data-seo-title>
                <small class="field-hint">Recomendado: 50–60 caracteres.</small>
            </label>

            <label>Meta descripción
                <textarea name="seo_description" maxlength="180" data-seo-description><?=h($set['seo_description']??'')?></textarea>
                <small class="field-hint">Resume qué es IZZY y qué problema resuelve en 120–160 caracteres.</small>
            </label>

            <div class="two-col">
                <label>Google Site Verification
                    <input name="google_site_verification" maxlength="255" value="<?=h($set['google_site_verification']??'')?>" placeholder="Código de Google Search Console">
                    <small class="field-hint">Pega solo el valor del atributo <code>content</code>.</small>
                </label>
                <label>Indexación
                    <select name="seo_robots">
                        <option value="index,follow" <?=($set['seo_robots']??'')==='index,follow'?'selected':''?>>Indexar y seguir enlaces</option>
                        <option value="noindex,nofollow" <?=($set['seo_robots']??'')==='noindex,nofollow'?'selected':''?>>Ocultar de buscadores</option>
                    </select>
                    <small class="field-hint">Para producción usa “Indexar y seguir enlaces”.</small>
                </label>
            </div>

            <div class="seo-social-upload">
                <div class="section-heading compact">
                    <div>
                        <h3>Imagen para compartir</h3>
                        <p class="muted">Puede aparecer al compartir IZZY en WhatsApp, Facebook u otras plataformas.</p>
                    </div>
                </div>
                <?php if($seoSocialImage!==''): ?>
                <div class="seo-current-social">
                    <img src="../<?=h($seoSocialImage)?>" alt="Imagen social actual">
                    <div><b>Imagen social actual</b><small><?=h($seoSocialImage)?></small></div>
                    <label class="premium-check compact-check"><input type="checkbox" name="remove_social" value="1"><span class="check-ui"></span><span>Quitar imagen</span></label>
                </div>
                <?php endif; ?>
                <div class="upload-zone seo-upload-zone" data-upload-zone tabindex="0">
                    <div class="upload-icon"><?=icon('image')?></div>
                    <strong>Adjuntar imagen social</strong>
                    <small data-upload-name>Arrastra, pega o selecciona JPG, PNG o WEBP</small>
                    <input type="file" name="seo_social_image" accept="image/jpeg,image/png,image/webp">
                    <div class="upload-preview" data-upload-preview></div>
                </div>
            </div>

            <div class="form-actions"><button>Guardar configuración SEO</button></div>
        </form>
    </section>

    <div class="seo-right-stack">
        <section class="search-preview google-preview-card" data-seo-preview>
            <div class="search-preview-head">
                <div><span>Vista previa en Google</span><small>Resultado aproximado en escritorio.</small></div>
                <span class="search-preview-badge">Google · Desktop</span>
            </div>
            <div class="google-preview-browser">
                <div class="google-browser-bar">
                    <span class="browser-dot"></span><span class="browser-dot"></span><span class="browser-dot"></span>
                    <div class="browser-address">google.com/search?q=IZZY</div>
                </div>
                <div class="google-result">
                    <div class="google-site-row">
                        <span class="google-favicon">I</span>
                        <span class="google-site-copy"><strong>IZZY</strong><small>https://<span data-seo-domain><?=h($set['website']??'izzycloud.app')?></span>/</small></span>
                        <span class="google-more" aria-hidden="true">⋮</span>
                    </div>
                    <h3 data-seo-preview-title><?=h($set['seo_title']??'IZZY | Sistema de gestión empresarial')?></h3>
                    <p data-seo-preview-description><?=h($set['seo_description']??'Agrega una descripción clara para que los usuarios entiendan qué ofrece tu sitio.')?></p>
                </div>
            </div>
            <div class="seo-preview-metrics">
                <div><span>Título</span><b data-seo-title-count>0/70</b><small>Ideal: 50–60 caracteres</small></div>
                <div><span>Descripción</span><b data-seo-description-count>0/180</b><small>Ideal: 120–160 caracteres</small></div>
            </div>
        </section>

        <section class="panel seo-checklist-panel">
            <div class="section-heading compact">
                <div><p class="eyebrow">SALUD SEO</p><h3>Lista de revisión</h3><p class="muted">Verifica los puntos básicos antes de publicar cambios.</p></div>
            </div>
            <div class="seo-checklist">
                <?php foreach($seoChecks as $check): ?>
                <div class="seo-check <?=$check['ok']?'ok':'pending'?>">
                    <span><?=$check['ok']?icon('check'):icon('info')?></span>
                    <b><?=h($check['label'])?></b>
                    <small><?=$check['ok']?'Correcto':'Pendiente'?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<div class="seo-lower-final">
    <section class="panel seo-social-preview">
        <div class="section-heading compact">
            <div><p class="eyebrow">SOCIAL</p><h3>Vista previa al compartir</h3><p class="muted">Así puede verse el enlace cuando alguien comparte IZZY.</p></div>
        </div>
        <div class="social-share-card">
            <div class="social-share-image <?=$seoSocialImage===''?'is-default-logo':'has-custom-image'?>">
                <?php if($seoSocialImage!==''): ?><img src="../<?=h($seoSocialImage)?>" alt="Preview social"><?php else: ?><img src="../assets/izzy/logo-full-dark.png" alt="IZZY"><?php endif; ?>
            </div>
            <div class="social-share-copy">
                <small><?=h($set['website']??'izzycloud.app')?></small>
                <b><?=h($seoTitleValue!==''?$seoTitleValue:'IZZY | Sistema de gestión empresarial')?></b>
                <span><?=h($seoDescValue!==''?$seoDescValue:'Facturación y gestión empresarial con IZZY.')?></span>
            </div>
        </div>
    </section>

    <section class="panel seo-technical-panel">
        <div class="section-heading compact">
            <div><p class="eyebrow">SEO TÉCNICO</p><h3>Robots y Sitemap</h3><p class="muted">Archivos públicos para rastreo e indexación.</p></div>
        </div>
        <div class="seo-tech-grid">
            <article class="seo-tech-card <?=$robotsExists?'ready':'missing'?>">
                <div class="seo-tech-icon"><?=icon('shield')?></div>
                <div class="seo-tech-copy">
                    <span class="seo-tech-status"><?=$robotsExists?'LISTO':'PENDIENTE'?></span>
                    <h4>robots.txt</h4>
                    <p>Permite rastreo público, bloquea administración/instalador y anuncia el sitemap.</p>
                    <small><?=h($seoBaseUrl.'/robots.txt')?> · <?=h($robotsUpdated)?></small>
                </div>
                <form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="generate_robots"><button class="button secondary"><?=$robotsExists?'Regenerar':'Generar'?></button></form>
            </article>
            <article class="seo-tech-card <?=$sitemapExists?'ready':'missing'?>">
                <div class="seo-tech-icon"><?=icon('external')?></div>
                <div class="seo-tech-copy">
                    <span class="seo-tech-status"><?=$sitemapExists?'LISTO':'PENDIENTE'?></span>
                    <h4>sitemap.xml</h4>
                    <p>Publica la URL principal del sitio para facilitar su descubrimiento e indexación.</p>
                    <small><?=h($seoBaseUrl.'/sitemap.xml')?> · <?=h($sitemapUpdated)?></small>
                </div>
                <form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="generate_sitemap"><button class="button secondary"><?=$sitemapExists?'Regenerar':'Generar'?></button></form>
            </article>
        </div>
        <div class="seo-tech-note"><?=icon('info')?><span><b>Indexación:</b> mantén “Indexar y seguir enlaces” para producción. Luego puedes registrar el sitemap en Google Search Console.</span></div>
    </section>
</div>
<?php
require __DIR__.'/_footer.php';
