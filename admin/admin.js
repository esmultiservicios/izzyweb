const AdminOverlayManager = window.AdminOverlayManager = (() => {
  const sidebar = document.querySelector('[data-sidebar]');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
  const quickFind = document.querySelector('[data-admin-find]');
  const quickFindInput = document.querySelector('[data-admin-find-input]');
  const quickFindTriggers = [...document.querySelectorAll('[data-admin-find-open]')];
  let quickFindReturnFocus = null;

  const setSidebarState = (open) => {
    sidebar?.classList.toggle('open', open);
    sidebarBackdrop?.classList.toggle('show', open);
    sidebarToggle?.setAttribute('aria-expanded', String(open));
    sidebarToggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };

  const closeSide = () => setSidebarState(false);

  const closeTopbarMenus = (except = null) => {
    document.querySelectorAll('.admin-header details[open]').forEach((menu) => {
      if (menu !== except) menu.open = false;
    });
  };

  const closeAdminFind = ({ restoreFocus = false } = {}) => {
    const wasOpen = quickFind && !quickFind.hidden;
    if (quickFind) quickFind.hidden = true;
    document.body.classList.remove('admin-find-open');
    if (quickFindInput) quickFindInput.value = '';
    quickFindTriggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));

    if (restoreFocus && wasOpen && quickFindReturnFocus?.isConnected) {
      window.setTimeout(() => quickFindReturnFocus.focus({ preventScroll: true }), 0);
    }
  };

  const openSide = () => {
    closeTopbarMenus();
    closeAdminFind();
    setSidebarState(true);
  };

  const openAdminFind = (trigger = null) => {
    closeSide();
    closeTopbarMenus();
    quickFindReturnFocus = trigger || (typeof document.activeElement?.focus === 'function' ? document.activeElement : null);
    if (quickFind) quickFind.hidden = false;
    document.body.classList.add('admin-find-open');
    quickFindTriggers.forEach((button) => button.setAttribute('aria-expanded', 'true'));
    window.setTimeout(() => quickFindInput?.focus(), 30);
  };

  return Object.freeze({
    closeSide,
    closeTopbarMenus,
    closeAdminFind,
    openSide,
    openAdminFind,
    isSideOpen: () => sidebar?.classList.contains('open') ?? false,
  });
})();

window.addEventListener('orientationchange',()=> {
  window.requestAnimationFrame(()=> {
    AdminOverlayManager.closeSide();
    AdminOverlayManager.closeTopbarMenus();
    AdminOverlayManager.closeAdminFind();
  });
});

