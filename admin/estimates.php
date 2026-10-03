<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require_once __DIR__.'/../core/EmailService.php';
require_permission('estimates.view');

const ESTIMATE_REPLY_MAX_FILES=5;
const ESTIMATE_REPLY_MAX_BYTES=2621440;

function estimate_sanitize_html(string $html):string {
    $html=str_replace("\0",'',$html);
    do {
        $before=$html;
        $html=preg_replace('~<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\1\s*>~is','',$html)??'';
    } while($before!==$html);
    $html=preg_replace('/<!--.*?-->/s','',$html)??'';
    $html=strip_tags($html,'<p><br><strong><b><em><i><u><ul><ol><li><blockquote>');
    return trim(preg_replace_callback(
        '~<\s*(/?)\s*(p|br|strong|b|em|i|u|ul|ol|li|blockquote)\b[^>]*>~i',
        static function(array $m):string {
            $tag=strtolower($m[2]);
            return $m[1]==='/'&&$tag==='br'?'':'<'.$m[1].$tag.'>';
        },$html
    )??'');
}
function estimate_html_has_text(string $html):bool {
    $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
    return (preg_replace('/[\s\x{00A0}]+/u','',$text)??'')!=='';
}
function estimate_safe_upload_path(string $relative):?string {
    $relative=str_replace('\\','/',trim($relative));
    if($relative===''||str_starts_with($relative,'/')||str_contains($relative,"\0"))return null;
    $parts=explode('/',$relative);
    if(($parts[0]??'')!=='uploads'||in_array('..',$parts,true))return null;
    $real=@realpath(ROOT_DIR.'/'.$relative);
    $root=@realpath(UPLOAD_DIR);
    if($real===false||$root===false||!is_file($real))return null;
    return str_starts_with($real,rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)?$real:null;
}
function estimate_remove_files(array $paths):int {
    $failed=0;
    foreach(array_unique(array_filter(array_map('strval',$paths))) as $path) {
        $absolute=estimate_safe_upload_path($path);
        if($absolute!==null&&!@unlink($absolute))$failed++;
    }
    return $failed;
}
function estimate_validate_reply_files(array $files):array {
    if(count($files)>ESTIMATE_REPLY_MAX_FILES)throw new RuntimeException('Attach no more than 5 files per response.');
    $types=[
        'pdf'=>['application/pdf'],
        'doc'=>['application/msword','application/cdfv2','application/x-ole-storage'],
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
        'xls'=>['application/vnd.ms-excel','application/cdfv2','application/x-ole-storage'],
        'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
        'txt'=>['text/plain'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],
        'png'=>['image/png'],'webp'=>['image/webp'],
    ];
    $errors=[UPLOAD_ERR_INI_SIZE=>'A file exceeds the server upload limit.',UPLOAD_ERR_FORM_SIZE=>'A file exceeds the form upload limit.',UPLOAD_ERR_PARTIAL=>'A file was only partially uploaded.',UPLOAD_ERR_NO_TMP_DIR=>'The server upload directory is unavailable.',UPLOAD_ERR_CANT_WRITE=>'The server could not save an uploaded file.',UPLOAD_ERR_EXTENSION=>'A server extension stopped the upload.'];
    $validated=[];$total=0;
    foreach($files as $file) {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK)throw new RuntimeException($errors[$error]??'An attachment could not be uploaded.');
        $tmp=(string)($file['tmp_name']??'');
        if($tmp===''||!is_uploaded_file($tmp)||!is_file($tmp))throw new RuntimeException('An uploaded attachment is not a valid temporary file. Please choose it again.');
        $size=(int)filesize($tmp);$total+=$size;
        if($size<1)throw new RuntimeException('Empty attachments are not allowed.');
        if($total>ESTIMATE_REPLY_MAX_BYTES)throw new RuntimeException('Attachments may use at most 2.5 MB combined.');
        $name=trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/u',' ',basename((string)($file['name']??'')))??'');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if($name===''||!isset($types[$ext]))throw new RuntimeException('Allowed attachments: PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, JPEG, PNG and WEBP.');
        $mime=secure_file_mime_type($tmp);
        if(!in_array($mime,$types[$ext],true))throw new RuntimeException('The real file type does not match “'.$name.'”.');
        if(in_array($ext,['docx','xlsx'],true)&&$mime==='application/zip') {
            if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP/ZipArchive is required to verify Office attachments securely. Review Website Health.');
            $zip=new ZipArchive();
            if($zip->open($tmp)!==true)throw new RuntimeException('The Office attachment “'.$name.'” is not valid.');
            $valid=$zip->locateName('[Content_Types].xml')!==false&&$zip->locateName($ext==='docx'?'word/document.xml':'xl/workbook.xml')!==false;
            $zip->close();
            if(!$valid)throw new RuntimeException('The Office attachment “'.$name.'” could not be verified.');
        }
        $validated[]=['tmp'=>$tmp,'original_name'=>substr($name,0,255),'mime_type'=>$mime,'file_size'=>$size,'extension'=>$ext==='jpeg'?'jpg':$ext];
    }
    return $validated;
}
function estimate_store_reply_files(array $files,int $estimateId):array {
    if(!$files)return [];
    $dir=UPLOAD_DIR.'/estimate-replies/'.$estimateId;
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('The response attachment directory could not be created.');
    $stored=[];
    try {
        foreach($files as $file) {
            $physical=bin2hex(random_bytes(20)).'.'.$file['extension'];
            $absolute=$dir.'/'.$physical;
            if(!move_uploaded_file($file['tmp'],$absolute))throw new RuntimeException('A response attachment could not be saved.');
            @chmod($absolute,0644);
            $stored[]=$file+['path'=>$absolute,'file_path'=>'uploads/estimate-replies/'.$estimateId.'/'.$physical];
        }
        return $stored;
    } catch(Throwable $e) {
        foreach($stored as $file)@unlink($file['path']);
        throw $e;
    }
}

