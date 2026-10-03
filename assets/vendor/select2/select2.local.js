(function($,w,d){if(!$)return;
  function init(select,opts){if(select.dataset.s2Ready)return;select.dataset.s2Ready='1';opts=opts||{};select.classList.add('s2-hidden-accessible');
    const box=d.createElement('div');box.className='s2-container';const btn=d.createElement('button');btn.type='button';btn.className='s2-selection';btn.innerHTML='<span class="s2-label"></span><span class="s2-arrow" aria-hidden="true"></span>';const drop=d.createElement('div');drop.className='s2-dropdown';box.append(btn,drop);select.insertAdjacentElement('afterend',box);
    function selectedText(){const o=select.options[select.selectedIndex];return o?o.text:''}
    function render(){drop.innerHTML='';const threshold=Number(opts.minimumResultsForSearch??8);let search=null;if(select.options.length>=threshold){search=d.createElement('input');search.type='search';search.className='s2-search';search.placeholder='Buscar...';drop.appendChild(search)}const list=d.createElement('div');drop.appendChild(list);
      function fill(q){list.innerHTML='';q=(q||'').toLowerCase();Array.from(select.options).forEach((o,i)=>{if(q&&!o.text.toLowerCase().includes(q))return;const item=d.createElement('button');item.type='button';item.className='s2-option'+(i===select.selectedIndex?' selected':'');item.textContent=o.text;item.disabled=o.disabled;item.onclick=()=>{select.selectedIndex=i;select.dispatchEvent(new Event('change',{bubbles:true}));close()};list.appendChild(item)})}fill('');if(search)search.addEventListener('input',()=>fill(search.value))}
    function sync(){btn.querySelector('.s2-label').textContent=selectedText();render()}
    function open(){d.querySelectorAll('.s2-container.open').forEach(x=>x!==box&&x.classList.remove('open'));box.classList.add('open');render();setTimeout(()=>drop.querySelector('.s2-search')?.focus(),10)}function close(){box.classList.remove('open')}
    btn.onclick=()=>box.classList.contains('open')?close():open();select.addEventListener('change',sync);select.form?.addEventListener('reset',()=>w.setTimeout(sync,0));d.addEventListener('mousedown',e=>{if(!box.contains(e.target))close()});sync();
  }
  $.fn.select2=function(opts){return this.each(function(){init(this,opts)})};
})(window.jQuery,window,document);