(()=> {
  const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)]; const toggle=q('[data-sidebar-toggle]'),back=q('[data-sidebar-backdrop]');
  const confirmDialog=(options)=> {
    if(window.Swal?.fire){
      const originalDidOpen=options?.didOpen;
      const swalOptions={...options,didOpen:(popup)=>{
        if(typeof originalDidOpen==='function')originalDidOpen(popup);
        // SweetAlert buttons are dynamic: decorate them after the modal exists.
        window.ESActionIcons?.scan?.(popup||document);
        window.setTimeout(()=>window.ESActionIcons?.scan?.(popup||document),0);
      }};
      return window.Swal.fire(swalOptions);
    }
    if(window.CMSModal?.fire)return window.CMSModal.fire({
      role:'alertdialog',
      variant:options.icon==='error'?'danger':(options.icon||'warning'),
      title:options.title,
      text:options.text,
      confirmText:options.confirmButtonText,
      cancelText:options.cancelButtonText,
      showCancel:options.showCancelButton,
      allowOutsideClick:options.allowOutsideClick
    });
    return Promise.resolve({isConfirmed:false,isDismissed:true});
  };
  toggle?.addEventListener('click',()=> {
    if(AdminOverlayManager.isSideOpen())AdminOverlayManager.closeSide();else AdminOverlayManager.openSide();
  }
  );back?.addEventListener('click',AdminOverlayManager.closeSide);qa('.admin-sidebar a').forEach(a=>a.addEventListener('click',AdminOverlayManager.closeSide)); qa('.profile-menu').forEach(d=>document.addEventListener('click',e=> {
    if(d.open&&!d.contains(e.target))d.open=false
  }
  )); qa('.action-menu').forEach(menu=> {
    menu.addEventListener('toggle',()=> {
      if(!menu.open)return;qa('.action-menu[open]').forEach(other=> {
        if(other!==menu)other.open=false
      }
      );const nav=q('nav',menu);menu.classList.remove('drop-up');if(nav) {
        const rect=nav.getBoundingClientRect();if(rect.bottom>window.innerHeight-12)menu.classList.add('drop-up');
      }
    }
    );q('nav',menu)?.addEventListener('click',e=> {
      if(e.target.closest('a,button'))menu.open=false;
    }
    );
  }
  ); qa('[data-stat]').forEach(el=> {
    const target=parseInt(el.dataset.stat||el.textContent,10)||0;let start=0;const dur=500,t0=performance.now();function tick(t) {
      const p=Math.min(1,(t-t0)/dur);el.textContent=Math.round(target*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(tick)
    }
    requestAnimationFrame(tick)
  }
  ); qa('.animate-in').forEach((el,i)=> {
    el.style.animationDelay=Math.min(i*45,360)+'ms'
  }
  ); const flash=q('[data-flash-message]');if(flash&&window.showNotify) {
    showNotify(flash.dataset.flashMessage||'',flash.dataset.flashType||'info');
  }
  const modal=q('[data-image-modal]'),mimg=q('[data-modal-image]'),mcap=q('[data-modal-caption]');function openModal(src,cap='') {
    if(!modal||!mimg)return;mimg.src=src;mcap.textContent=cap;modal.classList.add('open');modal.setAttribute('aria-hidden','false')
  }
  function closeModal() {
    modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');if(mimg)mimg.src=''
  }
  qa('[data-preview-src]').forEach(b=>b.addEventListener('click',()=>openModal(b.dataset.previewSrc||'',b.dataset.previewCaption||'')));q('[data-modal-close]')?.addEventListener('click',closeModal);document.addEventListener('keydown',e=> {
    if(e.key==='Escape')closeModal()
  }
  ); function initUpload(zone) {
    const input=q('input[type=file]',zone);
    const preview=q('[data-upload-preview]',zone);
    const name=q('[data-upload-name]',zone);
    const fileList=q('[data-upload-file-list]',zone);
    const chooseButton=q('[data-upload-choose]',zone);
    if(!input)return;

    const multiple=input.multiple;
    const maxFiles=Math.max(1,Number.parseInt(input.dataset.maxFiles||'12',10)||12);
    const maxTotalBytes=Math.max(0,Number.parseInt(input.dataset.maxTotalBytes||'0',10)||0);
    const allowedExtensions=(input.dataset.allowedExtensions||'')
      .split(',')
      .map(value=>value.trim().toLowerCase())
      .filter(Boolean);
    const accepts=(input.getAttribute('accept')||'')
      .split(',')
      .map(value=>value.trim().toLowerCase())
      .filter(Boolean);
    let files=[];
    let dragDepth=0;

    const notify=(message)=> {
      if(window.showNotify)showNotify(message,'warning');
    };
    const fileKey=(file)=>[file.name,file.size,file.lastModified].join(':');
    const extensionOf=(file)=>file.name.includes('.')?file.name.split('.').pop().toLowerCase():'';
    const formatBytes=(bytes)=>bytes>=1048576
      ?(bytes/1048576).toFixed(2)+' MB'
      :(bytes/1024).toFixed(1)+' KB';
    const allowed=(file)=> {
      if(allowedExtensions.length)return allowedExtensions.includes(extensionOf(file));
      if(!accepts.length)return true;
      return accepts.some(rule=> {
        if(rule.startsWith('.'))return extensionOf(file)===rule.slice(1);
        if(rule.endsWith('/*'))return file.type.toLowerCase().startsWith(rule.slice(0,-1));
        return file.type.toLowerCase()===rule;
      });
    };
    const removeAt=(index)=> {
      files.splice(index,1);
      sync();
    };

    function render() {
      if(preview) {
        preview.innerHTML='';
        files.forEach((file,index)=> {
          const card=document.createElement('div');
          card.className='upload-preview-item';
          const remove=document.createElement('button');
          remove.type='button';
          remove.textContent='×';
          remove.setAttribute('aria-label','Remove '+file.name);
          remove.addEventListener('click',()=>removeAt(index));

          if(file.type.startsWith('image/')) {
            const image=document.createElement('img');
            image.alt='Preview of '+file.name;
            const url=URL.createObjectURL(file);
            image.src=url;
            image.addEventListener('load',()=>URL.revokeObjectURL(url),{once:true});
            card.append(image);
          } else if(file.type.startsWith('video/')) {
            const video=document.createElement('video');
            video.muted=true;
            video.playsInline=true;
            video.preload='metadata';
            const url=URL.createObjectURL(file);
            video.src=url;
            video.addEventListener('loadedmetadata',()=>URL.revokeObjectURL(url),{once:true});
            card.append(video);
          } else {
            const badge=document.createElement('div');
            badge.className='upload-file-badge';
            badge.textContent=extensionOf(file).toUpperCase()||'FILE';
            card.append(badge);
          }
          card.append(remove);
          preview.append(card);
        });
      }

      if(name)name.textContent=files.length
        ?files.length+' file'+(files.length>1?'s':'')+' selected · '+formatBytes(files.reduce((sum,file)=>sum+file.size,0))
        :(input.dataset.emptyLabel||'No attachments selected.');

      if(fileList) {
        fileList.innerHTML='';
        if(!files.length) {
          const empty=document.createElement('p');
          empty.className='reply-file-empty';
          empty.textContent='No attachments selected.';
          fileList.append(empty);
        }
        files.forEach((file,index)=> {
          const item=document.createElement('article');
          item.className='reply-file-item';
          const icon=document.createElement('span');
          icon.className='reply-file-icon';
          icon.setAttribute('aria-hidden','true');
          icon.textContent=extensionOf(file).toUpperCase()||'FILE';
          const copy=document.createElement('div');
          const fileName=document.createElement('strong');
          const fileSize=document.createElement('small');
          fileName.textContent=file.name;
          fileSize.textContent=formatBytes(file.size);
          copy.append(fileName,fileSize);
          const remove=document.createElement('button');
          remove.type='button';
          remove.className='button danger small';
          remove.textContent='Remove';
          remove.setAttribute('aria-label','Remove '+file.name);
          remove.addEventListener('click',()=>removeAt(index));
          item.append(icon,copy,remove);
          fileList.append(item);
        });
      }
    }

    function sync() {
      const transfer=new DataTransfer();
      files.forEach(file=>transfer.items.add(file));
      input.files=transfer.files;
      render();
    }

    function add(list) {
      const incoming=[...list];
      if(!incoming.length)return;
      const invalid=incoming.filter(file=>!allowed(file));
      if(invalid.length)notify('One or more files use a format that is not allowed.');
      const existing=new Set(files.map(fileKey));
      let next=multiple?[...files]:[];
      let limitExceeded=false;
      let sizeExceeded=false;

      incoming.filter(allowed).forEach(file=> {
        if(existing.has(fileKey(file)))return;
        if(!multiple&&next.length)next=[];
        if(next.length>=maxFiles) {
          limitExceeded=true;
          return;
        }
        const nextSize=next.reduce((sum,current)=>sum+current.size,0)+file.size;
        if(maxTotalBytes&&nextSize>maxTotalBytes) {
          sizeExceeded=true;
          return;
        }
        existing.add(fileKey(file));
        next.push(file);
      });

      files=next;
      sync();
      if(limitExceeded)notify('You can attach up to '+maxFiles+' files.');
      if(sizeExceeded)notify('Attachments may use at most '+formatBytes(maxTotalBytes)+' combined.');
    }

    input.addEventListener('change',()=>add(input.files));
    chooseButton?.addEventListener('click',event=> {
      event.stopPropagation();
      input.click();
    });
    zone.addEventListener('dragenter',event=> {
      event.preventDefault();
      dragDepth++;
      zone.classList.add('dragover');
    });
    zone.addEventListener('dragover',event=> {
      event.preventDefault();
      if(event.dataTransfer)event.dataTransfer.dropEffect='copy';
      zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave',()=> {
      dragDepth=Math.max(0,dragDepth-1);
      if(!dragDepth)zone.classList.remove('dragover');
    });
    zone.addEventListener('drop',event=> {
      event.preventDefault();
      dragDepth=0;
      zone.classList.remove('dragover');
      add(event.dataTransfer?.files||[]);
    });
    zone.addEventListener('paste',event=> {
      const clipboardFiles=[...(event.clipboardData?.files||[])];
      if(!clipboardFiles.length)return;
      event.preventDefault();
      add(clipboardFiles);
    });
    zone.addEventListener('click',event=> {
      if(event.target.closest('button')||event.target===input)return;
      input.click();
    });
    zone.addEventListener('keydown',event=> {
      if((event.key==='Enter'||event.key===' ')&&!event.target.closest('button,input,select,textarea')) {
        event.preventDefault();
        input.click();
      }
    });
    render();
  }
  qa('[data-upload-zone]').forEach(initUpload);
  qa('[data-response-composer]').forEach(form=> {
    const editor=q('[data-rich-editor]',form);
    const content=q('[data-rich-editor-content]',form);
    const input=q('[data-rich-editor-input]',form);
    const error=q('[data-rich-editor-error]',form);
    if(!editor||!content||!input)return;
    const sync=()=> {
      input.value=content.innerHTML.trim();
      const hasText=(content.textContent||'').replace(/\u00a0/g,' ').trim()!=='';
      editor.classList.toggle('invalid',!hasText&&form.dataset.submitted==='1');
      if(error)error.hidden=hasText||form.dataset.submitted!=='1';
      return hasText;
    };
    qa('[data-editor-command]',editor).forEach(button=>button.addEventListener('click',()=> {
      content.focus();
      document.execCommand(button.dataset.editorCommand||'',false,button.dataset.editorValue||null);
      sync();
    }));
    content.addEventListener('input',sync);
    content.addEventListener('paste',event=> {
      event.preventDefault();
      const text=event.clipboardData?.getData('text/plain')||'';
      document.execCommand('insertText',false,text);
    });
    form.addEventListener('submit',event=> {
      form.dataset.submitted='1';
      if(!sync()) {
        event.preventDefault();
        content.focus();
        if(window.showNotify)showNotify('Write a response before sending.','warning');
      }
    });
  });
  qa('[data-project-media-select]').forEach(select=> {
    const preview=select.closest('.project-media-fields')?.querySelector('[data-project-media-preview]');
    const image=preview?.querySelector('img');
    const update=()=> {
      const source=select.options[select.selectedIndex]?.dataset.previewSrc||'';
      if(!preview||!image)return;
      preview.hidden=source==='';
      image.src=source;
    };
    select.addEventListener('change',update);
    update();
  }); qa('[data-method-select]').forEach(sel=> {
    const form=sel.closest('form');const update=()=> {
      qa('.email-method-panel[data-method]',form).forEach(panel=> {
        const active=panel.dataset.method===sel.value;
        panel.hidden=!active;
        qa('input,select,textarea,button',panel).forEach(control=> {
          control.disabled=!active;
          if(control.matches('select'))control.nextElementSibling?.querySelector('.cms-select-button')?.toggleAttribute('disabled',!active);
        });
      });
      const summary=q('[data-method-summary]',form);
      if(summary)summary.textContent=sel.value==='GRAPH'
        ?'Use a Microsoft 365 mailbox through Microsoft Graph.'
        :'Use an authenticated SMTP server.';
    }
    ;sel.addEventListener('change',update);update()
  }
  ); qa('form[data-swal-confirm]').forEach(form=>form.addEventListener('submit',async e=> {
    if(form.dataset.swalApproved==='1')return;
    const expected=form.dataset.confirmText;
    const confirmation=q('[name=confirmation]',form);
    if(expected&&confirmation&&confirmation.value.trim()!==expected) {
      e.preventDefault();
      e.stopImmediatePropagation();
      confirmation.focus();
      if(window.showNotify)showNotify('Type '+expected+' exactly to continue.','warning');
      return;
    }
    e.preventDefault();const result=await confirmDialog( {
      icon:'warning',title:form.dataset.swalConfirm||'Confirm action',text:form.dataset.swalText||'Please confirm this action.',showCancelButton:true,confirmButtonText:form.dataset.swalConfirmText||'Yes, continue',cancelButtonText:'Cancel',allowOutsideClick:false
    });if(result.isConfirmed) {
      form.dataset.swalApproved='1';if(form.requestSubmit)form.requestSubmit();else form.submit();
    }
  }
  )); qa('[data-confirm-text]').forEach(form=>form.addEventListener('submit',e=> {
    const expected=form.dataset.confirmText,input=q('[name=confirmation]',form);
    if(input)input.setCustomValidity('');
    if(input&&input.value.trim()!==expected) {
      e.preventDefault();
      input.setCustomValidity('Type '+expected+' exactly to continue.');
      input.focus();
      if(window.showNotify)showNotify('Type '+expected+' exactly to continue.','warning');
    }
  }
  )); qa('[data-cms-modal-open]').forEach(trigger=>trigger.addEventListener('click',async()=> {
    if(!window.CMSModal?.fire)return;
    const result=await window.CMSModal.fire({
      variant:trigger.dataset.cmsModalVariant||'info',
      title:trigger.dataset.cmsModalTitle||'Information',
      subtitle:trigger.dataset.cmsModalSubtitle||'CMS Core',
      text:trigger.dataset.cmsModalText||'',
      confirmText:trigger.dataset.cmsModalConfirm||'Continue',
      cancelText:trigger.dataset.cmsModalCancel||'Cancel',
      showCancel:trigger.dataset.cmsModalShowCancel==='true',
      allowOutsideClick:false
    });
    if(result.isConfirmed&&trigger.dataset.cmsModalHref)window.location.href=trigger.dataset.cmsModalHref;
  })); // Live content editor preview while typing.
  qa('.cms-form [name]').forEach(field=>field.addEventListener('input',()=> {
    const iframe=q('.live-preview-panel iframe');if(!iframe||!field.name)return;try {
      const doc=iframe.contentDocument;doc?.querySelectorAll('[data-content-key="'+CSS.escape(field.name)+'"]').forEach(el=>el.textContent=field.value);
    } catch(err) {
    }
  }
  )); // Secure logout confirmation.
  qa('[data-logout-confirm]').forEach(link=>link.addEventListener('click',async e=> {
    e.preventDefault();const href=link.getAttribute('href');const result=await confirmDialog( {
      icon:'question',title:'Log out of the administrator?',text:'Your current admin session will be closed.',showCancelButton:true,confirmButtonText:'Yes, log out',cancelButtonText:'Stay signed in',allowOutsideClick:false
    });if(result.isConfirmed)window.location.href=href;
  }
  )); qa('[data-sortable-list]').forEach(list => {
    let dragged = null;
    let pointerDragged = null;

    const cards = () => qa('[data-section-card]', list);
    const sync = () => cards().forEach((card, index) => {
      const value = (index + 1) * 10;
      const input = q('[data-sort-order]', card);
      const label = q('[data-order-label]', card);
      const up = q('[data-move-section="up"]', card);
      const down = q('[data-move-section="down"]', card);
      if (input) input.value = value;
      if (label) label.textContent = value;
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === cards().length - 1;
    });

    const placeAtPointer = (card, clientY) => {
      const elements = document.elementsFromPoint(window.innerWidth / 2, clientY);
      const target = elements.map(element => element.closest?.('[data-section-card]')).find(Boolean);
      if (!target || target === card || target.parentElement !== list) return;
      const rect = target.getBoundingClientRect();
      list.insertBefore(card, clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
      sync();
    };

    cards().forEach(card => {
      card.addEventListener('dragstart', event => {
        if (event.target.closest('input, select, label, button:not(.drag-handle)')) {
          event.preventDefault();
          return;
        }
        dragged = card;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
      });
      card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        dragged = null;
        sync();
      });
      card.addEventListener('dragover', event => {
        event.preventDefault();
        if (!dragged || dragged === card) return;
        const rect = card.getBoundingClientRect();
        list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? card : card.nextSibling);
      });

      qa('[data-move-section]', card).forEach(button => button.addEventListener('click', () => {
        const direction = button.dataset.moveSection;
        const sibling = direction === 'up' ? card.previousElementSibling : card.nextElementSibling;
        if (!sibling) return;
        if (direction === 'up') list.insertBefore(card, sibling);
        else list.insertBefore(sibling, card);
        sync();
        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }));

      const visibility = q('[data-section-visibility]', card);
      visibility?.addEventListener('change', () => {
        const label = q('[data-section-visibility-label]', card);
        if (label) label.textContent = visibility.checked ? 'Visible' : 'Hidden';
      });

      const handle = q('.drag-handle', card);
      handle?.addEventListener('pointerdown', event => {
        if (event.pointerType === 'mouse') return;
        pointerDragged = card;
        card.classList.add('dragging');
        handle.setPointerCapture(event.pointerId);
        event.preventDefault();
      });
      handle?.addEventListener('pointermove', event => {
        if (pointerDragged !== card) return;
        placeAtPointer(card, event.clientY);
        event.preventDefault();
      });
      const finishPointerSort = event => {
        if (pointerDragged !== card) return;
        card.classList.remove('dragging');
        pointerDragged = null;
        if (handle.hasPointerCapture(event.pointerId)) handle.releasePointerCapture(event.pointerId);
        sync();
      };
      handle?.addEventListener('pointerup', finishPointerSort);
      handle?.addEventListener('pointercancel', finishPointerSort);
    });

    sync();
  });
}
)();
// Selects are initialized globally with the local Select2-compatible library.
// Simple progressive disclosure panels used by user/approval forms.
// Social networks: real local-SVG preview before saving.
(()=> {
  const form=document.querySelector('[data-social-form]');
  const preview=document.querySelector('[data-social-preview]');
  if(!form||!preview)return;
  const labels={instagram:'Instagram',facebook:'Facebook',tiktok:'TikTok',youtube:'YouTube',linkedin:'LinkedIn'};
  const render=()=> {
    const size=form.querySelector('[name="social_size"]')?.value||'medium';
    const style=form.querySelector('[name="social_style"]')?.value||'icon_name';
    const location=form.querySelector('[name="social_location"]')?.value||'footer';
    const desktop=form.querySelector('[name="social_show_desktop"]')?.checked;
    const mobile=form.querySelector('[name="social_show_mobile"]')?.checked;
    const rows=[...form.querySelectorAll('[data-social-row]')].map((row,index)=>({
      platform:row.querySelector('[data-social-platform]')?.value||'',
      order:Number(row.querySelector('[data-social-order]')?.value||index*10),
      active:row.querySelector('.social-row-active input')?.checked||false,
    })).filter(row=>row.active).sort((a,b)=>a.order-b.order);
    preview.innerHTML='';
    const meta=document.createElement('div');
    meta.className='social-preview-meta';
    meta.textContent='Location: '+location.replaceAll('_',' ')+' · '+(desktop?'desktop/tablet':'')+(desktop&&mobile?' + ':'')+(mobile?'mobile':'');
    preview.append(meta);
    const list=document.createElement('div');
    list.className='social-preview-links social-preview-'+size+(style==='icon'?' icon-only':'');
    if(!rows.length) {
      const empty=document.createElement('p');
      empty.textContent='Activate at least one complete network to preview it.';
      list.append(empty);
    }
    rows.forEach(row=> {
      const item=document.createElement('span');
      item.className='social-preview-item';
      const icon=document.createElement('span');
      icon.className='social-preview-icon';
      const template=document.querySelector('template[data-social-icon="'+CSS.escape(row.platform)+'"]');
      if(template)icon.append(template.content.cloneNode(true));
      item.append(icon);
      if(style==='icon_name') {
        const name=document.createElement('span');
        name.textContent=labels[row.platform]||row.platform;
        item.append(name);
      }
      list.append(item);
    });
    preview.append(list);
  };
  form.querySelectorAll('[data-social-input],[data-social-setting]').forEach(field=> {
    field.addEventListener('input',render);
    field.addEventListener('change',render);
  });
  render();
})();
document.querySelectorAll('[data-toggle-panel]').forEach(btn=>btn.addEventListener('click',()=> {
  const el=document.getElementById(btn.dataset.togglePanel||'');if(!el)return;el.classList.toggle('is-collapsed');if(!el.classList.contains('is-collapsed'))el.scrollIntoView( {
    behavior:'smooth',block:'start'
  }
  );
}
));
// Last opened wins across every menu and overlay in the admin header.
document.querySelectorAll('.admin-header details').forEach(menu=>menu.addEventListener('toggle',()=> {
  if(!menu.open)return;
  AdminOverlayManager.closeSide();
  AdminOverlayManager.closeAdminFind();
  AdminOverlayManager.closeTopbarMenus(menu);
}
));
// Appearance mini live preview.
(()=> {
  const form=document.querySelector('[data-appearance-form]'),box=document.querySelector('[data-style-preview]');
  if(!form||!box)return;
  const heading=box.querySelector('h2'),para=box.querySelector('p');
  const fontStack=v=>v==='System'?'system-ui, sans-serif':'"'+v+'", sans-serif';
  const update=()=> {
    const g=n=>form.querySelector('[name="'+n+'"]')?.value;if(heading) {
      heading.style.fontFamily=fontStack(g('font_heading_family')||'Manrope');heading.style.fontSize=Math.max(24,Math.min(80,Number(g('font_h1_desktop')||40)))+'px';heading.style.fontWeight=g('heading_weight')||800
    }
    if(para) {
      para.style.fontFamily=fontStack(g('font_body_family')||'DM Sans');para.style.fontSize=Math.max(14,Math.min(24,Number(g('font_body_size')||16)))+'px';para.style.lineHeight=g('line_height_body')||1.7
    }
  }
  ;form.querySelectorAll('input,select').forEach(el=>el.addEventListener('input',update));update()
}
)();
// Quick Find: helps non-technical users jump to the right admin area.
(() => {
  const overlay = document.querySelector('[data-admin-find]');
  const input = document.querySelector('[data-admin-find-input]');
  const results = document.querySelector('[data-admin-find-results]');
  if (!overlay || !input || !results) return;
  const links = [...document.querySelectorAll('.admin-sidebar a[href]')].map((link) => ( {
    href: link.getAttribute('href'), label: (link.textContent || '').replace(/\s+/g, ' ').trim(),
  }
  )); const aliases = {
    appearance: 'theme colors typography fonts banner header navigation menu design', settings: 'logo favicon whatsapp maintenance contact branding', social: 'social networks redes sociales instagram facebook tiktok youtube linkedin links icons footer floating', widget: 'nivo web chat widget floating chat whatsapp burbuja flotante', media: 'images videos files documents upload library', gallery: 'projects case studies cases portfolio photos images', content: 'text titles paragraphs landing copy editor publish draft', users: 'accounts team staff administrators login', roles: 'permissions access security roles', estimates: 'requests quotes customers leads follow up', email: 'smtp graph messages mail',
  }
  ; const render = (query = '') => {
    const term = query.trim().toLowerCase(); const matches = links.filter((item) => {
      const key = (item.href || '').replace('.php', '').toLowerCase(); const searchable = `${item.label} ${key} ${aliases[key] || ''}`.toLowerCase(); return term === '' || searchable.includes(term);
    }
    ); results.innerHTML = ''; if (!matches.length) {
      const empty = document.createElement('div'); empty.className = 'admin-find-empty'; empty.textContent = 'No matching admin area. Try a simpler word.'; results.appendChild(empty); return;
    }
    matches.slice(0, 10).forEach((item) => {
      const link = document.createElement('a'); link.href = item.href; link.innerHTML = `<span>${item.label}</span><b>Open →</b>`; results.appendChild(link);
    }
    );
  }
  ; const open = (trigger = null) => {
    render(''); AdminOverlayManager.openAdminFind(trigger);
  }
  ; const close = (restoreFocus = false) => {
    AdminOverlayManager.closeAdminFind({ restoreFocus });
  }
  ; document.querySelectorAll('[data-admin-find-open]').forEach((button) => {
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-haspopup', 'dialog');
    button.addEventListener('click', () => open(button));
  }
  ); document.querySelectorAll('[data-admin-find-close]').forEach((button) => {
    button.addEventListener('click', () => close(true));
  }
  ); input.addEventListener('input', () => render(input.value)); document.addEventListener('keydown', (event) => {
    const editable = event.target.matches('input, textarea, select, [contenteditable="true"]'); if (event.key === '/' && !editable) {
      event.preventDefault(); open();
    }
    if (event.key === 'Escape' && !overlay.hidden) {
      close(true);
    }
  }
  );
}
)();
// Warn users before leaving a form with unsaved changes.
(() => {
  const forms = [...document.querySelectorAll('[data-unsaved-form]')]; if (!forms.length) return; let dirty = false; forms.forEach((form) => {
    const markDirty = () => {
      dirty = true; form.classList.add('has-unsaved-changes');
    }
    ; form.querySelectorAll('input, textarea, select').forEach((field) => {
      if (field.type === 'hidden') return; field.addEventListener('input', markDirty); field.addEventListener('change', markDirty);
    }
    ); form.addEventListener('submit', () => {
      dirty = false; form.classList.remove('has-unsaved-changes');
    }
    );
  }
  ); window.addEventListener('beforeunload', (event) => {
    if (!dirty) return; event.preventDefault(); event.returnValue = '';
  }
  );
}
)();
// Keep color picker and HEX input synchronized in Appearance.
(() => {
  document.querySelectorAll('.color-input-wrap').forEach((wrap) => {
    const picker = wrap.querySelector('[data-color-picker]'); const text = wrap.querySelector('[data-color-text]'); if (!picker || !text) return; picker.addEventListener('input', () => {
      text.value = picker.value.toLowerCase(); text.dispatchEvent(new Event('input', {
        bubbles: true
      }
      ));
    }
    ); text.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
        picker.value = text.value;
      }
    }
    );
  }
  );
}
)();