$pdo=db();$me=current_admin();
$canAll=user_can('estimates.manage_all');
$canAssigned=user_can('estimates.manage_assigned')||$canAll;
$error='';

if(isset($_GET['download_reply_attachment'])) {
    $st=$pdo->prepare('SELECT a.*,e.assigned_to FROM estimate_reply_attachments a JOIN estimate_requests e ON e.id=a.estimate_id WHERE a.id=?');
    $st->execute([(int)$_GET['download_reply_attachment']]);$download=$st->fetch();
    if(!$download||(!$canAll&&(int)($download['assigned_to']??0)!==(int)$me['id'])){http_response_code(404);exit('Attachment not found.');}
    $absolute=estimate_safe_upload_path((string)$download['file_path']);
    if($absolute===null){http_response_code(404);exit('Attachment file not found.');}
    $name=preg_replace('/[\r\n"]+/',' ',(string)$download['original_name'])?:'attachment';
    header('X-Content-Type-Options: nosniff');header('Content-Type: '.(string)$download['mime_type']);header('Content-Length: '.filesize($absolute));
    header('Content-Disposition: attachment; filename="'.str_replace('"','',$name).'"; filename*=UTF-8\'\''.rawurlencode($name));readfile($absolute);exit;
}

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();$id=(int)($_POST['id']??0);$stored=[];
    try {
        $st=$pdo->prepare('SELECT * FROM estimate_requests WHERE id=?');$st->execute([$id]);$cur=$st->fetch();
        if(!$cur)throw new RuntimeException('Request not found.');
        $action=(string)($_POST['action']??'update');
        if($action==='delete_permanent'&&!$canAll)throw new RuntimeException('Only users with Manage All permission can permanently delete requests.');
        if($action!=='delete_permanent'&&(!$canAssigned||(!$canAll&&(int)($cur['assigned_to']??0)!==(int)$me['id'])))throw new RuntimeException('This request is not assigned to you or you lack permission.');
        if($action==='update') {
            $status=(string)($_POST['status']??'new');$priority=(string)($_POST['priority']??'normal');$follow=trim((string)($_POST['follow_up_date']??''))?:null;
            if(!in_array($status,['new','contacted','in_progress','won','lost','closed'],true))throw new RuntimeException('Invalid status.');
            if(!in_array($priority,['low','normal','high','urgent'],true))$priority='normal';
            $assigned=$canAll?((int)($_POST['assigned_to']??0)?:null):(int)$me['id'];
            $pdo->prepare('UPDATE estimate_requests SET status=?,priority=?,follow_up_date=?,assigned_to=? WHERE id=?')->execute([$status,$priority,$follow,$assigned,$id]);
            log_activity('estimate_update','Updated estimate request workflow',['estimate_id'=>$id]);flash('success','Request updated.');
        } elseif($action==='note') {
            $note=trim((string)($_POST['note']??''));if($note==='')throw new RuntimeException('Write a note first.');
            $pdo->prepare('INSERT INTO estimate_notes(estimate_id,admin_id,note) VALUES(?,?,?)')->execute([$id,(int)$me['id'],$note]);
            log_activity('estimate_note','Added internal estimate note',['estimate_id'=>$id]);flash('success','Internal note added.');
        } elseif($action==='reply') {
            $recipient=trim((string)($_POST['recipient_email']??''));$subject=trim((string)($_POST['subject']??''));
            if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid recipient email address.');
            if($subject===''||strlen($subject)>255)throw new RuntimeException('Enter a subject of 255 characters or fewer.');
            $safeHtml=estimate_sanitize_html((string)($_POST['message_html']??''));
            if(!estimate_html_has_text($safeHtml))throw new RuntimeException('Write a response before sending.');
            $stored=estimate_store_reply_files(estimate_validate_reply_files(normalized_files('reply_attachments')),$id);
            $mailFiles=array_map(static fn(array $f):array=>['path'=>$f['path'],'name'=>$f['original_name'],'mime'=>$f['mime_type']],$stored);
            $result=(new EmailService())->sendWithFallback([4,3,1],$recipient,$subject,EmailTemplates::estimateResponse((string)($cur['full_name']??''),$safeHtml,settings()),'',[],['attachments'=>$mailFiles]);
            if(empty($result['success'])){estimate_remove_files(array_column($stored,'file_path'));$stored=[];throw new RuntimeException((string)($result['message']??'The email could not be sent.'));}
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO estimate_replies(estimate_id,admin_id,recipient_email,subject,message) VALUES(?,?,?,?,?)')->execute([$id,(int)$me['id'],$recipient,$subject,$safeHtml]);
            $replyId=(int)$pdo->lastInsertId();
            $insert=$pdo->prepare('INSERT INTO estimate_reply_attachments(reply_id,estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)');
            foreach($stored as $file)$insert->execute([$replyId,$id,$file['file_path'],$file['original_name'],$file['mime_type'],$file['file_size']]);
            $pdo->prepare("UPDATE estimate_requests SET status='contacted' WHERE id=? AND status='new'")->execute([$id]);$pdo->commit();
            log_activity('estimate_reply','Sent a response from Estimate Requests',['estimate_id'=>$id,'reply_id'=>$replyId]);flash('success','Response sent successfully and added to the request history.');
        } elseif(in_array($action,['mark_spam','not_spam','archive','restore'],true)) {
            $actions=[
                'mark_spam'=>['INSERT INTO estimate_request_flags(estimate_id,is_spam,updated_by) VALUES(?,1,?) ON DUPLICATE KEY UPDATE is_spam=1,updated_by=VALUES(updated_by)','Request marked as spam. Its data and history were preserved.'],
                'not_spam'=>['INSERT INTO estimate_request_flags(estimate_id,is_spam,updated_by) VALUES(?,0,?) ON DUPLICATE KEY UPDATE is_spam=0,updated_by=VALUES(updated_by)','Request restored from spam.'],
                'archive'=>['INSERT INTO estimate_request_flags(estimate_id,archived_at,updated_by) VALUES(?,NOW(),?) ON DUPLICATE KEY UPDATE archived_at=NOW(),updated_by=VALUES(updated_by)','Request archived.'],
                'restore'=>['INSERT INTO estimate_request_flags(estimate_id,archived_at,updated_by) VALUES(?,NULL,?) ON DUPLICATE KEY UPDATE archived_at=NULL,updated_by=VALUES(updated_by)','Request restored from archive.'],
            ];
            [$sql,$message]=$actions[$action];$pdo->prepare($sql)->execute([$id,(int)$me['id']]);
            log_activity('estimate_'.$action,$message,['estimate_id'=>$id]);flash('success',$message);
        } elseif($action==='delete_permanent') {
            $expectedConfirmation='DELETE REQUEST #'.$id;
            if(!hash_equals($expectedConfirmation,trim((string)($_POST['confirmation']??'')))) {
                throw new RuntimeException('Type “'.$expectedConfirmation.'” exactly to permanently delete this request.');
            }
            $paths=trim((string)($cur['photo_path']??''))!==''?[$cur['photo_path']]:[];
            foreach(['estimate_attachments','estimate_reply_attachments'] as $table){$q=$pdo->prepare('SELECT file_path FROM '.$table.' WHERE estimate_id=?');$q->execute([$id]);$paths=array_merge($paths,$q->fetchAll(PDO::FETCH_COLUMN));}
            $pdo->beginTransaction();
            foreach(['DELETE FROM estimate_reply_attachments WHERE estimate_id=?','DELETE FROM estimate_replies WHERE estimate_id=?','DELETE FROM estimate_request_flags WHERE estimate_id=?','DELETE FROM estimate_notes WHERE estimate_id=?','DELETE FROM estimate_attachments WHERE estimate_id=?','DELETE FROM estimate_requests WHERE id=?'] as $sql)$pdo->prepare($sql)->execute([$id]);
            $pdo->commit();$failed=estimate_remove_files($paths);$dir=UPLOAD_DIR.'/estimate-replies/'.$id;if(is_dir($dir))@rmdir($dir);
            log_activity('estimate_delete','Permanently deleted an estimate request',['estimate_id'=>$id,'file_cleanup_failures'=>$failed]);
            flash($failed?'warning':'success',$failed?'Request deleted, but one or more physical files could not be removed.':'Request and associated files permanently deleted.');header('Location: estimates.php');exit;
        } else throw new RuntimeException('Unsupported request action.');
        header('Location: estimates.php?view='.$id);exit;
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        if($stored)estimate_remove_files(array_column($stored,'file_path'));
        $error=$e->getMessage();
    }
}

