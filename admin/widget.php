<?php
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');
$set=settings();

/**
 * Mantiene el código de instalación de NIVO sincronizado con la posición
 * efectiva elegida en IZZY. El generador de ZYNKO puede entregar
 * data-position y/o data-side; si no vienen, se agregan al <script>.
 */
function normalize_nivo_embed_position(string $embed,string $position):string{
    if($embed==='' || !preg_match('/data-zynko-key\s*=/i',$embed)){
        return $embed;
    }

    $position=$position==='left'?'left':'right';
    foreach(['data-position','data-side'] as $attribute){
        $pattern='/(\s'.preg_quote($attribute,'/').'\s*=\s*)([\"\'])(.*?)(\2)/i';
        if(preg_match($pattern,$embed)){
            $embed=preg_replace($pattern,'$1$2'.$position.'$4',$embed,1)??$embed;
        }else{
            $embed=preg_replace(
                '/<script\b(?=[^>]*data-zynko-key\s*=)([^>]*)>/i',
                '<script$1 '.$attribute.'="'.$position.'">',
                $embed,
                1
            )??$embed;
        }
    }
    return $embed;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $whatsappEnabled=isset($_POST['whatsapp_enabled'])?'1':'0';
        $whatsappNumber=preg_replace('/\D+/','',(string)($_POST['whatsapp_number']??($set['whatsapp_number']??$set['phone_digits']??$set['phone']??'')));
        $whatsappMessage=trim((string)($_POST['whatsapp_message']??($set['whatsapp_message']??'')));
        $whatsappPosition=in_array($_POST['whatsapp_position']??($set['whatsapp_position']??'left'),['left','right'],true)?(string)($_POST['whatsapp_position']??$set['whatsapp_position']):'left';
        $whatsappOrder=max(1,min(999,(int)($_POST['whatsapp_order']??($set['whatsapp_order']??10))));

        if($whatsappEnabled==='1' && ($whatsappNumber==='' || strlen($whatsappNumber)<8 || strlen($whatsappNumber)>15)){
            throw new RuntimeException('Ingresa un número de WhatsApp válido, incluyendo el código de país.');
        }

        $enabled=isset($_POST['chat_widget_enabled'])?'1':'0';
        $provider=trim((string)($_POST['chat_widget_provider']??'NIVO Web Chat'));
        $title=trim((string)($_POST['chat_widget_title']??'¿Necesitas ayuda?'));
        $subtitle=trim((string)($_POST['chat_widget_subtitle']??'Chatea con nosotros'));
        $mode=in_array($_POST['chat_widget_mode']??'embed',['url','embed'],true)?(string)$_POST['chat_widget_mode']:'embed';
        $url=trim((string)($_POST['chat_widget_url']??''));
        $embed=trim((string)($_POST['chat_widget_embed_code']??''));
        $requestedPosition=in_array($_POST['chat_widget_position']??'right',['left','right'],true)?(string)$_POST['chat_widget_position']:'right';
        $chatOrder=max(1,min(999,(int)($_POST['chat_widget_order']??($set['chat_widget_order']??20))));
        $gap=max(8,min(40,(int)($_POST['floating_widget_gap']??($set['floating_widget_gap']??12))));

        // El contenido real manda: si se pega código de instalación, NIVO no necesita URL.
        if($embed!=='' && $url===''){
            $mode='embed';
        }elseif($url!=='' && $embed===''){
            $mode='url';
        }

        if($mode==='url' && $url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || !preg_match('~^https://~i',$url))){
            throw new RuntimeException('La URL del chat debe ser una URL HTTPS completa.');
        }
        if($enabled==='1' && $mode==='url' && $url===''){
            throw new RuntimeException('Para el modo URL embebible, agrega una URL HTTPS válida.');
        }
        if($enabled==='1' && $mode==='embed' && $embed===''){
            throw new RuntimeException('Pega el código de instalación que entrega NIVO antes de activarlo.');
        }

        // Nunca permitimos que WhatsApp y NIVO queden en el mismo lado.
        $resolvedPosition=$requestedPosition===$whatsappPosition
            ?($whatsappPosition==='right'?'left':'right')
            :$requestedPosition;

        if($mode==='embed' && $embed!==''){
            $embed=normalize_nivo_embed_position($embed,$resolvedPosition);
        }

        save_setting('whatsapp_enabled',$whatsappEnabled);
        save_setting('whatsapp_number',$whatsappNumber);
        save_setting('whatsapp_message',$whatsappMessage);
        save_setting('whatsapp_position',$whatsappPosition);
        save_setting('whatsapp_order',(string)$whatsappOrder);

        save_setting('chat_widget_enabled',$enabled);
        save_setting('chat_widget_provider',$provider!==''?$provider:'NIVO Web Chat');
        save_setting('chat_widget_title',$title!==''?$title:'¿Necesitas ayuda?');
        save_setting('chat_widget_subtitle',$subtitle!==''?$subtitle:'Chatea con nosotros');
        save_setting('chat_widget_mode',$mode);
        save_setting('chat_widget_url',$url);
        save_setting('chat_widget_embed_code',$embed);
        // Guardamos ya la posición efectiva para que el formulario refleje el resultado real.
        save_setting('chat_widget_position',$resolvedPosition);
        save_setting('chat_widget_resolved_position',$resolvedPosition);
        save_setting('chat_widget_order',(string)$chatOrder);
        save_setting('floating_widget_gap',(string)$gap);

        // Compatibilidad con claves legacy de NIVO.
        save_setting('nivo_widget_enabled',$enabled);
        save_setting('nivo_widget_url',$url);
        save_setting('nivo_widget_title',$provider!==''?$provider:'NIVO Web Chat');
        save_setting('nivo_widget_greeting',$title!==''?$title:'¿Necesitas ayuda?');

        log_activity('floating_chat_widget_update','Updated floating widgets',[
            'whatsapp_position'=>$whatsappPosition,
            'chat_mode'=>$mode,
            'requested_chat_position'=>$requestedPosition,
            'resolved_chat_position'=>$resolvedPosition,
            'chat_enabled'=>$enabled==='1'
        ]);

        $moved=$requestedPosition!==$resolvedPosition
            ?' NIVO se movió automáticamente al lado contrario para evitar que se monte sobre WhatsApp.'
            :'';
        flash('success','Widgets flotantes actualizados. WhatsApp: '.($whatsappPosition==='right'?'derecha':'izquierda').'. NIVO: '.($resolvedPosition==='right'?'derecha':'izquierda').'.'.$moved);
        header('Location: widget.php');
        exit;
    }catch(Throwable $e){
        flash('error',$e->getMessage());
    }
}

