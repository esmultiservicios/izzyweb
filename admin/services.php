<?php
require __DIR__.'/bootstrap.php';
require_permission('services.manage');
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);

    try {
        if($action==='save') {
            $title=trim((string)($_POST['title']??''));
            $details=cms_prepare_rich_html((string)($_POST['details']??''));
            $sort=(int)($_POST['sort_order']??0);
            $active=isset($_POST['active'])?1:0;
            $iconPath=trim((string)($_POST['current_icon_path']??''));

            if(isset($_POST['remove_icon'])) $iconPath='';
            if(!empty($_FILES['icon']['name'])) {
                $iconPath=upload_image($_FILES['icon'],'service-icons','service-icon',8);
            }

            if($title==='') throw new RuntimeException('Service title is required.');

            if($id) {
                $pdo->prepare('UPDATE services SET title=?,details=?,icon_path=?,sort_order=?,active=? WHERE id=?')
                    ->execute([$title,$details,$iconPath,$sort,$active,$id]);
            } else {
                $pdo->prepare('INSERT INTO services(title,details,icon_path,sort_order,active) VALUES(?,?,?,?,?)')
                    ->execute([$title,$details,$iconPath,$sort,$active]);
            }
            log_activity('service_save','Saved website service',['service_id'=>$id?:null,'title'=>$title]);
            flash('success','Service saved.');
        } elseif($action==='delete'&&$id) {
            $pdo->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
            log_activity('service_delete','Deleted website service',['service_id'=>$id]);
            flash('success','Service deleted.');
        }
    } catch(Throwable $e) {
        flash('error',$e->getMessage());
    }
    header('Location: services.php');
    exit;
}

$edit=null;
if(isset($_GET['edit'])) {
    $st=$pdo->prepare('SELECT * FROM services WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit=$st->fetch();
}
$rows=$pdo->query('SELECT * FROM services ORDER BY sort_order,id')->fetchAll();
$pageTitle='Services';
$active='services';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">SERVICES</p>
        <h1>Manage services</h1>
        <p class="muted">Keep the service catalog organized and give every service its own approved badge or image.</p>
    </div>
    <a class="button secondary" href="services.php">New service</a>
</div>

<section class="panel animate-in">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>">
        <input type="hidden" name="current_icon_path" value="<?=h($edit['icon_path']??'')?>">

        <div class="two-col">
            <label>Service title
                <input name="title" required value="<?=h($edit['title']??'')?>">
            </label>
            <label>Display order
                <input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??0))?>">
            </label>
        </div>

        <label>Subservices / details
            <textarea name="details" required data-rich-text><?=h($edit['details']??'')?></textarea>
        </label>

        <div class="service-icon-editor">
            <div>
                <strong>Service badge / icon</strong>
                <p class="muted">Upload any approved JPG, PNG or WEBP. This is not limited to the current eight services.</p>
                <input type="file" name="icon" accept="image/jpeg,image/png,image/webp">
            </div>
            <?php if(!empty($edit['icon_path'])): ?>
                <div class="service-icon-current">
                    <button type="button" class="service-image-preview" data-preview-src="../<?=h($edit['icon_path'])?>" data-preview-caption="<?=h($edit['title']??'Service image')?>" aria-label="Preview service image">
                        <img src="../<?=h($edit['icon_path'])?>" alt="Current service badge">
                        <span class="plan-image-loupe"><?=icon('search')?></span>
                    </button>
                    <label class="check-row"><input type="checkbox" name="remove_icon"> Remove current badge</label>
                </div>
            <?php endif; ?>
        </div>

        <label class="check-row status-switch">
            <input type="checkbox" name="active" <?=!$edit||!empty($edit['active'])?'checked':''?>> Publish on website
        </label>
        <div class="form-actions">
            <button>Save service</button>
            <?php if($edit): ?><a class="button secondary" href="services.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</section>

<div class="list-grid">
<?php foreach($rows as $r): ?>
    <article class="list-card animate-in service-admin-card">
        <div class="service-admin-main">
            <?php if(!empty($r['icon_path'])): ?>
                <button type="button" class="service-admin-thumb" data-preview-src="../<?=h($r['icon_path'])?>" data-preview-caption="<?=h($r['title'])?>" aria-label="Preview image for <?=h($r['title'])?>">
                    <img class="service-admin-badge" src="../<?=h($r['icon_path'])?>" alt="<?=h($r['title'])?> badge">
                    <span class="plan-image-loupe"><?=icon('search')?></span>
                </button>
            <?php else: ?>
                <div class="service-admin-badge placeholder">No badge</div>
            <?php endif; ?>
            <div class="service-admin-copy">
                <div class="list-head">
                    <div><strong><?=h($r['title'])?></strong><small>Order <?=$r['sort_order']?></small></div>
                    <span class="badge <?=$r['active']?'contacted':'closed'?>"><?=$r['active']?'Published':'Hidden'?></span>
                </div>
                <div class="rich-display service-details-display"><?=cms_sanitize_rich_html((string)$r['details'])?></div>
            </div>
        </div>
        <div class="actions">
            <details class="action-menu">
                <summary>Actions ▾</summary>
                <nav class="action-menu-list">
                    <a class="action-menu-item action-menu-item--edit" href="?edit=<?=$r['id']?>"><?=icon('edit')?> Edit</a>
                    <form method="post" data-swal-confirm="Delete this service?" data-swal-text="This action cannot be undone.">
                        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?=$r['id']?>">
                        <button class="action-menu-item action-menu-item--danger" style="background:transparent!important;background-image:none!important;border-color:transparent!important;box-shadow:none!important"><?=icon('trash')?> Delete</button>
                    </form>
                </nav>
            </details>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/_footer.php';