$box=(string)($_GET['box']??'active');if(!in_array($box,['active','spam','archive','all'],true))$box='active';
$where=[];$args=[];
if(!$canAll){$where[]='e.assigned_to=?';$args[]=(int)$me['id'];}
if($box==='active')$where[]='COALESCE(f.is_spam,0)=0 AND f.archived_at IS NULL';
elseif($box==='spam')$where[]='COALESCE(f.is_spam,0)=1 AND f.archived_at IS NULL';
elseif($box==='archive')$where[]='f.archived_at IS NOT NULL';
if(!empty($_GET['status'])){$where[]='e.status=?';$args[]=$_GET['status'];}
if(($_GET['follow']??'')==='due')$where[]='e.follow_up_date IS NOT NULL AND e.follow_up_date<=CURDATE()';
$sql='SELECT e.*,f.is_spam,f.archived_at,u.full_name assigned_name,u.username assigned_username FROM estimate_requests e LEFT JOIN estimate_request_flags f ON f.estimate_id=e.id LEFT JOIN admin_users u ON u.id=e.assigned_to'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY CASE e.priority WHEN "urgent" THEN 0 WHEN "high" THEN 1 WHEN "normal" THEN 2 ELSE 3 END,e.id DESC';
$st=$pdo->prepare($sql);$st->execute($args);$rows=$st->fetchAll();
$view=null;$atts=[];$notes=[];$replies=[];
if(isset($_GET['view'])) {
    $st=$pdo->prepare('SELECT e.*,f.is_spam,f.archived_at,u.full_name assigned_name,u.username assigned_username FROM estimate_requests e LEFT JOIN estimate_request_flags f ON f.estimate_id=e.id LEFT JOIN admin_users u ON u.id=e.assigned_to WHERE e.id=?');$st->execute([(int)$_GET['view']]);$view=$st->fetch();
    if($view&&!$canAll&&(int)($view['assigned_to']??0)!==(int)$me['id'])$view=null;
    if($view) {
        $q=$pdo->prepare('SELECT * FROM estimate_attachments WHERE estimate_id=? ORDER BY id');$q->execute([$view['id']]);$atts=$q->fetchAll();
        $q=$pdo->prepare('SELECT n.*,u.full_name,u.username FROM estimate_notes n LEFT JOIN admin_users u ON u.id=n.admin_id WHERE n.estimate_id=? ORDER BY n.id DESC');$q->execute([$view['id']]);$notes=$q->fetchAll();
        $q=$pdo->prepare('SELECT r.*,u.full_name,u.username FROM estimate_replies r LEFT JOIN admin_users u ON u.id=r.admin_id WHERE r.estimate_id=? ORDER BY r.id DESC');$q->execute([$view['id']]);$replies=$q->fetchAll();
        if($replies){$q=$pdo->prepare('SELECT * FROM estimate_reply_attachments WHERE estimate_id=? ORDER BY id');$q->execute([$view['id']]);$group=[];foreach($q->fetchAll() as $a)$group[(int)$a['reply_id']][]=$a;foreach($replies as &$r)$r['attachments']=$group[(int)$r['id']]??[];unset($r);}
    }
}
$assignees=$canAll?$pdo->query('SELECT u.id,u.full_name,u.username,r.role_name FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.active=1 ORDER BY u.full_name,u.username')->fetchAll():[];
$dueSql="SELECT COUNT(*) FROM estimate_requests WHERE follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')".($canAll?'':' AND assigned_to='.(int)$me['id']);
$due=(int)$pdo->query($dueSql)->fetchColumn();$pageTitle='Estimate Requests';$active='estimates';require __DIR__.'/_header.php';
?>

