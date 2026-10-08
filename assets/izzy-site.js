(()=>{
 const menu=document.querySelector('[data-menu]'),toggle=document.querySelector('[data-menu-toggle]');
 toggle?.addEventListener('click',()=>{const open=menu.classList.toggle('open');toggle.setAttribute('aria-expanded',open?'true':'false')});
 document.querySelectorAll('[data-menu] a').forEach(a=>a.addEventListener('click',()=>{menu?.classList.remove('open');toggle?.setAttribute('aria-expanded','false')}));

 const ro=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');ro.unobserve(e.target)}}),{threshold:.08});
 document.querySelectorAll('.reveal').forEach(el=>ro.observe(el));

 const rotating=document.querySelector('[data-rotating-word]');
 if(rotating){
   const words=(rotating.dataset.words||'').split('|').map(v=>v.trim()).filter(Boolean);
   let i=0;
   if(words.length>1){setInterval(()=>{rotating.classList.add('switching');setTimeout(()=>{i=(i+1)%words.length;rotating.textContent=words[i];rotating.classList.remove('switching')},220)},2200)}
 }

 const planToggle=document.querySelector('[data-toggle-plans]');
 planToggle?.addEventListener('click',()=>{
   const expanded=planToggle.getAttribute('aria-expanded')==='true';
   document.querySelectorAll('.plan-extra').forEach(card=>card.hidden=expanded);
   planToggle.setAttribute('aria-expanded',expanded?'false':'true');
   const label=planToggle.querySelector('[data-toggle-plans-label]');
   if(label)label.textContent=expanded?'Ver todos los planes':'Mostrar menos planes';
   if(expanded)document.getElementById('planes')?.scrollIntoView({behavior:'smooth',block:'start'});
   requestAnimationFrame(()=>window.IZZY_syncPlanHeights?.());
 });

 const sectionRoot=document.querySelector('[data-izzy-section-root]');
 const sectionConfig=window.IZZY_SECTION_CONFIG||{};
 const configuredOrder=Array.isArray(sectionConfig.order)?sectionConfig.order:[];
 const configuredActive=sectionConfig.active&&typeof sectionConfig.active==='object'?sectionConfig.active:{};

 // Apply the exact order saved in Admin > Section Manager to the public DOM.
 // Companion blocks (such as the strip below Inicio) travel with their owner section.
 if(sectionRoot&&configuredOrder.length){
   configuredOrder.forEach(key=>{
     const managed=sectionRoot.querySelector('[data-managed-section="'+CSS.escape(key)+'"]');
     if(!managed)return;
     const enabled=configuredActive[key]!==false;
     managed.hidden=!enabled;
     sectionRoot.appendChild(managed);
     sectionRoot.querySelectorAll('[data-section-companion="'+CSS.escape(key)+'"]')
       .forEach(companion=>{companion.hidden=!enabled;sectionRoot.appendChild(companion)});
   });
 }

 const navLinks=[...document.querySelectorAll('.izzy-menu a[href^="#"]')].filter(a=>a.getAttribute('href')!=='#');
 const sectionPairs=navLinks.map(link=>{
   const section=document.querySelector(link.getAttribute('href'));
   return section&&!section.hidden?{link,section}:null;
 }).filter(Boolean);
 const setActive=id=>navLinks.forEach(a=>{
   const active=a.getAttribute('href')==='#'+id;
   a.classList.toggle('active',active);
   if(active)a.setAttribute('aria-current','location');
   else a.removeAttribute('aria-current');
 });
 let spyFrame=0;
 const syncNavigationState=()=>{
   spyFrame=0;
   if(!sectionPairs.length)return;
   const header=document.querySelector('.izzy-header');
   const marker=window.scrollY+(header?.offsetHeight||0)+Math.max(24,Math.min(180,window.innerHeight*.24));
   let current=sectionPairs[0];
   for(const pair of sectionPairs){
     const top=pair.section.getBoundingClientRect().top+window.scrollY;
     if(top<=marker+1)current=pair;
     else break;
   }
   if(window.innerHeight+window.scrollY>=document.documentElement.scrollHeight-4){
     current=sectionPairs[sectionPairs.length-1];
   }
   setActive(current.section.id);
 };
 const requestNavigationSync=()=>{
   if(spyFrame)return;
   spyFrame=requestAnimationFrame(syncNavigationState);
 };
 if(sectionPairs.length){
   window.addEventListener('scroll',requestNavigationSync,{passive:true});
   window.addEventListener('resize',requestNavigationSync,{passive:true});
   window.addEventListener('orientationchange',requestNavigationSync,{passive:true});
   window.addEventListener('load',requestNavigationSync,{once:true});
   syncNavigationState();
 }

 // v1.0.92 · Todas las imágenes ampliables muestran una lupa real y funcional.
 // La imagen conserva cursor zoom-in; al entrar sobre la lupa el cursor es pointer.
 const zoomButtonSvg='<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16.2 16.2 4.3 4.3"></path></svg>';
 document.querySelectorAll('img[data-zoom]').forEach(img=>{
   const host=img.parentElement;
   if(!host)return;
   host.classList.add('zoomable-media-host');
   if(host.matches('button,a')||host.querySelector(':scope > .media-zoom-button'))return;
   const button=document.createElement('button');
   button.type='button';
   button.className='media-zoom-button';
   button.setAttribute('data-no-action-icon','');
   button.setAttribute('aria-label','Ampliar '+(img.getAttribute('alt')||'imagen'));
   button.innerHTML=zoomButtonSvg;
   button.addEventListener('click',event=>{
     event.preventDefault();
     event.stopPropagation();
     img.dispatchEvent(new MouseEvent('click',{bubbles:true}));
   });
   host.appendChild(button);
 });

 const lb=document.querySelector('[data-lightbox]'),lbImg=lb?.querySelector('img');
 document.querySelectorAll('[data-zoom]').forEach(el=>el.addEventListener('click',()=>{if(!lb||!lbImg)return;lbImg.src=el.getAttribute('data-zoom')||el.getAttribute('src')||'';lb.classList.add('open');lb.setAttribute('aria-hidden','false')}));
 document.querySelectorAll('[data-zoom][role="button"]').forEach(el=>el.addEventListener('keydown',event=>{
   if(event.key!=='Enter'&&event.key!==' ')return;
   event.preventDefault();
   el.click();
 }));
 const close=()=>{lb?.classList.remove('open');lb?.setAttribute('aria-hidden','true');if(lbImg)lbImg.src=''};
 lb?.querySelector('button')?.addEventListener('click',close);
 lb?.addEventListener('click',e=>{if(e.target===lb)close()});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});

 const form=document.getElementById('estimateForm'),toast=document.getElementById('toast');
 const show=(m,type='info')=>{if(window.showNotify){window.showNotify(m,type);return}if(!toast)return;toast.textContent=m;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),4800)};

 const turnstileContainer=form?.querySelector('[data-turnstile-container]');
 let turnstileWidgetId=null;
 const initTurnstile=()=>{
   if(!turnstileContainer||turnstileWidgetId!==null||!window.turnstile||typeof window.turnstile.render!=='function')return;
   const sitekey=turnstileContainer.dataset.sitekey||'';
   if(!sitekey)return;
   try{
     turnstileWidgetId=window.turnstile.render(turnstileContainer,{
       sitekey,
       action:'contact_inquiry',
       appearance:'interaction-only',
       theme:'auto',
       size:'flexible',
       language:turnstileContainer.dataset.language||'auto',
       'refresh-expired':'auto',
       'error-callback':()=>show('No pudimos verificar el envío. Intenta nuevamente.','warning'),
       'timeout-callback':()=>{if(turnstileWidgetId!==null)window.turnstile?.reset?.(turnstileWidgetId)}
     });
   }catch(_){}
 };
 const resetTurnstile=()=>{
   if(turnstileWidgetId!==null&&window.turnstile&&typeof window.turnstile.reset==='function'){
     try{window.turnstile.reset(turnstileWidgetId)}catch(_){}
   }
 };
 window.izzyTurnstileReady=initTurnstile;
 if(turnstileContainer&&window.turnstile)initTurnstile();

 const emailField=form?.querySelector('[data-email-validation-field]');
 const emailInput=emailField?.querySelector('[data-email-input]');
 const emailState=emailField?.querySelector('[data-email-state]');
 const emailMessage=emailField?.querySelector('[data-email-message]');
 const emailSuggestion=emailField?.querySelector('[data-email-suggestion]');
 let emailTimer=0;
 let emailSequence=0;
 let emailValidatedValue='';
 let emailValidationStatus='idle';

 const setEmailState=(status,message='',suggestion='')=>{
   if(!emailState)return;
   emailValidationStatus=status;
   emailState.classList.remove('is-checking','is-valid','is-invalid','is-suggestion','is-warning');
   if(status==='idle'){
     emailState.hidden=true;
     if(emailMessage)emailMessage.textContent='';
     if(emailSuggestion){emailSuggestion.hidden=true;emailSuggestion.textContent='';emailSuggestion.dataset.value=''}
     return;
   }
   emailState.hidden=false;
   if(status==='checking')emailState.classList.add('is-checking');
   if(status==='valid')emailState.classList.add('is-valid');
   if(status==='invalid')emailState.classList.add('is-invalid');
   if(status==='suggestion')emailState.classList.add('is-suggestion');
   if(status==='warning')emailState.classList.add('is-warning');
   if(emailMessage)emailMessage.textContent=message;
   if(emailSuggestion){
     if(suggestion){
       emailSuggestion.hidden=false;
       emailSuggestion.dataset.value=suggestion;
       emailSuggestion.textContent=`Usar ${suggestion}`;
     }else{
       emailSuggestion.hidden=true;
       emailSuggestion.textContent='';
       emailSuggestion.dataset.value='';
     }
   }
 };

 const emailLooksValid=value=>{
   const v=(value||'').trim();
   if(!v||/\s/.test(v)||v.length>180)return false;
   const parts=v.split('@');
   if(parts.length!==2||!parts[0]||!parts[1]||!parts[1].includes('.'))return false;
   if(parts[0].startsWith('.')||parts[0].endsWith('.')||parts[0].includes('..')||parts[1].includes('..'))return false;
   return /^[^@\s]+@[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,63}$/.test(v);
 };

 const validateEmail=async(force=false)=>{
   if(!emailInput)return true;
   const value=emailInput.value.trim();
   if(!value){
     emailValidatedValue='';
     setEmailState('idle');
     return false;
   }
   if(!emailLooksValid(value)){
     emailValidatedValue=value;
     setEmailState('invalid','Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com');
     return false;
   }
   if(!force&&emailValidatedValue===value&&emailValidationStatus==='valid')return true;
   const requestId=++emailSequence;
   setEmailState('checking','Validando correo...');
   try{
     const body=new URLSearchParams({email:value});
     const response=await fetch('email-validate.php',{
       method:'POST',
       body,
       headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}
     });
     const result=await response.json().catch(()=>({}));
     if(requestId!==emailSequence)return false;
     emailValidatedValue=value;
     if(result.ok){
       setEmailState('valid',result.message||'Correo válido');
       return true;
     }
     if(result.status==='suggestion'&&result.suggestion){
       setEmailState('suggestion',result.message||`¿Quisiste escribir ${result.suggestion}?`,result.suggestion);
       return false;
     }
     if(result.status==='rate_limit'){
       setEmailState('warning',result.message||'Espera un momento antes de volver a validar el correo.');
       return true;
     }
     setEmailState('invalid',result.message||'Revisa el correo electrónico e inténtalo nuevamente.');
     return false;
   }catch(error){
     if(requestId!==emailSequence)return false;
     emailValidatedValue=value;
     setEmailState('warning','No pudimos completar la validación avanzada ahora. Puedes continuar.');
     return true;
   }
 };

 emailInput?.addEventListener('input',()=>{
   window.clearTimeout(emailTimer);
   emailValidatedValue='';
   const value=emailInput.value.trim();
   if(!value){setEmailState('idle');return}
   if(!emailLooksValid(value)){
     setEmailState('invalid','Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com');
     return;
   }
   setEmailState('checking','Validando correo...');
   emailTimer=window.setTimeout(()=>validateEmail(),650);
 });
 emailInput?.addEventListener('blur',()=>{
   window.clearTimeout(emailTimer);
   if(emailInput.value.trim())validateEmail();
 });
 emailSuggestion?.addEventListener('click',()=>{
   const suggestion=emailSuggestion.dataset.value||'';
   if(!suggestion||!emailInput)return;
   emailInput.value=suggestion;
   emailValidatedValue='';
   emailInput.focus();
   validateEmail(true);
 });

 const richRoot=form?.querySelector('[data-public-rich-editor]');
 const richContent=richRoot?.querySelector('[data-rich-content]');
 const richValue=richRoot?.querySelector('[data-rich-value]');
 const syncRich=()=>{
   if(!richContent||!richValue)return '';
   const html=richContent.innerHTML
     .replace(/<div><br><\/div>/gi,'<br>')
     .replace(/<div>/gi,'<p>')
     .replace(/<\/div>/gi,'</p>')
     .trim();
   richValue.value=html;
   return (richContent.innerText||'').replace(/\u00a0/g,' ').trim();
 };
 richRoot?.querySelectorAll('[data-rich-command]').forEach(btn=>btn.addEventListener('click',()=>{
   richContent?.focus();
   document.execCommand(btn.dataset.richCommand,false,null);
   syncRich();
 }));
 richRoot?.querySelector('[data-rich-clear]')?.addEventListener('click',()=>{
   if(!richContent)return;
   richContent.innerHTML='';
   syncRich();
   richContent.focus();
 });
 richContent?.addEventListener('input',syncRich);
 richContent?.addEventListener('blur',syncRich);
 richContent?.addEventListener('paste',()=>{
   window.setTimeout(syncRich,0);
 });

 form?.addEventListener('reset',()=>{
   window.setTimeout(()=>{
     if(richContent)richContent.innerHTML='';
     if(richValue)richValue.value='';
     emailValidatedValue='';
     setEmailState('idle');
   },0);
 });

 form?.addEventListener('submit',async e=>{
   e.preventDefault();
   const richText=syncRich();
   if(!form.checkValidity()){form.reportValidity();return}
   const emailReady=await validateEmail(true);
   if(!emailReady){
     emailInput?.focus();
     return;
   }
   if(richText.length<12){
     show('Cuéntanos un poco más sobre tu negocio y lo que necesitas.','warning');
     richContent?.focus();
     return;
   }
   const btn=form.querySelector('button[type=submit]');
   const old=btn.innerHTML;
   btn.disabled=true;
   btn.textContent='Enviando...';
   try{
     const r=await fetch('estimate-submit.php',{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}});
     const j=await r.json().catch(()=>({}));
     if(!r.ok||!j.ok){
       const error=new Error(j.message||'No se pudo enviar la solicitud.');
       if(j.field==='email')error.emailResult=j;
       throw error;
     }
     show(j.message||'Solicitud recibida. Te contactaremos pronto.','success');
     form.reset();
     resetTurnstile();
   }catch(err){
     if(err&&err.emailResult){
       const result=err.emailResult;
       if(result.code==='suggestion'&&result.suggestion){
         setEmailState('suggestion',result.message||`¿Quisiste escribir ${result.suggestion}?`,result.suggestion);
       }else{
         setEmailState('invalid',result.message||'Revisa el correo electrónico e inténtalo nuevamente.');
       }
       emailInput?.focus();
     }
     show(err.message||'No se pudo enviar. Intenta por WhatsApp.','error')
   }finally{
     btn.disabled=false;
     btn.innerHTML=old
   }
 });
})();