$set=settings();
$pageTitle='Widgets flotantes';
$active='widget';
$whatsappPosition=($set['whatsapp_position']??'left')==='right'?'right':'left';
$whatsappNumber=preg_replace('/\D+/','',(string)($set['whatsapp_number']??$set['phone_digits']??$set['phone']??''));
$currentRequested=in_array($set['chat_widget_position']??'right',['left','right'],true)?(string)$set['chat_widget_position']:'right';
$currentResolved=$currentRequested===$whatsappPosition?($whatsappPosition==='right'?'left':'right'):$currentRequested;
$widgetMode=in_array($set['chat_widget_mode']??'embed',['url','embed'],true)?(string)$set['chat_widget_mode']:'embed';
if(trim((string)($set['chat_widget_embed_code']??''))!=='' && trim((string)($set['chat_widget_url']??''))===''){
    $widgetMode='embed';
}
$whatsappOrder=max(1,(int)($set['whatsapp_order']??10));
$chatOrder=max(1,(int)($set['chat_widget_order']??20));
$gap=max(8,min(40,(int)($set['floating_widget_gap']??12)));
require __DIR__.'/_header.php';
?>
<div class="page-heading animate-in floating-widgets-heading">
    <div>
        <p class="eyebrow">WIDGETS</p>
        <h1>Widgets flotantes</h1>
        <p class="muted">Administra WhatsApp, NIVO Web Chat y cualquier widget futuro desde un solo lugar. El sistema evita automáticamente que dos accesos se monten en el mismo lado.</p>
    </div>
    <div class="heading-actions">
        <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener"><?=icon('eye')?> Ver sitio</a>
    </div>
</div>

