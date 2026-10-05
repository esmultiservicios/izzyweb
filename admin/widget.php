<?php
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');
$set=settings();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $enabled=isset($_POST['chat_widget_enabled'])?'1':'0';
        $provider=trim((string)($_POST['chat_widget_provider']??'NIVO Web Chat'));
        $title=trim((string)($_POST['chat_widget_title']??'¿Necesitas ayuda?'));
        $subtitle=trim((string)($_POST['chat_widget_subtitle']??'Chatea con nosotros'));
        $requestedMode=in_array($_POST['chat_widget_mode']??'embed',['url','embed'],true)?(string)$_POST['chat_widget_mode']:'embed';
        $url=trim((string)($_POST['chat_widget_url']??''));
        $embed=trim((string)($_POST['chat_widget_embed_code']??''));
        $requestedPosition=in_array($_POST['chat_widget_position']??'auto',['auto','left','right'],true)?(string)$_POST['chat_widget_position']:'auto';

        if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || !preg_match('~^https://~i',$url))){
            throw new RuntimeException('La URL del chat debe ser una URL HTTPS completa.');
        }

        // La URL siempre es opcional. Si existe código de instalación y no hay URL,
        // se utiliza automáticamente el modo embed aunque una configuración anterior
        // hubiera quedado guardada como URL / iframe.
        $mode=$requestedMode;
        if($embed!=='' && $url===''){
            $mode='embed';
        }elseif($url!=='' && $embed===''){
            $mode='url';
        }

        if($enabled==='1' && $url==='' && $embed===''){
            throw new RuntimeException('Agrega una URL embebible o pega el código de instalación antes de activar el widget.');
        }
        if($enabled==='1' && $mode==='embed' && $embed===''){
            throw new RuntimeException('Pega el código de instalación que entrega tu proveedor o selecciona URL embebible.');
        }
        if($enabled==='1' && $mode==='url' && $url===''){
            throw new RuntimeException('Agrega la URL embebible o selecciona Código de instalación.');
        }

        $whatsappPosition=($set['whatsapp_position']??'left')==='right'?'right':'left';
        if($requestedPosition==='auto'){
            $resolvedPosition=$whatsappPosition==='right'?'left':'right';
        }else{
            $resolvedPosition=$requestedPosition===$whatsappPosition ? ($whatsappPosition==='right'?'left':'right') : $requestedPosition;
        }

        save_setting('chat_widget_enabled',$enabled);
        save_setting('chat_widget_provider',$provider!==''?$provider:'NIVO Web Chat');
        save_setting('chat_widget_title',$title!==''?$title:'¿Necesitas ayuda?');
        save_setting('chat_widget_subtitle',$subtitle!==''?$subtitle:'Chatea con nosotros');
        save_setting('chat_widget_mode',$mode);
        save_setting('chat_widget_url',$url);
        save_setting('chat_widget_embed_code',$embed);
        save_setting('chat_widget_position',$requestedPosition);
        save_setting('chat_widget_resolved_position',$resolvedPosition);

        save_setting('nivo_widget_enabled',$enabled);
        save_setting('nivo_widget_url',$url);
        save_setting('nivo_widget_title',$provider!==''?$provider:'NIVO Web Chat');
        save_setting('nivo_widget_greeting',$title!==''?$title:'¿Necesitas ayuda?');

        log_activity('floating_chat_widget_update','Updated floating chat widget',[
            'provider'=>$provider,'mode'=>$mode,'requested_position'=>$requestedPosition,
            'resolved_position'=>$resolvedPosition,'enabled'=>$enabled==='1'
        ]);
        flash('success','Widget flotante actualizado. Se ubicará en '.($resolvedPosition==='right'?'la derecha':'la izquierda').' para no chocar con WhatsApp.');
        header('Location: widget.php'); exit;
    }catch(Throwable $e){ flash('error',$e->getMessage()); }
}

$set=settings();
$pageTitle='Widget flotante';
$active='widget';
$whatsappPosition=($set['whatsapp_position']??'left')==='right'?'right':'left';
$currentRequested=$set['chat_widget_position']??'auto';
$currentResolved=$set['chat_widget_resolved_position']??($whatsappPosition==='right'?'left':'right');
$widgetMode=$set['chat_widget_mode']??'embed';
$currentWidgetUrl=trim((string)($set['chat_widget_url']??$set['nivo_widget_url']??''));
$currentWidgetEmbed=trim((string)($set['chat_widget_embed_code']??''));
if($currentWidgetEmbed!=='' && $currentWidgetUrl==='') $widgetMode='embed';
elseif($currentWidgetUrl!=='' && $currentWidgetEmbed==='') $widgetMode='url';
require __DIR__.'/_header.php';
?>
<div class="page-heading animate-in">
  <div><p class="eyebrow">CHAT FLOTANTE</p><h1>Widget de chat</h1><p class="muted">Conecta NIVO Web Chat o cualquier proveedor externo sin competir visualmente con WhatsApp.</p></div>
  <div class="heading-actions"><a class="button secondary" href="../?preview=1" target="_blank" rel="noopener"><?=icon('eye')?> Vista previa</a></div>
</div>

