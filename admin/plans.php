<?php
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');
$pdo=db();

// v1.0.40: ensure the optional public plan image flag exists on upgraded installations.
try {
    $column = $pdo->query("SHOW COLUMNS FROM izzy_plans LIKE 'show_image'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE izzy_plans ADD COLUMN show_image TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path");
        // Existing plans already had promotional images; enable them once so the new public layout can be reviewed.
        $pdo->exec("UPDATE izzy_plans SET show_image=1 WHERE image_path IS NOT NULL AND image_path<>''");
    }
} catch (Throwable $e) {
    // Keep the page usable if the database user cannot alter schema; the SQL update package contains the same migration.
}

// v1.0.41: existing installations start with the global photo switch ON so the
// administrator can review the current plan photos. Fresh installs seed it OFF.
try {
    $globalPhotoSetting = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='plans_show_images'");
    $globalPhotoSetting->execute();
    if ($globalPhotoSetting->fetchColumn() === false) {
        save_setting('plans_show_images','1');
    }
} catch (Throwable $e) {
    // The page still works; the cumulative SQL package also creates the setting.
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'');
    $id=(int)($_POST['id']??0);
    try{
        if($action==='save_global'){
            $globalImagesEnabled=((string)($_POST['plans_show_images']??'0'))==='1';
            save_setting('plans_show_images',$globalImagesEnabled?'1':'0');
            log_activity('izzy_plan_global_photo_visibility','Updated global plan photo visibility',['enabled'=>$globalImagesEnabled?1:0]);
            flash('success',$globalImagesEnabled?'Fotos de planes activadas globalmente.':'Fotos de planes ocultas globalmente.');
        }elseif($action==='save'){
            $name=trim((string)($_POST['name']??''));
            $tagline=trim((string)($_POST['tagline']??''));
            $price=max(0,(float)($_POST['price']??0));
            $billing=trim((string)($_POST['billing_label']??'/mes'));
            $features=trim((string)($_POST['features']??''));
            $sort=(int)($_POST['sort_order']??0);
            $featured=isset($_POST['featured'])?1:0;
            $showImage=isset($_POST['show_image'])?1:0;
            $activePlan=isset($_POST['active'])?1:0;
            $image=trim((string)($_POST['current_image_path']??''));
            if(isset($_POST['remove_image']))$image='';
            if(!empty($_FILES['image']['name']))$image=upload_image($_FILES['image'],'plans','plan',10);
            if($name==='')throw new RuntimeException('El nombre del plan es obligatorio.');
            if($id){
                $pdo->prepare('UPDATE izzy_plans SET name=?,tagline=?,price=?,billing_label=?,features=?,image_path=?,show_image=?,featured=?,sort_order=?,active=? WHERE id=?')
                    ->execute([$name,$tagline,$price,$billing,$features,$image,$showImage,$featured,$sort,$activePlan,$id]);
            }else{
                $pdo->prepare('INSERT INTO izzy_plans(name,tagline,price,billing_label,features,image_path,show_image,featured,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$name,$tagline,$price,$billing,$features,$image,$showImage,$featured,$sort,$activePlan]);
            }
            log_activity('izzy_plan_save','Saved IZZY plan',['plan_id'=>$id?:null,'name'=>$name]);
            flash('success','Plan guardado correctamente.');
        }elseif($action==='delete'&&$id){
            $pdo->prepare('DELETE FROM izzy_plans WHERE id=?')->execute([$id]);
            log_activity('izzy_plan_delete','Deleted IZZY plan',['plan_id'=>$id]);
            flash('success','Plan eliminado.');
        }
    }catch(Throwable $e){ flash('error',$e->getMessage()); }
    header('Location: plans.php'); exit;
}
$edit=null;
if(isset($_GET['edit'])){
    $st=$pdo->prepare('SELECT * FROM izzy_plans WHERE id=?');$st->execute([(int)$_GET['edit']]);$edit=$st->fetch();
}
$rows=$pdo->query('SELECT * FROM izzy_plans ORDER BY sort_order,id')->fetchAll();
$plansShowImages=setting('plans_show_images','0')==='1';
$pageTitle='Planes IZZY';$active='plans';require __DIR__.'/_header.php';
?>
<div class="page-heading plan-page-heading">
<div><p class="eyebrow">IZZY</p><h1>Planes y precios</h1><p class="muted">Administra los planes que se muestran en la landing page, sus precios, beneficios, imagen promocional y orden.</p></div>
<div class="heading-actions"><a class="button secondary" href="plans.php">Nuevo plan</a></div>
</div>

