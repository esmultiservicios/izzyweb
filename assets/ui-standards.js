(function(window,document){
  'use strict';

  const svg=(body)=>'<svg class="ui-action-icon" viewBox="0 0 24 24" aria-hidden="true">'+body+'</svg>';
  const icons={
    back:svg('<path d="M15 18l-6-6 6-6"/>'),
    next:svg('<path d="M9 18l6-6-6-6"/>'),
    save:svg('<path d="M5 4h12l2 2v14H5z"/><path d="M8 4v5h8V4M8 15h8"/>'),
    add:svg('<path d="M12 5v14M5 12h14"/>'),
    edit:svg('<path d="M4 20h4l11-11-4-4L4 16z"/><path d="M13 7l4 4"/>'),
    delete:svg('<path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13"/>'),
    upload:svg('<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>'),
    download:svg('<path d="M12 4v12M7 11l5 5 5-5"/><path d="M5 20h14"/>'),
    search:svg('<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>'),
    view:svg('<path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.7"/>'),
    send:svg('<path d="M3 11l17-7-7 17-2-7z"/><path d="M11 14l9-10"/>'),
    close:svg('<path d="M6 6l12 12M18 6L6 18"/>'),
    login:svg('<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 4h6v16h-6"/>'),
    check:svg('<path d="M5 12l4 4 10-10"/>'),
    copy:svg('<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'),
    refresh:svg('<path d="M20 6v5h-5"/><path d="M4 18v-5h5"/><path d="M18.5 9A7 7 0 0 0 6.2 6.2L4 8M5.5 15A7 7 0 0 0 17.8 17.8L20 16"/>'),
    archive:svg('<path d="M4 7h16v13H4zM3 3h18v4H3zM9 11h6"/>'),
    mail:svg('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>'),
    lock:svg('<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>'),
    user:svg('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'),
    publish:svg('<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>'),
    gear:svg('<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.4 1A8 8 0 0 0 15 6l-.3-2.6h-4L10.5 6A8 8 0 0 0 9 7L6.6 6 4.5 9.5l2 1.5a7 7 0 0 0 0 2l-2 1.5L6.6 18 9 17a8 8 0 0 0 1.5 1l.2 2.6h4L15 18a8 8 0 0 0 1.5-1l2.4 1 2.1-3.5-2.1-1.5a7 7 0 0 0 .1-1z"/>')
  };

  const actionFromText=(text)=>{
    const t=(text||'').trim().toLowerCase();
    if(/atr[aá]s|back|volver/.test(t))return 'back';
    if(/siguiente|continuar|next/.test(t))return 'next';
    if(/guardar|save|actualizar|update/.test(t))return 'save';
    if(/agregar|a[nñ]adir|nuevo|nueva|crear|add|new|create/.test(t))return 'add';
    if(/editar|edit/.test(t))return 'edit';
    if(/eliminar|borrar|remove|delete/.test(t))return 'delete';
    if(/subir|upload|seleccionar archivo|choose file/.test(t))return 'upload';
    if(/descargar|download|exportar|export/.test(t))return 'download';
    if(/buscar|search|find/.test(t))return 'search';
    if(/ver |vista|preview|open|abrir/.test(t))return 'view';
    if(/copiar|copy/.test(t))return 'copy';
    if(/recargar|revisar|recheck|refresh|reiniciar|reset/.test(t))return 'refresh';
    if(/archivar|archive|spam/.test(t))return 'archive';
    if(/correo|email|reply|responder/.test(t))return 'mail';
    if(/contrase[nñ]a|password|2fa|authenticator|segur/.test(t))return 'lock';
    if(/usuario|user|cuenta|account/.test(t))return 'user';
    if(/publicar|publish|submit for approval/.test(t))return 'publish';
    if(/enviar|send|probar|test|whatsapp|hablar|solicitar|request/.test(t))return 'send';
    if(/cerrar|cancelar|close|cancel|descartar|discard|clear/.test(t))return 'close';
    if(/entrar|iniciar sesi[oó]n|login|sign in/.test(t))return 'login';
    if(/finalizar|instalar|confirmar|aplicar|finish|install|confirm|apply|active/.test(t))return 'check';
    if(/configurar|settings|config|permisos|permissions|health/.test(t))return 'gear';
    if(/planes|conocer|dashboard|page content|sitio|site|preview/.test(t))return 'view';
    return null;
  };

  function iconizeButtons(root=document){
    root.querySelectorAll('button,.button,.btn').forEach((button)=>{
      if(button.dataset.uiIconized==='1')return;
      if(button.classList.contains('password-toggle')||button.classList.contains('notify-close')||button.classList.contains('modal-close')||button.classList.contains('icon-btn'))return;
      if(button.querySelector('svg,img,.ui-action-icon,[class*="icon"],i')||/[←→↑↓↻☰⌕📎✕×✓★]/.test(button.textContent||'')){button.dataset.uiIconized='1';return;}
      const type=button.dataset.uiIcon||actionFromText(button.textContent);
      if(!type||!icons[type])return;
      button.insertAdjacentHTML('afterbegin',icons[type]);
      button.dataset.uiIconized='1';
    });
  }

  function initSelect2(root=document){
    if(!window.jQuery||!window.jQuery.fn||typeof window.jQuery.fn.select2!=='function')return;
    window.jQuery(root).find('select:not([data-no-select2])').each(function(){
      if(this.dataset.s2Ready)return;
      window.jQuery(this).select2({minimumResultsForSearch:8,width:'100%'});
    });
  }

  function fileSummary(files){
    if(!files.length)return 'Arrastra y suelta, pega desde el portapapeles o selecciona el archivo.';
    if(files.length===1)return files[0].name;
    return files.length+' archivos seleccionados';
  }

  function upgradeFileInput(input){
    if(input.dataset.uiFileReady==='1'||input.closest('[data-upload-zone],.upload-zone,.drop-zone'))return;
    input.dataset.uiFileReady='1';
    input.classList.add('ui-file-input-hidden');

    const zone=document.createElement('div');
    zone.className='ui-file-zone';
    zone.tabIndex=0;
    zone.setAttribute('role','button');
    zone.setAttribute('aria-label','Seleccionar archivos');
    zone.innerHTML='<span class="ui-file-zone-icon">'+icons.upload+'</span>'+
      '<span class="ui-file-zone-copy"><strong>Adjuntar archivo</strong><small></small></span>'+
      '<button type="button" class="ui-file-zone-button">Seleccionar archivo</button><span class="ui-file-list"></span>';
    input.insertAdjacentElement('afterend',zone);
    const summary=zone.querySelector('.ui-file-zone-copy small');
    const list=zone.querySelector('.ui-file-list');
    const choose=zone.querySelector('.ui-file-zone-button');

    const render=()=>{
      const files=[...input.files];
      summary.textContent=fileSummary(files);
      list.innerHTML='';
      files.slice(0,8).forEach(file=>{
        const chip=document.createElement('span');
        chip.className='ui-file-chip';
        chip.textContent=file.name;
        list.appendChild(chip);
      });
      if(files.length>8){const chip=document.createElement('span');chip.className='ui-file-chip';chip.textContent='+'+(files.length-8)+' más';list.appendChild(chip);}
    };
    const assign=(incoming)=>{
      const files=[...incoming].filter(Boolean);
      if(!files.length)return;
      if(typeof DataTransfer==='undefined'){input.click();return;}
      const transfer=new DataTransfer();
      (input.multiple?files:files.slice(0,1)).forEach(file=>transfer.items.add(file));
      input.files=transfer.files;
      input.dispatchEvent(new Event('change',{bubbles:true}));
      render();
    };
    const open=()=>input.click();
    choose.addEventListener('click',(e)=>{e.stopPropagation();open();});
    zone.addEventListener('click',(e)=>{if(e.target.closest('button'))return;open();});
    zone.addEventListener('keydown',(e)=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();open();}});
    zone.addEventListener('dragover',(e)=>{e.preventDefault();zone.classList.add('is-dragging');});
    zone.addEventListener('dragleave',()=>zone.classList.remove('is-dragging'));
    zone.addEventListener('drop',(e)=>{e.preventDefault();zone.classList.remove('is-dragging');assign(e.dataTransfer.files);});
    zone.addEventListener('paste',(e)=>{
      const files=[...e.clipboardData.items].filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
      if(files.length){e.preventDefault();assign(files);}
    });
    input.addEventListener('change',render);
    render();
  }

  function upgradeFiles(root=document){root.querySelectorAll('input[type="file"]').forEach(upgradeFileInput);}


  function upgradePasswordInput(input){
    if(!input||input.dataset.uiPasswordReady==='1')return;
    if(input.closest('.password-wrap')&&input.closest('.password-wrap').querySelector('.password-toggle')){
      input.dataset.uiPasswordReady='1';
      return;
    }
    if(input.closest('.ui-password-wrap')){
      input.dataset.uiPasswordReady='1';
      return;
    }
    input.dataset.uiPasswordReady='1';
    const wrap=document.createElement('span');
    wrap.className='ui-password-wrap';
    input.parentNode.insertBefore(wrap,input);
    wrap.appendChild(input);
    const button=document.createElement('button');
    button.type='button';
    button.className='ui-password-toggle';
    button.setAttribute('aria-label','Mostrar contraseña');
    button.setAttribute('aria-pressed','false');
    button.setAttribute('tabindex','-1');
    button.innerHTML='<svg class="ui-eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg><svg class="ui-eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A11 11 0 0 1 12 6c6.2 0 9.7 6 9.7 6a14.5 14.5 0 0 1-3 3.5M6.6 6.8C3.8 8.7 2.3 12 2.3 12s3.5 6 9.7 6c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
    wrap.appendChild(button);
    button.addEventListener('click',()=>{
      const showing=input.type==='text';
      input.type=showing?'password':'text';
      input.toggleAttribute('data-ui-password-visible',!showing);
      button.setAttribute('aria-pressed',showing?'false':'true');
      button.setAttribute('aria-label',showing?'Mostrar contraseña':'Ocultar contraseña');
      input.focus({preventScroll:true});
      try{input.setSelectionRange(input.value.length,input.value.length);}catch(e){}
    });
  }

  function upgradePasswords(root=document){
    if(root.matches?.('input[type="password"]'))upgradePasswordInput(root);
    root.querySelectorAll?.('input[type="password"]').forEach(upgradePasswordInput);
  }

  function bridgeMessages(root=document){
    if(typeof window.showNotify!=='function')return;
    root.querySelectorAll('.alert.success,.alert.error,.alert.danger,.alert.warning,.alert.info,[data-ui-notify]').forEach((node)=>{
      if(node.dataset.uiNotified==='1')return;
      const type=node.dataset.uiNotify || (node.classList.contains('danger')?'danger':node.classList.contains('error')?'error':node.classList.contains('warning')?'warning':node.classList.contains('success')?'success':'info');
      const text=(node.dataset.message||node.textContent||'').trim();
      if(!text)return;
      node.dataset.uiNotified='1';
      window.showNotify(text,type);
      if(node.classList.contains('alert'))node.hidden=true;
    });
  }

  function init(root=document){
    initSelect2(root);
    upgradeFiles(root);
    upgradePasswords(root);
    iconizeButtons(root);
    bridgeMessages(root);
  }

  document.addEventListener('DOMContentLoaded',()=>{
    try{
      const params=new URLSearchParams(window.location.search);
      if(params.get('installed')==='1'){
        Object.keys(window.sessionStorage).filter(key=>key.indexOf('izzy_installer_draft_')===0).forEach(key=>window.sessionStorage.removeItem(key));
      }
    }catch(error){}
    init(document);

    const observer=new MutationObserver((mutations)=>{
      mutations.forEach((mutation)=>mutation.addedNodes.forEach((node)=>{
        if(node.nodeType===1)init(node.matches?.('select,input[type=\"file\"],button,.button,.btn,.alert,[data-ui-notify]')?node.parentElement||node:node);
      }));
    });
    observer.observe(document.body,{childList:true,subtree:true});
  });
  window.IZZYUI=Object.freeze({init,initSelect2,upgradeFiles,upgradePasswords,iconizeButtons,bridgeMessages});
})(window,document);