<div class="page-heading estimate-action-center">
<div><p class="eyebrow">FREE ESTIMATES</p><h1><?=$view?'Request #'.(int)$view['id']:'Customer requests'?></h1><p class="muted"><?=$view?'Manage and answer this request without leaving its detail view.':'Assign leads, track follow-ups and keep private team notes in one place.'?></p></div>
<div class="heading-actions"><?php if($view): ?><a class="button secondary" href="estimates.php">← Back to requests</a><?php endif; ?><a class="button secondary" href="?box=active">Active</a><a class="button secondary" href="?box=spam">Spam</a><a class="button secondary" href="?box=archive">Archive</a><a class="button warning" href="?follow=due">Follow-ups due: <?=$due?></a></div>
</div>
<?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<?php if($view): ?>
<section class="panel animate-in estimate-detail">
<section class="estimate-request-actions" aria-labelledby="request-actions-heading">
<div><p class="eyebrow">ACTION CENTER</p><h2 id="request-actions-heading">Request actions</h2></div>
<div class="actions">
<a class="button secondary" href="estimates.php">← Back to requests</a>
<?php if($canAssigned): ?><a class="button" href="#reply-by-email"><?=icon('mail')?> Reply by email</a><?php endif; ?>
<?php $whatsappNumber=preg_replace('/\D+/','',(string)($view['phone']??'')); if($whatsappNumber!==''): ?><a class="button secondary" href="https://wa.me/<?=h($whatsappNumber)?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
<?php if($canAssigned): ?>
<?php if((int)($view['is_spam']??0)===1): ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="not_spam"><button class="button secondary">Not spam</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="mark_spam"><button class="button warning">Mark as spam</button></form><?php endif; ?>
<?php if($view['archived_at']): ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="restore"><button class="button secondary">Restore</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="archive"><button class="button secondary">Archive</button></form><?php endif; ?>
<?php endif; ?>
</div>
</section>
<div class="section-heading"><div><p class="eyebrow">REQUEST #<?=$view['id']?></p><h2><?=h($view['full_name']?:'Website visitor')?></h2></div><div class="request-badges"><?php if((int)($view['is_spam']??0)===1): ?><span class="badge urgent">Spam</span><?php endif; ?><?php if($view['archived_at']): ?><span class="badge normal">Archived</span><?php endif; ?><span class="badge <?=h($view['priority'])?>"><?=h($view['priority'])?></span><span class="badge <?=h($view['status'])?>"><?=h(str_replace('_',' ',$view['status']))?></span></div></div>
<div class="three-col estimate-contact-grid"><div class="list-card"><small>PHONE</small><strong><?=h($view['phone']?:'Not provided')?></strong></div><div class="list-card"><small>EMAIL</small><strong class="break-anywhere"><?=h($view['email']?:'Not provided')?></strong></div><div class="list-card"><small>FOLLOW-UP</small><strong><?=h($view['follow_up_date']?:'Not scheduled')?></strong></div></div>
<div class="estimate-summary-grid">
<div class="list-card estimate-service-card <?=trim((string)($view['address']??''))!==''?'has-address':''?>"><div><small>SERVICE</small><strong><?=h($view['service_needed']?:'General project')?></strong></div><?php if(trim((string)($view['address']??''))!==''): ?><div><small>ADDRESS</small><p><?=h($view['address'])?></p></div><?php endif; ?></div>
<div class="list-card estimate-project-details"><small>PROJECT DETAILS</small><div class="estimate-rich-message"><?=estimate_sanitize_html((string)($view['message']?:'<p>No additional details.</p>'))?></div></div>
</div>
<div class="list-card request-source-card"><small>HOW THEY FOUND US</small><strong><?=h(($view['lead_source']??'')?:'Not provided')?></strong><?php if(trim((string)($view['lead_source_detail']??''))!==''): ?><p><?=h($view['lead_source_detail'])?></p><?php endif; ?></div>
<?php if($atts||$view['photo_path']): ?><div class="section-heading estimate-subheading"><h2>Attached images</h2></div><div class="gallery-editor"><?php $all=$atts?:[['file_path'=>$view['photo_path'],'original_name'=>'Project photo']];foreach($all as $a): ?><article class="gallery-item"><div class="gallery-media"><img src="../<?=h($a['file_path'])?>" alt="<?=h($a['original_name']??'Request attachment')?>"><button type="button" class="zoom-btn" data-preview-src="../<?=h($a['file_path'])?>" data-preview-caption="<?=h($a['original_name']??'Request attachment')?>"><?=icon('eye')?></button></div></article><?php endforeach; ?></div><?php endif; ?>

