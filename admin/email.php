<?php
require __DIR__.'/bootstrap.php';
require_permission('email.manage');
require_once __DIR__.'/../core/EmailService.php';

function email_delivery_columns_ready(PDO $pdo): bool
{
    try {
        $destination=$pdo->query("SHOW COLUMNS FROM correo LIKE 'destinatario'")->fetch();
        $copy=$pdo->query("SHOW COLUMNS FROM correo LIKE 'copia'")->fetch();
        return (bool)$destination&&(bool)$copy;
    } catch(Throwable $e) {
        return false;
    }
}

function email_database_error_message(bool $schemaReady): string
{
    return $schemaReady
        ?'The email configuration could not be saved because the database operation failed. Review the server log for details.'
        :'Email Configuration requires the latest database update. Run database-update.sql once on the existing database and reload this page.';
}

function email_encrypted_secret(?string $value): string
{
    $value=(string)$value;
    if($value===''||str_starts_with($value,'v1:'))return $value;
    return secret_encrypt($value);
}

$pdo=db();
$emailService=new EmailService();
$emailSchemaReady=email_delivery_columns_ready($pdo);
$error='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try {
        if($action==='save') {
            if(!$emailSchemaReady)throw new RuntimeException(email_database_error_message(false));

            $type=(int)($_POST['correo_tipo_id']??0);
            $typeExists=$pdo->prepare('SELECT COUNT(*) FROM correo_tipo WHERE correo_tipo_id=?');
            $typeExists->execute([$type]);
            if((int)$typeExists->fetchColumn()!==1)throw new RuntimeException('Select a valid email purpose.');

            $method=in_array($_POST['metodo_envio']??'SMTP',['SMTP','GRAPH'],true)?$_POST['metodo_envio']:'SMTP';
            $estado=isset($_POST['estado'])?1:2;
            $destination=trim((string)($_POST['destinatario']??''));
            if($destination!==''&&!filter_var($destination,FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Internal destination must be a valid email address or remain empty.');
            }
            $copies=EmailService::normalizeAddressList($_POST['copia']??'');
            $copyValue=$copies?implode(', ',$copies):'';
            $old=$id?$emailService->configById($id):null;
            if($id&&!$old)throw new RuntimeException('The email configuration was not found.');
            $oldMethod=strtoupper((string)($old['metodo_envio']??''));

            $server='';
            $sender='';
            $passwordEnc='';
            $port=0;
            $secure='';
            $tenant=null;
            $client=null;
            $clientSecretEnc=null;
            $graphUser=null;
            $saveToSentItems=0;

            if($method==='SMTP') {
                $server=trim((string)($_POST['server']??''));
                $sender=trim((string)($_POST['correo']??''));
                $port=(int)($_POST['port']??587);
                $secure=in_array(strtolower((string)($_POST['smtp_secure']??'tls')),['tls','ssl'],true)
                    ?strtolower((string)$_POST['smtp_secure'])
                    :'tls';
                $password=trim((string)($_POST['password']??''));
                $passwordEnc=$password!==''
                    ?secret_encrypt($password)
                    :($oldMethod==='SMTP'?email_encrypted_secret($old['password']??''):'');
                if($sender!==''&&!filter_var($sender,FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid SMTP user / sender email.');
                }
            } else {
                $server='graph.microsoft.com';
                $tenant=trim((string)($_POST['tenant_id']??''))?:null;
                $client=trim((string)($_POST['client_id']??''))?:null;
                $graphUser=trim((string)($_POST['graph_user']??''))?:null;
                $sender=(string)($graphUser??'');
                $clientSecret=trim((string)($_POST['client_secret']??''));
                $clientSecretEnc=$clientSecret!==''
                    ?secret_encrypt($clientSecret)
                    :($oldMethod==='GRAPH'?email_encrypted_secret($old['client_secret']??''):null);
                $saveToSentItems=isset($_POST['save_to_sent_items'])?1:0;
                if($graphUser!==null&&!filter_var($graphUser,FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid Graph User / mailbox.');
                }
            }

            $configuration=[
                'metodo_envio'=>$method,
                'server'=>$server,
                'correo'=>$sender,
                'password'=>$passwordEnc,
                'port'=>$port,
                'smtp_secure'=>$secure,
                'tenant_id'=>$tenant,
                'client_id'=>$client,
                'client_secret'=>$clientSecretEnc,
                'graph_user'=>$graphUser,
                'destinatario'=>$destination,
                'copia'=>$copyValue,
            ];
            if($estado===1) {
                $problems=$emailService->configurationProblems($configuration);
                if($problems)throw new RuntimeException('This configuration cannot be activated. '.implode(' ',$problems));
            }

            $values=[
                $type,$method,$server,$sender,$passwordEnc,$port,$secure,$tenant,$client,$clientSecretEnc,
                $graphUser,$destination?:null,$copyValue?:null,$saveToSentItems,$estado,
            ];
            $pdo->beginTransaction();
            if($id) {
                $values[]=$id;
                $pdo->prepare(
                    'UPDATE correo SET correo_tipo_id=?,metodo_envio=?,server=?,correo=?,password=?,port=?,smtp_secure=?,tenant_id=?,client_id=?,client_secret=?,graph_user=?,destinatario=?,copia=?,save_to_sent_items=?,estado=? WHERE correo_id=?'
                )->execute($values);
                $savedId=$id;
            } else {
                $pdo->prepare(
                    'INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,destinatario,copia,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute($values);
                $savedId=(int)$pdo->lastInsertId();
            }
            if($estado===1) {
                $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=? AND correo_id<>?')->execute([$type,$savedId]);
            }
            $pdo->commit();

            log_activity($id?'email_configuration_update':'email_configuration_create',$id?'Updated an email connection.':'Created an email connection.',[
                'configuration_id'=>$savedId,
                'email_type_id'=>$type,
                'method'=>$method,
                'has_internal_destination'=>$destination!=='',
                'cc_count'=>count($copies),
            ]);
            flash('success','Email configuration saved.');
        } elseif($action==='copy'&&$id) {
            if(!$emailSchemaReady)throw new RuntimeException(email_database_error_message(false));
            $source=$emailService->configById($id);
            if(!$source)throw new RuntimeException('The source email configuration was not found.');

            $availableTypeIds=array_map('intval',$pdo->query('SELECT correo_tipo_id FROM correo_tipo')->fetchAll(PDO::FETCH_COLUMN));
            $targetTypeIds=array_values(array_unique(array_filter(
                array_map('intval',(array)($_POST['target_types']??[])),
                static fn(int $targetTypeId):bool=>$targetTypeId!==(int)$source['correo_tipo_id']&&in_array($targetTypeId,$availableTypeIds,true)
            )));
            if(!$targetTypeIds)throw new RuntimeException('Select at least one different email purpose.');

            $activateCopies=isset($_POST['activate_copies']);
            if($activateCopies) {
                $problems=$emailService->configurationProblems($source);
                if($problems)throw new RuntimeException('The source connection cannot be copied as active. '.implode(' ',$problems));
            }

            $copiedIds=[];
            $sourcePassword=email_encrypted_secret($source['password']??'');
            $sourceClientSecret=email_encrypted_secret($source['client_secret']??'');
            $pdo->beginTransaction();
            foreach($targetTypeIds as $targetTypeId) {
                if($activateCopies) {
                    $find=$pdo->prepare('SELECT correo_id FROM correo WHERE correo_tipo_id=? AND estado=1 ORDER BY correo_id DESC LIMIT 1');
                    $find->execute([$targetTypeId]);
                    $targetId=(int)($find->fetchColumn()?:0);
                    if(!$targetId) {
                        $find=$pdo->prepare('SELECT correo_id FROM correo WHERE correo_tipo_id=? ORDER BY correo_id DESC LIMIT 1');
                        $find->execute([$targetTypeId]);
                        $targetId=(int)($find->fetchColumn()?:0);
                    }
                } else {
                    $find=$pdo->prepare('SELECT correo_id FROM correo WHERE correo_tipo_id=? AND estado=2 ORDER BY correo_id DESC LIMIT 1');
                    $find->execute([$targetTypeId]);
                    $targetId=(int)($find->fetchColumn()?:0);
                }

                $copyValues=[
                    $targetTypeId,$source['metodo_envio'],$source['server'],$source['correo'],$sourcePassword,
                    (int)$source['port'],$source['smtp_secure'],$source['tenant_id'],$source['client_id'],$sourceClientSecret,
                    $source['graph_user'],$source['destinatario']??null,$source['copia']??null,(int)$source['save_to_sent_items'],$activateCopies?1:2,
                ];
                if($targetId) {
                    $copyValues[]=$targetId;
                    $pdo->prepare(
                        'UPDATE correo SET correo_tipo_id=?,metodo_envio=?,server=?,correo=?,password=?,port=?,smtp_secure=?,tenant_id=?,client_id=?,client_secret=?,graph_user=?,destinatario=?,copia=?,save_to_sent_items=?,estado=? WHERE correo_id=?'
                    )->execute($copyValues);
                    $copiedId=$targetId;
                } else {
                    $pdo->prepare(
                        'INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,destinatario,copia,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                    )->execute($copyValues);
                    $copiedId=(int)$pdo->lastInsertId();
                }
                if($activateCopies) {
                    $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=? AND correo_id<>?')->execute([$targetTypeId,$copiedId]);
                }
                $copiedIds[]=$copiedId;
            }
            $pdo->commit();
            log_activity('email_configuration_copy','Copied one email connection to multiple purposes.',[
                'source_id'=>$id,
                'target_type_ids'=>$targetTypeIds,
                'copied_configuration_ids'=>$copiedIds,
                'activated'=>$activateCopies,
            ]);
            flash('success',count($targetTypeIds).' email purpose'.(count($targetTypeIds)===1?' was':'s were').' configured from the selected connection.');
            header('Location: email.php');
            exit;
        } elseif($action==='delete'&&$id) {
            $pdo->prepare('DELETE FROM correo WHERE correo_id=?')->execute([$id]);
            log_activity('email_configuration_delete','Deleted an email connection.',['configuration_id'=>$id]);
            flash('success','Email configuration deleted.');
        } elseif($action==='toggle'&&$id) {
            $toggleConfig=$emailService->configById($id);
            if(!$toggleConfig)throw new RuntimeException('The email configuration was not found.');
            $newState=(int)$toggleConfig['estado']===1?2:1;
            if($newState===1) {
                $problems=$emailService->configurationProblems($toggleConfig);
                if($problems)throw new RuntimeException('This configuration cannot be activated. '.implode(' ',$problems));
            }
            $pdo->beginTransaction();
            if($newState===1)$pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=?')->execute([(int)$toggleConfig['correo_tipo_id']]);
            $pdo->prepare('UPDATE correo SET estado=? WHERE correo_id=?')->execute([$newState,$id]);
            $pdo->commit();
            log_activity('email_configuration_status','Changed an email connection status.',['configuration_id'=>$id,'active'=>$newState===1]);
            flash('success','Email status updated.');
        } elseif($action==='test'&&$id) {
            $res=$emailService->test($id,trim((string)($_POST['test_to']??'')));
            log_activity('email_configuration_test','Tested an email connection.',['configuration_id'=>$id,'success'=>(bool)$res['success']]);
            flash($res['success']?'success':'error',$res['message']);
        }
        header('Location: email.php'.($id?'?edit='.$id:''));
        exit;
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        $error=$e instanceof PDOException
            ?email_database_error_message($emailSchemaReady)
            :($e instanceof RuntimeException
                ?$e->getMessage()
                :'The email operation could not be completed safely. Review Website Health and try again.');
    }
}

$types=$pdo->query('SELECT * FROM correo_tipo ORDER BY correo_tipo_id')->fetchAll();
$rows=$pdo->query('SELECT c.*,t.nombre tipo_nombre FROM correo c JOIN correo_tipo t ON t.correo_tipo_id=c.correo_tipo_id ORDER BY c.correo_id DESC')->fetchAll();
$configuredTypes=[];
foreach($rows as $row) {
    $typeId=(int)$row['correo_tipo_id'];
    if(!isset($configuredTypes[$typeId]))$configuredTypes[$typeId]=['total'=>0,'active'=>0];
    $configuredTypes[$typeId]['total']++;
    if((int)$row['estado']===1)$configuredTypes[$typeId]['active']++;
}
$edit=null;
if(isset($_GET['edit']))$edit=$emailService->configById((int)$_GET['edit']);
$editMethod=strtoupper((string)($edit['metodo_envio']??'SMTP'));
$pageTitle='Email Configuration';
$active='email';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">EMAIL</p>
<h1>SMTP & Microsoft Graph</h1>
<p class="muted">Configure secure, purpose-specific email delivery without duplicating sender credentials.</p>
</div>
<a class="button secondary" href="email.php"><?=icon('plus')?> New configuration</a>
</div>

<?php if(!$emailSchemaReady): ?>
<div class="alert error" role="alert">
<strong>Email database update required.</strong>
Run <code>database-update.sql</code> once on the existing database, then reload this page. Existing data will be preserved.
</div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert error" role="alert"><?=h($error)?></div>
<?php endif; ?>

<section class="panel animate-in email-config-panel">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?></div>
<div>
<h2><?=$edit?'Edit configuration':'Add email configuration'?></h2>
<p>Sender passwords and client secrets are encrypted before being stored.</p>
</div>
</div>

<form method="post" novalidate class="email-config-form" data-email-config-form>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($edit['correo_id']??0))?>">

<div class="email-config-head-grid">
<div class="email-field">
<label for="email-purpose">Email Purpose</label>
<select id="email-purpose" name="correo_tipo_id">
<?php foreach($types as $type): ?>
<option value="<?=$type['correo_tipo_id']?>" <?=($edit['correo_tipo_id']??1)==$type['correo_tipo_id']?'selected':''?>><?=h($type['nombre'])?></option>
<?php endforeach; ?>
</select>
<small>Choose which CMS messages use this connection.</small>
</div>

<div class="email-field">
<label for="email-method">Method</label>
<select id="email-method" name="metodo_envio" data-method-select>
<option value="SMTP" <?=$editMethod==='SMTP'?'selected':''?>>SMTP</option>
<option value="GRAPH" <?=$editMethod==='GRAPH'?'selected':''?>>Microsoft Graph</option>
</select>
<small data-method-summary><?=$editMethod==='GRAPH'?'Use a Microsoft 365 mailbox through Microsoft Graph.':'Use an authenticated SMTP server.'?></small>
</div>

<label class="premium-switch email-active-switch">
<input type="checkbox" name="estado" <?=!$edit||($edit['estado']??1)==1?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span>
<b>Active configuration</b>
<small>Use this connection for the selected Email Purpose.</small>
</span>
</label>
</div>

<div class="email-routing-grid">
<div class="email-field">
<label for="internal-destination">Internal destination <span>Optional</span></label>
<input id="internal-destination" type="email" name="destinatario" value="<?=h($edit['destinatario']??'')?>" placeholder="Leave empty to use the method sender">
<small>If empty, SMTP uses its login address and Graph uses its mailbox.</small>
</div>
<div class="email-field">
<label for="optional-copy">Optional copy / CC <span>Optional</span></label>
<input id="optional-copy" name="copia" value="<?=h($edit['copia']??'')?>" placeholder="one@example.com; two@example.com">
<small>Separate multiple addresses with commas or semicolons. Empty means no CC.</small>
</div>
</div>

<section class="email-method-panel" data-method="SMTP"<?php if($editMethod!=='SMTP'): ?> hidden<?php endif; ?>>
<div class="email-method-heading">
<span class="email-method">SMTP</span>
<div>
<strong>SMTP connection</strong>
<p>This address is used as the sender and as the SMTP login username.</p>
</div>
</div>
<div class="email-smtp-grid">
<div class="email-field email-server-field">
<label for="smtp-server">SMTP server</label>
<input id="smtp-server" name="server" value="<?=h($editMethod==='SMTP'?($edit['server']??''):'')?>" placeholder="smtp.example.com">
<small>Gmail reference: smtp.gmail.com.</small>
</div>
<div class="email-field email-sender-field">
<label for="smtp-user">SMTP user / sender email</label>
<input id="smtp-user" type="email" name="correo" value="<?=h($editMethod==='SMTP'?($edit['correo']??''):'')?>" placeholder="sender@example.com">
<small>This address authenticates and sends the message.</small>
</div>
<div class="email-field email-port-field">
<label for="smtp-port">Port</label>
<input id="smtp-port" type="number" min="1" max="65535" name="port" value="<?=h((string)($editMethod==='SMTP'?($edit['port']??587):587))?>">
<small>Gmail TLS reference: 587.</small>
</div>
<div class="email-field email-security-field">
<label for="smtp-security">Security</label>
<select id="smtp-security" name="smtp_secure">
<option value="tls" <?=($edit['smtp_secure']??'tls')==='tls'?'selected':''?>>TLS</option>
<option value="ssl" <?=($edit['smtp_secure']??'')==='ssl'?'selected':''?>>SSL</option>
</select>
<small>Match the encryption required by the provider.</small>
</div>
</div>
<div class="email-field email-secret-field">
<label for="smtp-password">SMTP password / app password</label>
<input id="smtp-password" type="password" name="password" autocomplete="new-password" placeholder="<?=$edit&&$editMethod==='SMTP'?'Leave blank to keep saved password':'Password or app password'?>">
<small><?=$edit&&$editMethod==='SMTP'?'Leave blank to keep the saved encrypted password.':'Stored encrypted; never displayed after saving.'?></small>
</div>
</section>

<section class="email-method-panel" data-method="GRAPH"<?php if($editMethod!=='GRAPH'): ?> hidden<?php endif; ?>>
<div class="email-method-heading">
<span class="email-method">GRAPH</span>
<div>
<strong>Microsoft Graph connection</strong>
<p>This mailbox is used as the sender by Microsoft Graph. The mailbox password is not required.</p>
</div>
</div>
<div class="email-graph-grid">
<div class="email-field">
<label for="tenant-id">Tenant ID</label>
<input id="tenant-id" name="tenant_id" value="<?=h($editMethod==='GRAPH'?($edit['tenant_id']??''):'')?>">
<small>Microsoft Entra tenant identifier.</small>
</div>
<div class="email-field">
<label for="client-id">Client ID</label>
<input id="client-id" name="client_id" value="<?=h($editMethod==='GRAPH'?($edit['client_id']??''):'')?>">
<small>Application identifier registered in Microsoft Entra.</small>
</div>
<div class="email-field">
<label for="client-secret">Client Secret VALUE</label>
<input id="client-secret" type="password" name="client_secret" autocomplete="new-password" placeholder="<?=$edit&&$editMethod==='GRAPH'?'Leave blank to keep saved secret':'Microsoft Entra client secret VALUE'?>">
<small><?=$edit&&$editMethod==='GRAPH'?'Leave blank to keep the saved encrypted secret.':'Use the secret VALUE, not its Secret ID.'?></small>
</div>
<div class="email-field">
<label for="graph-user">Graph User / mailbox</label>
<input id="graph-user" type="email" name="graph_user" value="<?=h($editMethod==='GRAPH'?($edit['graph_user']??''):'')?>" placeholder="mailbox@example.com">
<small>This mailbox is the Microsoft Graph sender.</small>
</div>
</div>
<label class="premium-switch email-sent-items-switch">
<input type="checkbox" name="save_to_sent_items" <?=!$edit||!empty($edit['save_to_sent_items'])?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span>
<b>Save a copy in Sent Items</b>
<small>Microsoft Graph will keep the sent message in the sender mailbox.</small>
</span>
</label>
</section>

<div class="form-actions">
<button type="submit"<?php if(!$emailSchemaReady): ?> disabled<?php endif; ?>><?=icon($edit?'edit':'plus')?> <?=$edit?'Update email configuration':'Save email configuration'?></button>
<?php if($edit): ?><a class="button secondary" href="email.php">Cancel edit</a><?php endif; ?>
</div>
</form>
</section>

<?php if($edit): ?>
<section class="panel email-test-panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?></div>
<div>
<h2>Test this configuration</h2>
<p>Send a real message through the selected connection. Secrets are never displayed.</p>
</div>
</div>
<form method="post" class="test-email-form" novalidate>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="test">
<input type="hidden" name="id" value="<?=$edit['correo_id']?>">
<div class="email-field">
<label for="test-destination">Test destination <span>Optional</span></label>
<input id="test-destination" type="email" name="test_to" placeholder="Leave empty to test the effective destination">
<small>Fallback: Internal destination, then the SMTP user or Graph mailbox.</small>
</div>
<div class="form-actions">
<button class="button"><?=icon('mail')?> Send test email</button>
</div>
</form>
</section>
<?php else: ?>
<div class="info-strip">
<strong>Want to test it?</strong>
<span>Save the configuration first. The real test tool will appear here.</span>
</div>
<?php endif; ?>

<div class="section-heading">
<div>
<p class="eyebrow">CONFIGURED SENDERS</p>
<h2>Email connections</h2>
</div>
</div>

<?php if(!$rows): ?>
<div class="empty-state">
<strong>No email configured</strong>
<p>Add SMTP or Microsoft Graph above.</p>
</div>
<?php else: ?>
<div class="email-grid">
<?php foreach($rows as $row):
$sender=EmailService::senderAddress($row);
$internal=trim((string)($row['destinatario']??''));
$copy=trim((string)($row['copia']??''));
?>
<article class="email-card animate-in">
<div class="list-head">
<div>
<span class="email-method"><?=h($row['metodo_envio'])?></span>
<h3><?=h($row['tipo_nombre'])?></h3>
<small><?=h($sender)?></small>
</div>
<span class="badge <?=$row['estado']==1?'contacted':'closed'?>"><?=$row['estado']==1?'Active':'Inactive'?></span>
</div>
<div class="email-card-details">
<p><strong>Connection</strong><span><?=$row['metodo_envio']==='GRAPH'?'Microsoft Graph mailbox':'SMTP '.h($row['server']).':'.(int)$row['port']?></span></p>
<p><strong>Internal destination</strong><span><?=h($internal!==''?$internal:'Fallback to sender')?></span></p>
<p><strong>Optional CC</strong><span><?=h($copy!==''?$copy:'No CC')?></span></p>
</div>
<div class="actions">
<form method="post" class="actions email-card-test-form" novalidate>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="test">
<input type="hidden" name="id" value="<?=$row['correo_id']?>">
<input type="email" name="test_to" placeholder="Optional test destination" aria-label="Optional test destination">
<button class="button small"><?=icon('mail')?> Send test</button>
</form>
<button type="button" class="button secondary small" data-email-copy-open data-source-id="<?=$row['correo_id']?>" data-source-type="<?=$row['correo_tipo_id']?>" data-source-purpose="<?=h($row['tipo_nombre'])?>" data-source-method="<?=h($row['metodo_envio'])?>" data-source-email="<?=h($sender)?>"><?=icon('copy')?> Copy to purposes</button>
<details class="action-menu">
<summary>Actions ▾</summary>
<nav class="action-menu-list">
<a class="action-menu-item action-menu-item--edit" href="?edit=<?=$row['correo_id']?>"><?=icon('edit')?> Edit</a>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="id" value="<?=$row['correo_id']?>">
<button><?=icon($row['estado']==1?'eye':'approval')?> <?=$row['estado']==1?'Deactivate':'Activate'?></button>
</form>
<form method="post" data-swal-confirm="Delete this email configuration?" data-swal-text="Email delivery using this configuration will stop.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$row['correo_id']?>">
<button class="action-menu-item action-menu-item--danger" style="background:transparent!important;background-image:none!important;border-color:transparent!important;box-shadow:none!important"><?=icon('trash')?> Delete</button>
</form>
</nav>
</details>
</div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="email-copy-modal" data-email-copy-modal hidden aria-hidden="true">
<div class="email-copy-backdrop" aria-hidden="true"></div>
<section class="email-copy-dialog" role="dialog" aria-modal="true" aria-labelledby="email-copy-title">
<header class="email-copy-head">
<div>
<p class="eyebrow">REUSE CONNECTION</p>
<h2 id="email-copy-title">Copy email configuration</h2>
<p>Copy the method, encrypted credentials, routing destination and optional CC to other purposes.</p>
</div>
<button type="button" class="icon-btn" data-email-copy-close aria-label="Close copy dialog">×</button>
</header>
<form method="post" novalidate data-email-copy-form data-swal-confirm="Copy this email configuration?" data-swal-text="Selected active purposes will use this same sender connection. Existing active connections for those purposes will become inactive." data-swal-confirm-text="Yes, copy configuration">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="copy">
<input type="hidden" name="id" value="" data-email-copy-source-id>
<div class="email-copy-source">
<span class="email-method" data-email-copy-method>SMTP</span>
<div><strong data-email-copy-purpose>Source purpose</strong><small data-email-copy-email></small></div>
</div>
<div class="email-copy-toolbar">
<div><strong>Choose destination purposes</strong><small>The source purpose is excluded automatically.</small></div>
<div class="email-copy-selection-actions">
<button type="button" class="button secondary small" data-email-copy-select-all><?=icon('approval')?> Select all</button>
<button type="button" class="button secondary small" data-email-copy-clear><?=icon('trash')?> Clear</button>
</div>
</div>
<div class="email-copy-targets">
<?php foreach($types as $type):
$typeId=(int)$type['correo_tipo_id'];
$typeSummary=$configuredTypes[$typeId]??['total'=>0,'active'=>0];
?>
<label class="email-copy-target" data-email-copy-target data-type-id="<?=$typeId?>">
<input type="checkbox" name="target_types[]" value="<?=$typeId?>">
<span class="email-copy-check" aria-hidden="true">✓</span>
<span><strong><?=h($type['nombre'])?></strong><small><?=$typeSummary['active']?'Active connection exists':($typeSummary['total']?'Inactive connection exists':'Not configured')?></small></span>
</label>
<?php endforeach; ?>
</div>
<label class="premium-switch email-copy-activate">
<input type="checkbox" name="activate_copies" value="1" checked>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Activate copied configurations</b><small>Each copy becomes active and replaces the previous active sender for that purpose.</small></span>
</label>
<div class="email-copy-note">
<?=icon('shield')?>
<span>Encrypted secrets, Internal destination and Optional CC are copied securely without being displayed.</span>
</div>
<div class="form-actions email-copy-actions">
<button type="submit"<?php if(!$emailSchemaReady): ?> disabled<?php endif; ?>><?=icon('copy')?> Copy configuration</button>
<button type="button" class="button secondary" data-email-copy-close>Cancel</button>
</div>
</form>
</section>
</div>
<?php require __DIR__.'/_footer.php'; ?>