<section class="panel wide animate-in chat-widget-admin">
  <div class="chat-widget-intro">
    <div class="chat-widget-brand">
      <span class="chat-widget-brand-icon"><?=icon('message')?></span>
      <div><p class="eyebrow">CONFIGURACIÓN PRINCIPAL</p><h2>NIVO o cualquier chat compatible</h2><p>Puedes usar una URL embebible HTTPS o pegar el código de integración que te entrega tu proveedor.</p></div>
    </div>
    <div class="chat-widget-status <?=($set['chat_widget_enabled']??$set['nivo_widget_enabled']??'0')==='1'?'is-on':'is-off'?>">
      <b><?=($set['chat_widget_enabled']??$set['nivo_widget_enabled']??'0')==='1'?'ACTIVO':'INACTIVO'?></b>
      <span>Posición efectiva: <?=h($currentResolved==='right'?'Derecha':'Izquierda')?></span>
    </div>
  </div>

  <form method="post" class="chat-widget-form" data-unsaved-form>
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <label class="premium-switch widget-master-switch">
      <input type="checkbox" name="chat_widget_enabled" <?=($set['chat_widget_enabled']??$set['nivo_widget_enabled']??'0')==='1'?'checked':''?>>
      <span class="switch-ui" aria-hidden="true"></span>
      <span><b>Activar widget de chat</b><small>Si lo desactivas, la configuración queda guardada pero no aparece en el sitio.</small></span>
    </label>

    <div class="chat-widget-help">
      <span class="chat-widget-help-icon"><?=icon('info')?></span>
      <div><b>¿Dónde pego el código de NIVO?</b><p>En NIVO Web Chat usa <strong>“Código de instalación”</strong> y pega el snippet completo que comienza con <code>&lt;script</code> en el campo <strong>“Código de instalación”</strong>. La URL es opcional y solo se usa cuando un proveedor entrega una dirección HTTPS directa.</p></div>
    </div>

    <div class="chat-widget-setup-grid">
      <label>Proveedor / nombre<input name="chat_widget_provider" maxlength="80" value="<?=h($set['chat_widget_provider']??$set['nivo_widget_title']??'NIVO Web Chat')?>" placeholder="NIVO Web Chat"></label>
      <label>Cómo se instala
        <select name="chat_widget_mode">
          <option value="embed" <?=$widgetMode==='embed'?'selected':''?>>Código de instalación (recomendado)</option>
          <option value="url" <?=$widgetMode==='url'?'selected':''?>>URL embebible / iframe</option>
        </select>
        <small class="field-hint">Para NIVO selecciona “Código de instalación”.</small>
      </label>
      <label>Título del launcher<input name="chat_widget_title" maxlength="100" value="<?=h($set['chat_widget_title']??$set['nivo_widget_greeting']??'¿Necesitas ayuda?')?>" placeholder="¿Necesitas ayuda?"></label>
      <label>Texto secundario<input name="chat_widget_subtitle" maxlength="120" value="<?=h($set['chat_widget_subtitle']??'Chatea con nosotros')?>" placeholder="Chatea con nosotros"></label>
    </div>

    <label class="chat-widget-full-field">URL embebible del widget <span class="optional-label">Opcional</span><input type="url" name="chat_widget_url" placeholder="https://..." value="<?=h($set['chat_widget_url']??$set['nivo_widget_url']??'')?>"><small class="field-hint">Úsala solo si el proveedor entrega una URL directa para embeber. No pegues aquí código &lt;script&gt;.</small></label>

    <label class="chat-widget-full-field">Código de instalación <span class="recommended-label">Recomendado para NIVO</span><textarea name="chat_widget_embed_code" rows="6" placeholder="<script src=\"https://.../nivo-widget.js\" data-zynko-key=\"...\" async></script>"><?=h($set['chat_widget_embed_code']??'')?></textarea><small class="field-hint">Pega el código completo que entrega ZYNKO/NIVO. Este campo funciona sin necesidad de completar la URL.</small></label>

    <div class="chat-position-section">
      <div class="section-heading compact"><div><h3>Ubicación inteligente</h3><p class="muted">El widget nunca se colocará en el mismo lado que WhatsApp.</p></div></div>
      <div class="chat-position-options">
        <label class="chat-position-choice"><input type="radio" name="chat_widget_position" value="auto" <?=$currentRequested==='auto'?'checked':''?>><span><b>Automático</b><small>Siempre usa el lado contrario a WhatsApp.</small></span></label>
        <label class="chat-position-choice"><input type="radio" name="chat_widget_position" value="left" <?=$currentRequested==='left'?'checked':''?>><span><b>Izquierda</b><small>Si WhatsApp ya está ahí, se moverá automáticamente.</small></span></label>
        <label class="chat-position-choice"><input type="radio" name="chat_widget_position" value="right" <?=$currentRequested==='right'?'checked':''?>><span><b>Derecha</b><small>Si WhatsApp ya está ahí, se moverá automáticamente.</small></span></label>
      </div>
      <div class="widget-position-preview">
        <div class="widget-side active-wa"><span>WhatsApp</span><b><?=h($whatsappPosition==='left'?'Izquierda':'Derecha')?></b></div>
        <div class="widget-page-mini"><span>Tu sitio</span><small>sin superposición</small></div>
        <div class="widget-side active-chat"><span><?=h($set['chat_widget_provider']??'Chat')?></span><b><?=h($currentResolved==='right'?'Derecha':'Izquierda')?></b></div>
      </div>
    </div>

    <div class="chat-widget-note"><?=icon('shield')?><div><b>Compatibilidad</b><p>URL embebible: IZZY usa un panel flotante propio. Código embed: el proveedor controla su propia interfaz; la posición se mantiene separada de WhatsApp cuando la integración lo permite.</p></div></div>
    <div class="form-actions"><button>Guardar widget flotante</button></div>
  </form>
</section>
<?php require __DIR__.'/_footer.php';