<section class="panel animate-in plan-master-panel" id="global-plan-images">
    <div class="plan-master-copy">
        <span class="plan-master-icon"><?=icon('image')?></span>
        <div>
            <p class="eyebrow">VISIBILIDAD GLOBAL DE FOTOS</p>
            <h2>¿Mostrar fotografías en las tarjetas de TODOS los planes?</h2>
            <p>Este control manda sobre toda la sección pública. Cada plan mantiene además su propio control individual.</p>
        </div>
    </div>
    <form method="post" class="plan-master-form">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="action" value="save_global">
        <div class="plan-master-options" role="radiogroup" aria-label="Mostrar fotografías de planes">
            <label class="plan-master-choice">
                <input type="radio" name="plans_show_images" value="1" <?=$plansShowImages?'checked':''?>>
                <span class="choice-dot" aria-hidden="true"></span>
                <span><b>Sí, mostrar fotos</b><small>Respeta el ajuste individual de cada plan.</small></span>
            </label>
            <label class="plan-master-choice">
                <input type="radio" name="plans_show_images" value="0" <?=!$plansShowImages?'checked':''?>>
                <span class="choice-dot" aria-hidden="true"></span>
                <span><b>No, ocultar todas</b><small>Oculta todas las fotos sin borrar ningún archivo.</small></span>
            </label>
        </div>
        <button type="submit">Guardar visibilidad global</button>
    </form>
    <div class="plan-master-status <?=$plansShowImages?'is-on':'is-off'?>">
        <b><?=$plansShowImages?'ACTIVO':'OCULTO'?></b>
        <span><?=$plansShowImages?'La web puede mostrar fotos según cada plan.':'La web no muestra ninguna foto de plan.'?></span>
    </div>
</section>

<section class="panel animate-in">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>"><input type="hidden" name="current_image_path" value="<?=h($edit['image_path']??'')?>">
<div class="two-col"><label>Nombre del plan<input name="name" required autofocus value="<?=h($edit['name']??'')?>"></label><label>Orden<input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??0))?>"></label></div>
<label>Frase / enfoque<input name="tagline" value="<?=h($edit['tagline']??'')?>" placeholder="Ideal para comenzar"></label>
<div class="two-col"><label>Precio mensual (L.)<input type="number" min="0" step="0.01" name="price" value="<?=h((string)($edit['price']??0))?>"></label><label>Etiqueta de cobro<input name="billing_label" value="<?=h($edit['billing_label']??'/mes')?>" placeholder="/mes"></label></div>
<label>Beneficios <small class="muted">Uno por línea.</small><textarea name="features" rows="9" required data-plain-text><?=h($edit['features']??'')?></textarea></label>
<div class="service-icon-editor plan-image-editor">
    <div>
        <strong>Imagen promocional del plan</strong>
        <p class="muted">JPG, PNG o WEBP. Puedes reemplazarla cuando quieras.</p>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
    </div>
    <?php if(!empty($edit['image_path'])): ?>
        <div class="service-icon-current plan-current-image">
            <button type="button" class="plan-image-preview" data-preview-src="../<?=h($edit['image_path'])?>" data-preview-caption="<?=h($edit['name']??'Imagen del plan')?>" aria-label="Ver imagen ampliada">
                <img src="../<?=h($edit['image_path'])?>" alt="Imagen actual">
                <span class="plan-image-loupe"><?=icon('search')?></span>
            </button>
            <label class="check-row"><input type="checkbox" name="remove_image"> Quitar imagen</label>
        </div>
    <?php endif; ?>