/* ==========================================================
   PHASE 1 - Focused landing page section editor
   ========================================================== */
(() => {
  const switcher = document.querySelector('[data-section-switcher]');
  if (!switcher) return;

  const tabs = Array.from(switcher.querySelectorAll('[data-section-tab]'));
  const contentEditors = Array.from(document.querySelectorAll('[data-content-editor]'));
  const moduleEditors = Array.from(document.querySelectorAll('[data-module-editor]'));
  const title = document.querySelector('[data-active-section-title]');
  const description = document.querySelector('[data-active-section-description]');
  const previewName = document.querySelector('[data-preview-section-name]');
  const previewFrame = document.querySelector('[data-section-preview-frame]');
  const previewOpen = document.querySelector('[data-preview-open]');
  const returnInputs = Array.from(document.querySelectorAll('[data-return-section], [data-return-section-copy]'));
  const previousButton = document.querySelector('[data-section-previous]');
  const nextButton = document.querySelector('[data-section-next]');
  const savebar = document.querySelector('[data-content-savebar]');
  const aboutArtworkPanel = document.querySelector('[data-about-artwork-panel]');
  const deviceButtons = Array.from(document.querySelectorAll('[data-preview-device]'));
  const previewStage = document.querySelector('[data-preview-stage]');

  let activeKey = tabs.find(tab => tab.classList.contains('is-active'))?.dataset.sectionTab || tabs[0]?.dataset.sectionTab || 'home';

  const activeTabIndex = () => tabs.findIndex(tab => tab.dataset.sectionTab === activeKey);

  const focusPreviewSection = (anchor) => {
    if (!previewFrame || !anchor) return;

    const base = '../?preview=1&draft=1#' + encodeURIComponent(anchor);
    previewFrame.src = base;
    if (previewOpen) previewOpen.href = base;
  };

  const selectSection = (key, updateUrl = true) => {
    const tab = tabs.find(item => item.dataset.sectionTab === key);
    if (!tab) return;

    // Keep the administrator exactly where they are while changing sections.
    // Different editor heights must not make the page jump and lose context.
    const preservedScrollY = window.scrollY;

    activeKey = key;

    tabs.forEach(item => {
      const isActive = item === tab;
      item.classList.toggle('is-active', isActive);
      item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    contentEditors.forEach(editor => {
      const isActive = editor.dataset.contentEditor === key;
      editor.hidden = !isActive;
      editor.classList.toggle('is-active', isActive);
    });

    moduleEditors.forEach(editor => {
      const isActive = editor.dataset.moduleEditor === key;
      editor.hidden = !isActive;
      editor.classList.toggle('is-active', isActive);
    });

    const isEditableContent = contentEditors.some(editor => editor.dataset.contentEditor === key);
    if (savebar) savebar.hidden = !isEditableContent;
    if (aboutArtworkPanel) aboutArtworkPanel.hidden = key !== 'about';

    const sectionTitle = tab.dataset.sectionTitle || '';
    const sectionDescription = tab.dataset.sectionDescription || '';
    const sectionAnchor = tab.dataset.sectionAnchor || key;

    if (title) title.textContent = sectionTitle;
    if (description) description.textContent = sectionDescription;
    if (previewName) previewName.textContent = sectionTitle;
    returnInputs.forEach(input => { input.value = key; });

    focusPreviewSection(sectionAnchor);

    if (updateUrl && window.history?.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.set('section', key);
      window.history.replaceState({}, '', url.toString());
    }

    requestAnimationFrame(() => {
      window.scrollTo({ top: preservedScrollY, left: 0, behavior: 'auto' });
    });
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', () => selectSection(tab.dataset.sectionTab || 'home'));
  });

  previousButton?.addEventListener('click', () => {
    const index = activeTabIndex();
    const nextIndex = index <= 0 ? tabs.length - 1 : index - 1;
    selectSection(tabs[nextIndex].dataset.sectionTab || 'home');
  });

  nextButton?.addEventListener('click', () => {
    const index = activeTabIndex();
    const nextIndex = index >= tabs.length - 1 ? 0 : index + 1;
    selectSection(tabs[nextIndex].dataset.sectionTab || 'home');
  });

  deviceButtons.forEach(button => {
    button.addEventListener('click', () => {
      const device = button.dataset.previewDevice || 'desktop';
      deviceButtons.forEach(item => {
        const isActive = item === button;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      if (previewStage) previewStage.dataset.previewStage = device;
    });
  });

  document.querySelectorAll('[data-section-content-form] input, [data-section-content-form] textarea').forEach(field => {
    field.addEventListener('input', () => {
      const state = document.querySelector('[data-section-state="' + CSS.escape(activeKey) + '"]');
      if (!state) return;
      state.textContent = 'Unsaved';
      state.classList.remove('published', 'managed');
      state.classList.add('draft');
    });
  });

  selectSection(activeKey, false);
})();

// Copy one encrypted SMTP or Graph connection to several email purposes.
(() => {
  const modal = document.querySelector('[data-email-copy-modal]');
  const form = modal?.querySelector('[data-email-copy-form]');
  if (!modal || !form) return;

  const sourceId = form.querySelector('[data-email-copy-source-id]');
  const sourceMethod = form.querySelector('[data-email-copy-method]');
  const sourcePurpose = form.querySelector('[data-email-copy-purpose]');
  const sourceEmail = form.querySelector('[data-email-copy-email]');
  const activateCopies = form.querySelector('[name="activate_copies"]');
  const targets = [...form.querySelectorAll('[data-email-copy-target]')];
  let sourceType = 0;
  let returnFocus = null;

  const setAllTargets = (checked) => {
    targets.forEach(target => {
      const input = target.querySelector('input[type="checkbox"]');
      if (!input || input.disabled) return;
      input.checked = checked;
    });
  };

  const close = () => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('email-copy-modal-open');
    returnFocus?.focus();
  };

  const syncConfirmation = () => {
    form.dataset.swalText = activateCopies?.checked
      ? 'Selected purposes will use this same sender connection. Existing active connections for those purposes will become inactive.'
      : 'The copied connections will be saved inactive. Existing active connections will remain unchanged.';
  };

  document.querySelectorAll('[data-email-copy-open]').forEach(button => {
    button.addEventListener('click', () => {
      returnFocus = button;
      sourceType = Number(button.dataset.sourceType || 0);
      sourceId.value = button.dataset.sourceId || '';
      sourceMethod.textContent = button.dataset.sourceMethod || 'SMTP';
      sourcePurpose.textContent = button.dataset.sourcePurpose || 'Source purpose';
      sourceEmail.textContent = button.dataset.sourceEmail || '';
      if (activateCopies) activateCopies.checked = true;

      targets.forEach(target => {
        const input = target.querySelector('input[type="checkbox"]');
        const isSource = Number(target.dataset.typeId || 0) === sourceType;
        target.classList.toggle('is-source', isSource);
        if (input) {
          input.disabled = isSource;
          input.checked = false;
        }
      });

      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('email-copy-modal-open');
      syncConfirmation();
      window.setTimeout(() => {
        form.querySelector('input[type="checkbox"]:not(:disabled)')?.focus();
      }, 30);
    });
  });

  modal.querySelectorAll('[data-email-copy-close]').forEach(button => {
    button.addEventListener('click', close);
  });
  modal.querySelector('[data-email-copy-select-all]')?.addEventListener('click', () => setAllTargets(true));
  modal.querySelector('[data-email-copy-clear]')?.addEventListener('click', () => setAllTargets(false));
  activateCopies?.addEventListener('change', syncConfirmation);

  form.addEventListener('submit', event => {
    if (!sourceId.value || Number(sourceId.value) < 1) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (window.showNotify) showNotify('The source email configuration could not be identified. Close this window and try again.', 'error');
      return;
    }

    const hasTarget = targets.some(target => {
      const input = target.querySelector('input[type="checkbox"]');
      return input && !input.disabled && input.checked;
    });
    if (hasTarget) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (window.showNotify) showNotify('Select at least one destination purpose.', 'warning');
  }, true);

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
})();

// Force a fresh server capability request and show immediate feedback.
(() => {
  const form = document.querySelector('[data-server-recheck]');
  if (!form) return;

  const button = form.querySelector('[data-recheck-button]');
  const label = form.querySelector('[data-recheck-label]');
  const token = form.querySelector('[data-recheck-token]');

  form.addEventListener('submit', () => {
    if (token) token.value = String(Date.now());
    if (button) {
      button.disabled = true;
      button.classList.add('is-checking');
      button.setAttribute('aria-busy', 'true');
    }
    if (label) label.textContent = 'Checking server...';
  });
})();


// v1.0.41 — reusable rich-text editor for content-oriented textareas.
(() => {
  const editors = [...document.querySelectorAll('textarea[data-rich-text]')];
  const cleanHtml = (html) => String(html || '')
    .replace(/<(?!\/?(?:p|br|strong|b|em|i|u|ul|ol|li|blockquote|h2|h3|h4)(?:\s|>|\/))[^>]*>/gi, '');

  editors.forEach(textarea => {
    if (textarea.dataset.richTextReady === '1') return;
    textarea.dataset.richTextReady = '1';

    const shell = document.createElement('div');
    shell.className = 'cms-rich-text';
    shell.innerHTML = `
      <div class="cms-rich-text-toolbar" role="toolbar" aria-label="Formato de texto">
        <button type="button" data-cms-rich-command="bold" title="Negrita"><strong>B</strong></button>
        <button type="button" data-cms-rich-command="italic" title="Cursiva"><em>I</em></button>
        <button type="button" data-cms-rich-command="underline" title="Subrayado"><u>U</u></button>
        <button type="button" data-cms-rich-command="insertUnorderedList" title="Lista con viñetas">• Lista</button>
        <button type="button" data-cms-rich-command="insertOrderedList" title="Lista numerada">1. Lista</button>
        <button type="button" data-cms-rich-command="formatBlock" data-cms-rich-value="blockquote" title="Cita">❝ Cita</button>
        <button type="button" data-cms-rich-command="removeFormat" title="Quitar formato">Limpiar</button>
      </div>
      <div class="cms-rich-text-content" contenteditable="true" role="textbox" aria-multiline="true"></div>
    `;

    const content = shell.querySelector('.cms-rich-text-content');
    content.innerHTML = textarea.value || '';
    textarea.hidden = true;
    textarea.insertAdjacentElement('afterend', shell);

    const sync = () => {
      textarea.value = cleanHtml(content.innerHTML.trim());
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    shell.querySelectorAll('[data-cms-rich-command]').forEach(button => {
      button.addEventListener('click', () => {
        content.focus();
        document.execCommand(button.dataset.cmsRichCommand || '', false, button.dataset.cmsRichValue || null);
        sync();
      });
    });

    content.addEventListener('input', sync);
    content.addEventListener('blur', sync);
    content.addEventListener('paste', event => {
      event.preventDefault();
      const text = event.clipboardData?.getData('text/plain') || '';
      document.execCommand('insertText', false, text);
      sync();
    });

    textarea.form?.addEventListener('submit', sync);
  });
})();

// v1.0.43 · Persist sidebar position reliably across admin navigation.
(()=>{
  const sidebar=document.querySelector('[data-sidebar]');
  if(!sidebar)return;
  const key='izzyAdminSidebarScrollTop:v2';
  const save=()=>{
    try{ localStorage.setItem(key,String(Math.max(0,sidebar.scrollTop||0))); }catch(_e){}
  };
  const restore=()=>{
    try{
      const saved=Number(localStorage.getItem(key)||0);
      if(!Number.isFinite(saved)||saved<=0)return false;
      sidebar.scrollTop=saved;
      return true;
    }catch(_e){return false;}
  };

  // Re-apply after layout/fonts finish so active states never push the sidebar back to top.
  const hadSaved=restore();
  requestAnimationFrame(()=>restore());
  setTimeout(()=>restore(),80);
  setTimeout(()=>restore(),260);

  let frame=0;
  sidebar.addEventListener('scroll',()=>{
    cancelAnimationFrame(frame);
    frame=requestAnimationFrame(save);
  },{passive:true});

  sidebar.querySelectorAll('a[href]').forEach(a=>{
    a.addEventListener('pointerdown',save,{passive:true});
    a.addEventListener('click',save);
  });
  window.addEventListener('pagehide',save);
  window.addEventListener('beforeunload',save);

  // On a first visit only, reveal the active entry without changing future remembered positions.
  if(!hadSaved){
    const active=sidebar.querySelector('a.active');
    if(active) setTimeout(()=>active.scrollIntoView({block:'nearest'}),30);
  }
})();

// v1.0.42 · SEO search preview updates while the user types.
(()=>{
  const title=document.querySelector('[data-seo-title]');
  const desc=document.querySelector('[data-seo-description]');
  const titleOut=document.querySelector('[data-seo-preview-title]');
  const descOut=document.querySelector('[data-seo-preview-description]');
  if(!title||!desc||!titleOut||!descOut)return;
  const titleCount=document.querySelector('[data-seo-title-count]');
  const descCount=document.querySelector('[data-seo-description-count]');
  const update=()=>{
    const titleValue=title.value.trim();
    const descValue=desc.value.trim();
    titleOut.textContent=titleValue||'IZZY | Sistema de gestión empresarial';
    descOut.textContent=descValue||'Agrega una descripción clara para que los usuarios entiendan qué ofrece tu sitio.';
    if(titleCount) titleCount.textContent=`${titleValue.length}/70`;
    if(descCount) descCount.textContent=`${descValue.length}/180`;
  };
  title.addEventListener('input',update);desc.addEventListener('input',update);update();
})();








// v1.0.68 · Persistent fullscreen shell.
// The fullscreen element is a same-origin iframe, so normal admin navigation
// occurs inside that iframe and does not cancel browser fullscreen.
(()=>{
  const FRAME_NAME='izzy-admin-fullscreen';
  const FRAME_CLASS='admin-fullscreen-frame';
  const toggles=[...document.querySelectorAll('[data-admin-expand-toggle]')];

  const inFullscreenShell=()=>{
    try{
      return window.self!==window.top && window.name===FRAME_NAME;
    }catch(_e){
      return false;
    }
  };

  const setButtonState=(active)=>{
    toggles.forEach(btn=>{
      btn.setAttribute('aria-pressed',active?'true':'false');
      btn.setAttribute('title',active?'Salir de pantalla completa':'Pantalla completa');
      btn.setAttribute('aria-label',active?'Salir de pantalla completa':'Pantalla completa');
      btn.querySelectorAll('[data-admin-expand-label]').forEach(label=>{
        label.textContent=active?'Restaurar':'Expandir';
      });
    });
  };

  // We are already inside the fullscreen navigation shell.
  if(inFullscreenShell()){
    document.body.classList.add('admin-fullscreen-child');
    setButtonState(true);

    toggles.forEach(btn=>btn.addEventListener('click',async()=>{
      try{
        const topDoc=window.top.document;
        if(topDoc.fullscreenElement && topDoc.exitFullscreen){
          await topDoc.exitFullscreen();
        }else if(topDoc.webkitFullscreenElement && topDoc.webkitExitFullscreen){
          topDoc.webkitExitFullscreen();
        }
      }catch(_e){
        // Parent will also expose a close hook as fallback.
        try{ window.top.postMessage({type:'izzy-admin-fullscreen-exit'},window.location.origin); }catch(_err){}
      }
    }));
    return;
  }

  let shell=null;

  const cleanup=()=>{
    document.body.classList.remove('admin-fullscreen-host');
    shell?.remove();
    shell=null;
    setButtonState(false);
  };

  const enter=async()=>{
    if(shell || document.fullscreenElement)return;

    shell=document.createElement('iframe');
    shell.className=FRAME_CLASS;
    shell.name=FRAME_NAME;
    shell.src=window.location.href;
    shell.setAttribute('allow','fullscreen');
    shell.setAttribute('allowfullscreen','');
    shell.setAttribute('title','IZZY CMS · Pantalla completa');
    shell.setAttribute('aria-label','IZZY CMS · Pantalla completa');
    document.body.appendChild(shell);
    document.body.classList.add('admin-fullscreen-host');

    try{
      if(shell.requestFullscreen){
        await shell.requestFullscreen({navigationUI:'hide'});
      }else if(shell.webkitRequestFullscreen){
        shell.webkitRequestFullscreen();
      }else{
        throw new Error('Fullscreen API unavailable');
      }
      setButtonState(true);
    }catch(_e){
      cleanup();
      if(window.showNotify){
        window.showNotify('El navegador no permitió abrir el panel en pantalla completa.','warning');
      }
    }
  };

  toggles.forEach(btn=>btn.addEventListener('click',enter));

  const onFullscreenChange=()=>{
    const active=Boolean(document.fullscreenElement||document.webkitFullscreenElement);
    if(!active)cleanup();
  };

  document.addEventListener('fullscreenchange',onFullscreenChange);
  document.addEventListener('webkitfullscreenchange',onFullscreenChange);

  window.addEventListener('message',async(event)=>{
    if(event.origin!==window.location.origin || event.data?.type!=='izzy-admin-fullscreen-exit')return;
    try{
      if(document.fullscreenElement && document.exitFullscreen){
        await document.exitFullscreen();
      }else if(document.webkitFullscreenElement && document.webkitExitFullscreen){
        document.webkitExitFullscreen();
      }else{
        cleanup();
      }
    }catch(_e){
      cleanup();
    }
  });

  setButtonState(false);
})();