<?php if($canAssigned): ?>
<form method="post" class="estimate-workflow"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="update"><div class="four-col"><label>Status<select name="status"><?php foreach(['new'=>'New','contacted'=>'Contacted','in_progress'=>'In progress','won'=>'Won','lost'=>'Lost','closed'=>'Closed'] as $k=>$v): ?><option value="<?=$k?>" <?=$view['status']===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label><label>Priority<select name="priority"><?php foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $k=>$v): ?><option value="<?=$k?>" <?=$view['priority']===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label><label>Follow-up date<input type="date" name="follow_up_date" value="<?=h($view['follow_up_date']??'')?>"></label><?php if($canAll): ?><label>Assigned to<select name="assigned_to"><option value="">Unassigned</option><?php foreach($assignees as $u): ?><option value="<?=$u['id']?>" <?=(int)($view['assigned_to']??0)===(int)$u['id']?'selected':''?>><?=h($u['full_name']?:$u['username'])?> · <?=h($u['role_name']?:'User')?></option><?php endforeach; ?></select></label><?php else: ?><label>Assigned to<input value="<?=h($view['assigned_name']?:$view['assigned_username']?:'You')?>" disabled></label><?php endif; ?></div><div class="form-actions"><button><?=icon('edit')?> Save workflow</button></div></form>