// v1.0.42 · NIVO Web Chat floating panel.
(()=>{
  const root=document.querySelector('[data-nivo-widget]');
  if(!root)return;
  const panel=root.querySelector('[data-nivo-panel]');
  const toggle=root.querySelector('[data-nivo-toggle]');
  const close=root.querySelector('[data-nivo-close]');
  const setOpen=(open)=>{if(panel)panel.hidden=!open;toggle?.setAttribute('aria-expanded',String(open));};
  toggle?.addEventListener('click',()=>setOpen(panel?.hidden!==false));
  close?.addEventListener('click',()=>setOpen(false));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&panel&&!panel.hidden)setOpen(false);});
})();


// v1.0.46 · Equalize every visible plan card AND its internal content rows.
(()=>{
  const grid=document.querySelector('[data-plan-grid]');
  if(!grid)return;

  const rowSelectors=[
    '.plan-topline',
    '.plan-promo-media',
    '.plan-head',
    '.plan-price-wrap',
    '.plan-value-banner',
    '.plan-feature-grid',
    '.plan-compatibility',
    '.plan-cta-box'
  ];

  const resetCard=(card)=>{
    card.style.height='';
    card.style.minHeight='';
    rowSelectors.forEach(sel=>{
      const el=card.querySelector(sel);
      if(el){el.style.height='';el.style.minHeight='';}
    });
  };

  const sync=()=>{
    const cards=[...grid.querySelectorAll('.plan-card')].filter(card=>!card.hidden);
    cards.forEach(resetCard);

    if(window.innerWidth<=720 || cards.length<2)return;

    requestAnimationFrame(()=>{
      // Align corresponding internal blocks across all cards.
      rowSelectors.forEach(sel=>{
        const els=cards.map(card=>card.querySelector(sel)).filter(Boolean);
        if(!els.length)return;
        const max=Math.max(...els.map(el=>Math.ceil(el.getBoundingClientRect().height)));
        els.forEach(el=>{el.style.minHeight=max+'px';});
      });

      // After internal rows are aligned, force an identical outer card height.
      requestAnimationFrame(()=>{
        const maxCard=Math.max(...cards.map(card=>Math.ceil(card.getBoundingClientRect().height)));
        cards.forEach(card=>{
          card.style.height=maxCard+'px';
          card.style.minHeight=maxCard+'px';
        });
      });
    });
  };

  window.IZZY_syncPlanHeights=sync;

  let resizeTimer=0;
  window.addEventListener('resize',()=>{
    clearTimeout(resizeTimer);
    resizeTimer=setTimeout(sync,140);
  },{passive:true});

  window.addEventListener('load',sync,{once:true});
  document.fonts?.ready?.then(sync).catch?.(()=>{});

  grid.querySelectorAll('img').forEach(img=>{
    if(!img.complete)img.addEventListener('load',sync,{once:true});
  });

  // Covers late layout changes from fonts / browser paint / plan expansion.
  setTimeout(sync,60);
  setTimeout(sync,280);
  setTimeout(sync,700);
})();


// v1.0.51 · Ver pantalla completa abre la captura real del POS en el lightbox.
document.querySelectorAll('[data-zoom-trigger]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const src=btn.getAttribute('data-zoom-trigger');
    if(!src)return;
    const target=document.querySelector(`[data-zoom="${CSS.escape(src)}"]`);
    if(target){
      target.dispatchEvent(new MouseEvent('click',{bubbles:true}));
      return;
    }
    const img=document.querySelector(`img[src="${CSS.escape(src)}"]`);
    if(img) img.dispatchEvent(new MouseEvent('click',{bubbles:true}));
  });
});