</div>
<div class="plan-toggle-grid">
    <label class="check-row status-switch"><input type="checkbox" name="show_image" <?=!empty($edit['show_image'])?'checked':''?>> <span><strong>Mostrar foto en el sitio</strong><small>La imagen se verá dentro de la tarjeta pública del plan.</small></span></label>
    <label class="check-row status-switch"><input type="checkbox" name="featured" <?=!empty($edit['featured'])?'checked':''?>> <span><strong>Destacar plan</strong><small>Aplica el estilo recomendado.</small></span></label>
    <label class="check-row status-switch"><input type="checkbox" name="active" <?=!$edit||!empty($edit['active'])?'checked':''?>> <span><strong>Publicar</strong><small>Muestra este plan en la web pública.</small></span></label>
</div>
<div class="form-actions"><button>Guardar plan</button><?php if($edit): ?><a class="button secondary" href="plans.php">Cancelar</a><?php endif; ?></div>
</form></section>

<section class="panel animate-in plan-list-global-tools">
    <div class="plan-list-global-copy">
        <span class="plan-list-global-icon"><?=icon('image')?></span>
        <div>
            <strong>Fotos en todos los planes</strong>
            <small><?=$plansShowImages
                ? 'Activadas globalmente. Cada plan puede decidir además si muestra su propia imagen.'
                : 'Ocultas globalmente. Ninguna tarjeta pública mostrará fotografía hasta activarlas.'?></small>
        </div>
    </div>
    <form method="post" class="plan-list-global-form">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="action" value="save_global">
        <input type="hidden" name="plans_show_images" value="<?=$plansShowImages?'0':'1'?>">
        <button type="submit" class="button <?=$plansShowImages?'secondary':''?>">
            <?=icon($plansShowImages?'eye-off':'eye')?>
            <?=$plansShowImages?'Ocultar fotos en TODOS':'Mostrar fotos en TODOS'?>
        </button>
    </form>
</section>

<div class="list-grid plan-admin-list">
<?php foreach($rows as $r): ?>
    <article class="list-card animate-in service-admin-card plan-admin-card">
        <div class="service-admin-main">
            <?php if($r['image_path']): ?>
                <button type="button" class="plan-admin-thumb" data-preview-src="../<?=h($r['image_path'])?>" data-preview-caption="<?=h($r['name'])?>" aria-label="Ampliar imagen de <?=h($r['name'])?>">
                    <img class="service-admin-badge" src="../<?=h($r['image_path'])?>" alt="<?=h($r['name'])?>">
                    <span class="plan-image-loupe"><?=icon('search')?></span>
                </button>
            <?php else: ?>
                <div class="service-admin-badge placeholder">Sin imagen</div>
            <?php endif; ?>
            <div class="service-admin-copy">
                <div class="list-head">
                    <div><strong><?=h($r['name'])?></strong><small>L. <?=number_format((float)$r['price'],2)?> <?=h($r['billing_label'])?> · Orden <?=$r['sort_order']?></small></div>
                    <span class="badge <?=$r['active']?'contacted':'closed'?>"><?=$r['active']?'Publicado':'Oculto'?></span>
                </div>
                <p><?=h($r['tagline'])?></p>
                <div class="plan-admin-flags">
                    <span class="<?=!empty($r['show_image'])?'is-on':'is-off'?>"><?=!empty($r['show_image'])?'Foto visible':'Foto oculta'?></span>
                    <?php if(!empty($r['featured'])): ?><span class="is-featured">Destacado</span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="actions"><details class="action-menu"><summary>Acciones ▾</summary><nav class="action-menu-list"><a class="action-menu-item action-menu-item--edit" href="?edit=<?=$r['id']?>"><?=icon('edit')?> Editar</a><form method="post" data-swal-confirm="¿Eliminar este plan?" data-swal-text="Esta acción no se puede deshacer."><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="action-menu-item action-menu-item--danger" style="background:transparent!important;background-image:none!important;border-color:transparent!important;box-shadow:none!important"><?=icon('trash')?> Eliminar</button></form></nav></details></div>
    </article>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/_footer.php';