<section class="estimate-response-composer" id="reply-by-email" aria-labelledby="response-heading"><div class="section-heading"><div><p class="eyebrow">CUSTOMER RESPONSE</p><h2 id="response-heading">Reply by email</h2><p>Sent through the configured SMTP or Microsoft Graph connection.</p></div></div>
<form method="post" enctype="multipart/form-data" data-response-composer novalidate><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="reply"><div class="two-col response-address-fields"><label>Recipient email<input type="email" name="recipient_email" required autocomplete="email" value="<?=h($view['email']??'')?>"></label><label>Subject<input type="text" name="subject" required maxlength="255" value="<?=h('Re: Estimate request #'.$view['id'])?>"></label></div><label class="rich-editor-label">Response</label>
<div class="rich-editor" data-rich-editor><div class="rich-editor-toolbar" role="toolbar" aria-label="Response formatting"><button type="button" data-editor-command="bold" aria-label="Bold"><strong>B</strong></button><button type="button" data-editor-command="italic" aria-label="Italic"><em>I</em></button><button type="button" data-editor-command="underline" aria-label="Underline"><u>U</u></button><button type="button" data-editor-command="insertUnorderedList" aria-label="Bulleted list">• List</button><button type="button" data-editor-command="insertOrderedList" aria-label="Numbered list">1. List</button><button type="button" data-editor-command="formatBlock" data-editor-value="blockquote" aria-label="Block quote">❝ Quote</button><button type="button" data-editor-command="removeFormat" aria-label="Clear formatting">Clear</button></div><div class="rich-editor-content" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Response message" data-placeholder="Write a clear response to the customer…" data-rich-editor-content></div></div><textarea name="message_html" data-rich-editor-input hidden></textarea><p class="field-error" data-rich-editor-error hidden>Write a response before sending.</p>
<div
    class="upload-zone estimate-reply-upload"
    data-upload-zone
    role="button"
    tabindex="0"
    aria-label="Attach files by dropping, pasting or choosing from this device"
    aria-describedby="estimate-attachment-help"