<form method="post" class="floating-widgets-form animate-in" data-unsaved-form>
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

    <section class="panel wide floating-widget-section floating-widget-section--whatsapp">
        <div class="floating-widget-section-title">
            <div>
                <h2>WhatsApp</h2>
                <p>Botón de contacto directo. Configura el número, mensaje, posición y orden.</p>
            </div>
            <span class="widget-integrated-badge">Integrado</span>
        </div>

        <label class="premium-switch floating-widget-toggle-row">
            <input type="checkbox" name="whatsapp_enabled" <?=($set['whatsapp_enabled']??'1')==='1'?'checked':''?>>
            <span class="switch-ui" aria-hidden="true"></span>
            <span>
                <b>Mostrar WhatsApp</b>
                <small>Activa o desactiva el botón flotante en el sitio público.</small>
            </span>
        </label>

        <div class="two-col floating-widget-field-grid">
            <label>Número de WhatsApp
                <input
                    name="whatsapp_number"
                    inputmode="numeric"
                    autocomplete="tel"
                    maxlength="15"
                    value="<?=h($whatsappNumber)?>"
                    placeholder="50489136844"
                >
                <small class="field-hint">Solo números, incluyendo el código de país. Ejemplo: 50489136844.</small>
            </label>
            <label>Posición
                <select name="whatsapp_position">
                    <option value="left" <?=$whatsappPosition==='left'?'selected':''?>>Izquierda</option>
                    <option value="right" <?=$whatsappPosition==='right'?'selected':''?>>Derecha</option>
                </select>
                <small class="field-hint">NIVO se moverá al lado contrario si ambos coinciden.</small>
            </label>
            <label>Orden
                <input type="number" name="whatsapp_order" min="1" max="999" value="<?=h((string)$whatsappOrder)?>">
                <small class="field-hint">Un número menor queda más cerca de la parte inferior.</small>
            </label>
        </div>

        <label>Mensaje predeterminado de WhatsApp
            <textarea name="whatsapp_message" rows="4"><?=h($set['whatsapp_message']??'Hola, quiero información sobre IZZY.')?></textarea>
        </label>
    </section>

    <section class="panel wide floating-widget-section floating-widget-section--other">
        <div class="floating-widget-section-title floating-widget-section-title--with-action">
            <div>
                <h2>Otros widgets</h2>
                <p>Aquí configuras NIVO Web Chat y cualquier otro chat o soporte externo que agregues en el futuro.</p>
            </div>
        </div>

        <div class="widget-help-banner">
            <span class="widget-help-icon" aria-hidden="true">i</span>
            <div>
                <b>¿Dónde pego el código de NIVO?</b>
                <p>En <strong>Cómo se instala</strong> selecciona <strong>“Código de instalación (recomendado)”</strong> y pega el código completo que comienza con <code>&lt;script</code>. La URL es opcional y solo se usa si un proveedor entrega una dirección HTTPS directa para embeber.</p>
            </div>
        </div>

        <article class="floating-external-widget-card">
            <div class="floating-external-widget-head">
                <div>
                    <h3><?=h($set['chat_widget_provider']??$set['nivo_widget_title']??'NIVO Web Chat')?></h3>
                    <p>Posición y orden independientes, con prevención automática de cruces.</p>
                </div>
                <span class="widget-status-pill <?=($set['chat_widget_enabled']??$set['nivo_widget_enabled']??'0')==='1'?'is-on':'is-off'?>">
                    <?=($set['chat_widget_enabled']??$set['nivo_widget_enabled']??'0')==='1'?'Activo':'Inactivo'?>
                </span>
            </div>

            <label class="premium-switch floating-widget-toggle-row floating-widget-toggle-row--compact">
                <input type="checkbox" name="chat_widget_enabled" <?=($set['chat_widget_enabled']??$set['nivo_widget_enabled']??'0')==='1'?'checked':''?>>
                <span class="switch-ui" aria-hidden="true"></span>
                <span>
                    <b>Mostrar NIVO Web Chat</b>
                    <small>La configuración queda guardada aunque desactives temporalmente el widget.</small>
                </span>
            </label>

            <div class="two-col floating-widget-field-grid">
                <label>Nombre
                    <input name="chat_widget_provider" maxlength="80" value="<?=h($set['chat_widget_provider']??$set['nivo_widget_title']??'NIVO Web Chat')?>" placeholder="NIVO Web Chat">
                </label>
                <label>Cómo se instala
                    <select name="chat_widget_mode" data-chat-widget-mode>
                        <option value="embed" <?=$widgetMode==='embed'?'selected':''?>>Código de instalación (recomendado)</option>
                        <option value="url" <?=$widgetMode==='url'?'selected':''?>>URL embebible</option>
                    </select>
                    <small class="field-hint">Para NIVO usa Código de instalación.</small>
                </label>
                <label>Posición
                    <select name="chat_widget_position">
                        <option value="left" <?=$currentResolved==='left'?'selected':''?>>Izquierda</option>
                        <option value="right" <?=$currentResolved==='right'?'selected':''?>>Derecha</option>
                    </select>
                    <small class="field-hint">Si coincide con WhatsApp, al guardar NIVO cambia automáticamente al lado contrario.</small>
                </label>
                <label>Orden
                    <input type="number" name="chat_widget_order" min="1" max="999" value="<?=h((string)$chatOrder)?>">
                    <small class="field-hint">Se conserva para futuras combinaciones de widgets del mismo lado.</small>
                </label>
            </div>

            <div class="two-col floating-widget-field-grid">
                <label>Título del launcher
                    <input name="chat_widget_title" maxlength="100" value="<?=h($set['chat_widget_title']??$set['nivo_widget_greeting']??'¿Necesitas ayuda?')?>" placeholder="¿Necesitas ayuda?">
                </label>
                <label>Texto secundario
                    <input name="chat_widget_subtitle" maxlength="120" value="<?=h($set['chat_widget_subtitle']??'Chatea con nosotros')?>" placeholder="Chatea con nosotros">
                </label>
            </div>

            <div class="chat-source-block" data-chat-source="url" <?=$widgetMode==='url'?'':'hidden'?>>
                <label>URL embebible del widget
                    <input type="url" name="chat_widget_url" placeholder="https://..." value="<?=h($set['chat_widget_url']??$set['nivo_widget_url']??'')?>">
                    <small class="field-hint">Úsala solo si el proveedor entrega una URL HTTPS directa para embeber. No pegues aquí código &lt;script&gt;.</small>
                </label>
            </div>

            <div class="chat-source-block" data-chat-source="embed" <?=$widgetMode==='embed'?'':'hidden'?>>
                <label>Código de instalación
                    <textarea name="chat_widget_embed_code" rows="7" placeholder="Pega aquí el código completo que te entrega ZYNKO / NIVO (debe comenzar con <script y terminar con </script>)"><?=h($set['chat_widget_embed_code']??'')?></textarea>
                    <small class="field-hint">Este es el campo correcto para el código de NIVO. No necesitas agregar una URL cuando usas este modo.</small>
                </label>
            </div>
        </article>

        <div class="widget-separation-section">
            <div>
                <h3>Separación y prevención de cruces</h3>
                <p>WhatsApp y NIVO nunca quedan uno encima del otro. Si ambos seleccionan el mismo lado, NIVO pasa automáticamente al lado contrario.</p>
            </div>
            <label class="widget-gap-field">Separación entre widgets
                <input type="number" name="floating_widget_gap" min="8" max="40" value="<?=h((string)$gap)?>">
                <small class="field-hint">Recomendado: 12–20 px.</small>
            </label>

            <div class="widget-position-preview widget-position-preview--reference">
                <div class="widget-side <?=$whatsappPosition==='left'?'active-wa':'active-chat'?>">
                    <span><?=h($whatsappPosition==='left'?'WhatsApp':($set['chat_widget_provider']??'NIVO'))?></span>
                    <b>Izquierda</b>
                </div>
                <div class="widget-page-mini"><span>Tu sitio</span><small>sin superposición</small></div>
                <div class="widget-side <?=$whatsappPosition==='right'?'active-wa':'active-chat'?>">
                    <span><?=h($whatsappPosition==='right'?'WhatsApp':($set['chat_widget_provider']??'NIVO'))?></span>
                    <b>Derecha</b>
                </div>
            </div>
        </div>

        <div class="form-actions floating-widget-save-row">
            <button type="submit"><?=icon('save')?> Guardar widgets</button>
        </div>
    </section>
</form>

<script>
(() => {
    const mode = document.querySelector('[data-chat-widget-mode]');
    if (!mode) return;

    const sources = [...document.querySelectorAll('[data-chat-source]')];
    const syncSource = () => {
        sources.forEach((source) => {
            source.hidden = source.dataset.chatSource !== mode.value;
        });
    };

    mode.addEventListener('change', syncSource);
    syncSource();
})();
</script>
<?php require __DIR__.'/_footer.php';
