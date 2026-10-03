const header=document.querySelector('.site-header'),menuBtn=document.querySelector('.menu-btn'),nav=document.querySelector('.main-nav'),progress=document.querySelector('.scroll-progress span');
const mobileMenuMedia=window.matchMedia('(max-width:980px)');
let publicMenuOpen=false;
function updateMobileMenuOffset() {
  if(!header||!nav||!mobileMenuMedia.matches)return;
  nav.style.setProperty('--mobile-menu-top',`${Math.max(0,Math.round(header.getBoundingClientRect().bottom))}px`);
}
function setPublicMenu(open,{returnFocus=false}={}) {
  if(!menuBtn||!nav)return;
  publicMenuOpen=Boolean(open&&mobileMenuMedia.matches);
  nav.classList.toggle('open',publicMenuOpen);
  menuBtn.classList.toggle('is-open',publicMenuOpen);
  menuBtn.setAttribute('aria-expanded',String(publicMenuOpen));
  menuBtn.setAttribute('aria-label',publicMenuOpen?(menuBtn.dataset.closeLabel||'Close menu'):(menuBtn.dataset.openLabel||'Open menu'));
  document.body.classList.toggle('public-menu-open',publicMenuOpen);
  if(mobileMenuMedia.matches)nav.toggleAttribute('inert',!publicMenuOpen);
  else nav.removeAttribute('inert');
  if(publicMenuOpen)updateMobileMenuOffset();
  else nav.style.removeProperty('--mobile-menu-top');
  if(!publicMenuOpen&&returnFocus)menuBtn.focus();
}
menuBtn?.addEventListener('click',()=>setPublicMenu(!publicMenuOpen));
document.addEventListener('pointerdown',event=> {
  if(publicMenuOpen&&header&&!header.contains(event.target))setPublicMenu(false,{returnFocus:true});
});
document.addEventListener('keydown',event=> {
  if(event.key==='Escape'&&publicMenuOpen)setPublicMenu(false,{returnFocus:true});
});
function syncPublicMenuViewport() {
  if(!mobileMenuMedia.matches)setPublicMenu(false);
  else {
    if(!publicMenuOpen)nav?.setAttribute('inert','');
    updateMobileMenuOffset();
  }
}
window.addEventListener('resize',syncPublicMenuViewport,{passive:true});
window.addEventListener('orientationchange',()=>setPublicMenu(false,{returnFocus:true}),{passive:true});
window.addEventListener('pageshow',()=>setPublicMenu(false));
syncPublicMenuViewport();
function scrollToTarget(hash) {
  const target=document.querySelector(hash);
  if(!target)return;
  const offset=(header?.offsetHeight||0)+8;
  const top=target.getBoundingClientRect().top+window.scrollY-offset;
  window.scrollTo( {
    top,behavior:'smooth'
  }
  );
}
document.querySelectorAll('[data-scroll][href^="#"]').forEach(link=>link.addEventListener('click',event=> {
  const hash=link.getAttribute('href');if(!hash||hash==='#')return;event.preventDefault();setPublicMenu(false);scrollToTarget(hash);history.replaceState(null,'',hash);
}
));
const navLinks=[...document.querySelectorAll('.main-nav a[href^="#"]')];
const sections=navLinks.map(link=>document.querySelector(link.getAttribute('href'))).filter((section,index,list)=>section&&list.indexOf(section)===index);
const spy=new IntersectionObserver(entries=> {
  const visible=entries.filter(e=>e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];
  if(!visible)return;
  navLinks.forEach(link=> {
    const active=link.getAttribute('href')==='#'+visible.target.id;
    link.classList.toggle('active',active);
    if(active)link.setAttribute('aria-current','location');
    else link.removeAttribute('aria-current');
  });
}
, {
  rootMargin:'-22% 0px -58% 0px',threshold:[0,.15,.35,.6]
}
);
sections.forEach(s=>spy.observe(s));
const revealObserver=new IntersectionObserver(entries=>entries.forEach(entry=> {
  if(entry.isIntersecting) {
    entry.target.classList.add('visible');revealObserver.unobserve(entry.target);
  }
}
), {
  threshold:.08
}
);
document.querySelectorAll('.reveal').forEach(el=>revealObserver.observe(el));
function updateProgress() {
  if(!progress)return;
  const max=document.documentElement.scrollHeight-window.innerHeight;
  progress.style.width=(max>0?Math.min(100,window.scrollY/max*100):0)+'%';
}
window.addEventListener('scroll',updateProgress, {
  passive:true
}
);
updateProgress();
const upload=document.querySelector('[data-public-upload]'),fileInput=upload?.querySelector('input[type=file]'),preview=upload?.querySelector('[data-public-upload-preview]');
let selected=[];
function syncPublicFiles() {
  if(!fileInput)return;
  const dt=new DataTransfer();
  selected.forEach(f=>dt.items.add(f));
  fileInput.files=dt.files;
  renderPublicFiles()
}
function renderPublicFiles() {
  if(!preview)return;
  preview.innerHTML='';
  selected.forEach((f,i)=> {
    const u=URL.createObjectURL(f),d=document.createElement('div');
    d.className='public-upload-item';
    d.innerHTML='<img alt="Selected project image"><button type="button" aria-label="Remove image">×</button>';
    d.querySelector('img').src=u;
    d.querySelector('button').addEventListener('click',e=> {
      e.stopPropagation();selected.splice(i,1);syncPublicFiles()
    }
    );preview.appendChild(d)
  }
  )
}
function addPublicFiles(list) {
  const imgs=[...list].filter(f=>/^image\/(jpeg|png|webp)$/.test(f.type));
  selected=[...selected,...imgs].slice(0,8);
  syncPublicFiles()
}
fileInput?.addEventListener('change',()=> {
  selected=[...fileInput.files].slice(0,8);syncPublicFiles()
}
);
upload?.addEventListener('click',e=> {
  if(e.target.closest('button'))return;if(e.target!==fileInput)fileInput.click()
}
);
upload?.addEventListener('dragover',e=> {
  e.preventDefault();upload.classList.add('dragover')
}
);
upload?.addEventListener('dragleave',()=>upload.classList.remove('dragover'));
upload?.addEventListener('drop',e=> {
  e.preventDefault();upload.classList.remove('dragover');addPublicFiles(e.dataTransfer.files)
}
);
upload?.addEventListener('paste',e=> {
  const files=[...e.clipboardData.items].filter(i=>i.kind==='file').map(i=>i.getAsFile()).filter(Boolean);if(files.length) {
    e.preventDefault();addPublicFiles(files)
  }
}
);
const light=document.querySelector('[data-site-lightbox]'),lightImg=document.querySelector('[data-site-lightbox-img]'),lightCap=document.querySelector('[data-site-lightbox-caption]');
document.querySelectorAll('[data-gallery-src]').forEach(b=>b.addEventListener('click',()=> {
  lightImg.src=b.dataset.gallerySrc||'';lightCap.textContent=b.dataset.galleryTitle||'';light.classList.add('open');light.setAttribute('aria-hidden','false')
}
));
function closeLight() {
  light?.classList.remove('open');
  light?.setAttribute('aria-hidden','true');
  if(lightImg)lightImg.src=''
}
document.querySelector('[data-site-lightbox-close]')?.addEventListener('click',closeLight);
document.addEventListener('keydown',e=> {
  if(e.key==='Escape')closeLight()
}
);
const form=document.getElementById('estimateForm'),toast=document.getElementById('toast');
const referralSelect=form?.querySelector('[data-referral-select]');
const referralOtherField=form?.querySelector('[data-referral-other-field]');
const referralOtherInput=referralOtherField?.querySelector('input');
const turnstileContainer=form?.querySelector('[data-turnstile-container]');
let turnstileWidgetId=null;
const formLanguage=form?.dataset.language==='es'?'es':'en';
const formMessages=formLanguage==='es'? {
  required:'Completa los campos obligatorios antes de enviar.',
  name:'Escribe un nombre válido.',
  phone:'Escribe un número de teléfono válido.',
  message:'El mensaje necesita más información útil.',
  other:'Explica brevemente cómo nos encontraste.',
  verification:'No pudimos verificar el envío. Intenta nuevamente.',
  sending:'Enviando...',
  received:'Solicitud recibida.',
  failed:'No pudimos enviar la solicitud. Intenta nuevamente o usa otro medio de contacto.'
}: {
  required:'Complete the required fields before submitting.',
  name:'Enter a valid name.',
  phone:'Enter a valid phone number.',
  message:'The message needs more meaningful information.',
  other:'Briefly explain how you found us.',
  verification:'We could not verify the submission. Please try again.',
  sending:'Sending...',
  received:'Request received.',
  failed:'We could not send the request. Try again or use another contact method.'
};
window.cmsTurnstileReady=()=> {
  if(!turnstileContainer||!window.turnstile||turnstileWidgetId!==null)return;
  turnstileWidgetId=window.turnstile.render(turnstileContainer,{
    sitekey:turnstileContainer.dataset.sitekey,
    action:'contact_inquiry',
    appearance:'interaction-only',
    theme:'auto',
    size:'flexible',
    language:formLanguage,
    'refresh-expired':'auto',
    'error-callback':()=>showFormMessage(formMessages.verification),
    'timeout-callback':()=>window.turnstile?.reset(turnstileWidgetId)
  });
};
function resetTurnstile() {
  if(turnstileWidgetId!==null&&window.turnstile)window.turnstile.reset(turnstileWidgetId);
}
function showFormMessage(message,type='info') {
  if(window.showNotify){window.showNotify(message,type);return;}
  if(!toast)return;
  toast.textContent=message;
  toast.classList.add('show');
  window.setTimeout(()=>toast.classList.remove('show'),5200);
}
function syncReferralOther() {
  if(!referralSelect||!referralOtherField||!referralOtherInput)return;
  const option=referralSelect.options[referralSelect.selectedIndex];
  const active=Boolean(option?.hasAttribute('data-referral-other'));
  referralOtherField.hidden=!active;
  referralOtherInput.disabled=!active;
  referralOtherInput.required=active;
  if(!active) {
    referralOtherInput.value='';
    referralOtherInput.removeAttribute('aria-invalid');
  }
}
function meaningfulMetrics(value) {
  const normalized=value.trim().replace(/\s+/g,' ');
  return {
    characters:(normalized.match(/[\p{L}\p{N}]/gu)||[]).length,
    words:(normalized.match(/[\p{L}\p{N}]+/gu)||[]).length
  };
}
function markInvalid(control,message) {
  control.setAttribute('aria-invalid','true');
  control.focus();
  showFormMessage(message);
  return false;
}
function validateEstimateForm() {
  if(!form)return false;
  form.querySelectorAll('[aria-invalid="true"]').forEach(control=>control.removeAttribute('aria-invalid'));
  if(!form.checkValidity()) {
    const invalid=form.querySelector(':invalid');
    if(invalid)invalid.setAttribute('aria-invalid','true');
    form.reportValidity();
    showFormMessage(formMessages.required);
    return false;
  }
  const name=form.elements.namedItem('name');
  if(name&&(name.required||name.value.trim()!=='')&&meaningfulMetrics(name.value).characters<2)return markInvalid(name,formMessages.name);
  const phone=form.elements.namedItem('phone');
  if(phone&&(phone.required||phone.value.trim()!=='')&&(phone.value.match(/\d/g)||[]).length<7)return markInvalid(phone,formMessages.phone);
  if(referralOtherInput&&!referralOtherInput.disabled&&meaningfulMetrics(referralOtherInput.value).characters<2) {
    return markInvalid(referralOtherInput,formMessages.other);
  }
  const message=form.elements.namedItem('message');
  if(message?.required&&message.value.trim()==='')return markInvalid(message,formMessages.message);
  if(message&&message.value.trim()!=='') {
    const metrics=meaningfulMetrics(message.value);
    const minimumCharacters=Number(form.dataset.messageMinCharacters||30);
    const minimumWords=Number(form.dataset.messageMinWords||5);
    if(metrics.characters<minimumCharacters||metrics.words<minimumWords)return markInvalid(message,formMessages.message);
  }
  return true;
}
referralSelect?.addEventListener('change',syncReferralOther);
syncReferralOther();
form?.querySelectorAll('input,select,textarea').forEach(control=>control.addEventListener('input',()=>control.removeAttribute('aria-invalid')));
form?.addEventListener('submit',async e=> {
  e.preventDefault();
  if(!validateEstimateForm())return;
  const btn=form.querySelector('button[type="submit"]'),old=btn.innerHTML;
  btn.disabled=true;
  btn.textContent=formMessages.sending;
  try {
    const res=await fetch('estimate-submit.php',{method:'POST',body:new FormData(form)});
    const data=await res.json();
    showFormMessage(data.message||formMessages.received,data.ok?'success':'error');
    if(data.ok) {
      form.reset();
      selected=[];
      renderPublicFiles();
      syncReferralOther();
      form.querySelectorAll('[aria-invalid="true"]').forEach(control=>control.removeAttribute('aria-invalid'));
    }
  } catch {
    showFormMessage(formMessages.failed,'error');
  } finally {
    resetTurnstile();
    btn.disabled=false;
    btn.innerHTML=old;
  }
});
if(location.hash&&document.querySelector(location.hash))setTimeout(()=>scrollToTarget(location.hash),100);