>
    <div class="upload-icon" aria-hidden="true">📎</div>
    <strong>Attach files</strong>
    <p id="estimate-attachment-help">
        Drag &amp; drop files here, paste from clipboard, or choose them from your device.
    </p>
    <button class="upload-zone-action" type="button" data-upload-choose>Choose files</button>
    <small>
        PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, JPEG, PNG or WEBP · Maximum 5 files · 2.5 MB combined
    </small>
    <input
        type="file"
        name="reply_attachments[]"
        multiple
        data-max-files="5"
        data-max-total-bytes="2621440"
        data-allowed-extensions="pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp"
        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,image/jpeg,image/png,image/webp"
    >
    <div class="upload-preview" data-upload-preview></div>
    <span class="upload-selection-summary" data-upload-name aria-live="polite"></span>
    <div class="reply-file-list" data-upload-file-list aria-live="polite"></div>
</div>
<div class="form-actions"><button type="submit"><?=icon('mail')?> Send response</button></div>
</form>
</section>

<section class="estimate-response-history">
    <div class="section-heading">
        <div><h2>Response history</h2><p>Only transport-confirmed messages appear here.</p></div>
        <span class="badge normal"><?=count($replies)?> sent</span>
    </div>
    <div class="reply-timeline">
        <?php foreach($replies as $reply): ?>
        <article class="reply-card">
            <header>
                <div>
                    <strong><?=h($reply['subject'])?></strong>
                    <small>To <?=h($reply['recipient_email'])?> · <?=h($reply['full_name']?:$reply['username']?:'Administrator')?></small>
                </div>
                <time><?=h($reply['created_at'])?></time>
            </header>
            <div class="reply-message"><?=estimate_sanitize_html((string)$reply['message'])?></div>
            <?php if($reply['attachments']): ?>
            <div class="reply-attachments">
                <strong>Attachments</strong>
                <div>
                    <?php foreach($reply['attachments'] as $a): ?>
                    <a class="button secondary small" href="?download_reply_attachment=<?=$a['id']?>">
                        <span aria-hidden="true">📎</span>
                        <span><?=h($a['original_name'])?></span>
                        <small><?=number_format((int)$a['file_size']/1024,1)?> KB</small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php if(!$replies): ?>
        <div class="empty-state"><strong>No responses sent yet</strong><p>Successful responses will appear here with their attachments.</p></div>
        <?php endif; ?>
    </div>
</section>

<section class="estimate-notes"><div class="section-heading"><div><h2>Internal notes</h2><p>Only administrator users can see these notes.</p></div></div><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="note"><label>Add note<textarea name="note" required placeholder="Call outcome, next step, customer preference…"></textarea></label><div class="form-actions"><button>Add internal note</button></div></form><div class="note-timeline"><?php foreach($notes as $n): ?><article><span></span><div><strong><?=h($n['full_name']?:$n['username']?:'Administrator')?></strong><p><?=nl2br(h($n['note']))?></p><small><?=h($n['created_at'])?></small></div></article><?php endforeach; ?></div></section>

<?php if($canAll): ?>
<section class="estimate-danger-zone" aria-labelledby="danger-zone-heading">
    <div>
        <p class="eyebrow">DANGER ZONE</p>
        <h2 id="danger-zone-heading">Permanent delete</h2>
        <p>This permanently removes the request, notes, replies and every associated file. Use Spam or Archive when the record must be preserved.</p>
    </div>
    <form
        method="post"
        data-confirm-text="DELETE REQUEST #<?=$view['id']?>"
        data-swal-confirm="Permanently delete this request?"
        data-swal-text="This action is irreversible and removes every associated record and file."
        data-swal-confirm-text="Yes, delete permanently"
    >
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="id" value="<?=$view['id']?>">
        <input type="hidden" name="action" value="delete_permanent">
        <label>
            Type <span class="confirmation-required-phrase">DELETE REQUEST #<?=$view['id']?></span> to confirm
            <input type="text" name="confirmation" autocomplete="off" required>
        </label>
        <button class="button danger">Delete permanently</button>
    </form>
</section>
<?php endif; ?>
<?php endif; ?>
<div class="estimate-back-footer"><a class="button secondary" href="estimates.php">← Back to requests</a></div></section>
<?php endif; ?>

<div class="request-grid"><?php foreach($rows as $r): ?><article class="request-card animate-in priority-<?=h($r['priority'])?>"><div class="request-top"><div><strong><?=h($r['full_name']?:'Website visitor')?></strong><small><?=h($r['created_at'])?></small></div><span class="badge <?=h($r['status'])?>"><?=h(str_replace('_',' ',$r['status']))?></span></div><p><strong><?=h($r['service_needed']?:'General project')?></strong><br><?=h($r['phone']?:$r['email']?:'No contact provided')?></p><div class="request-assignment"><span class="badge <?=h($r['priority'])?>"><?=h($r['priority'])?></span><small>Assigned: <?=h($r['assigned_name']?:$r['assigned_username']?:'Unassigned')?><?php if($r['follow_up_date']): ?> · Follow-up <?=h($r['follow_up_date'])?><?php endif; ?></small></div><div class="actions"><a class="button secondary small" href="?view=<?=$r['id']?>">Open request</a></div></article><?php endforeach; ?></div>
<?php if(!$rows): ?><div class="empty-state"><strong>No requests match this view</strong><p>Assignments, flags and permissions determine which requests are visible.</p></div><?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
