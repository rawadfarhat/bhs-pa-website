(() => {
  'use strict';
  const log=(event,level=3)=>window.AppDebug?.info('browser',event,level);
  let controller=null,sequence=0,searchTimer=null;
  const progress=document.getElementById('communityProgress');
  function prepareImages(root=document){
    log('community.website.images.prepare',8);
    root.querySelectorAll('[data-community-image]').forEach(frame=>{
      if(frame.dataset.loadingBound)return;frame.dataset.loadingBound='1';
      const image=frame.querySelector('img'),status=frame.querySelector('.community-image-status');if(!image||!status)return;
      frame.classList.add('is-loading');frame.setAttribute('aria-busy','true');log('community.website.image.loading',10);
      const settle=()=>{
        image.removeEventListener('load',settle);image.removeEventListener('error',settle);
        const failed=image.naturalWidth===0;frame.classList.remove('is-loading');frame.classList.toggle('has-error',failed);frame.setAttribute('aria-busy','false');
        status.textContent=failed?'Image unavailable':'';
        if(failed)window.AppDebug?.warning('browser','community.website.image.unavailable');else log('community.website.image.ready',5);
      };
      image.addEventListener('load',settle);image.addEventListener('error',settle);
      if(image.complete)settle();
    });
    log('community.website.images.prepared',8);
  }
  function showProgress(refresh){
    log('community.website.progress.show',8);
    if(!progress)return;
    progress.hidden=false;
    progress.textContent=refresh?'Updating the Circle…':'Loading the Circle…';
    if(!window.matchMedia('(max-width: 600px), (pointer: coarse)').matches)return;
    const bounds=progress.getBoundingClientRect();
    const viewport=window.visualViewport;
    const viewportTop=viewport?.offsetTop??0;
    const viewportBottom=viewportTop+(viewport?.height??window.innerHeight);
    const headerBottom=document.querySelector('.site-header')?.getBoundingClientRect().bottom??0;
    if(bounds.top<Math.max(viewportTop,headerBottom)||bounds.bottom>viewportBottom){
      progress.scrollIntoView({block:'center',behavior:'instant'});
      log('community.website.progress.revealed',10);
    }
  }
  async function load(value,{replace=false,refresh=false,clearContent=false,lookup=null,restoredScroll=null}={}){
    log('community.website.load.start',1);const url=new URL(value,location.href);if(url.origin!==location.origin)return;
    controller?.abort();controller=new AbortController();const current=++sequence,content=document.getElementById('communityContent');if(!content)return;
    const active=document.activeElement,focused=content.contains(active)&&active.id?{id:active.id,start:active.selectionStart,end:active.selectionEnd}:null;
    const open=focused?Array.from(content.querySelectorAll('details[open]')).map(item=>item.dataset.communityLookup):(lookup?[lookup]:[]);const scroll=scrollY;if(!refresh&&!replace)history.replaceState({community_scroll:scroll},'',location.href);
    if(clearContent){content.replaceChildren();log('community.website.content.cleared',10);}
    content.setAttribute('aria-busy','true');showProgress(refresh);
    try{
      const response=await fetch(url,{cache:'no-store',credentials:'omit',redirect:'error',signal:controller.signal,headers:{Accept:'text/html'}});
      const html=await response.text();if(current!==sequence)return;const parsed=new DOMParser().parseFromString(html,'text/html'),next=parsed.getElementById('communityContent');
      if(!next)throw new Error('Circle unavailable');
      content.replaceChildren(...Array.from(next.childNodes));prepareImages(content);
      // Reconcile the shared navigation using the server's fresh availability gate.
      for(const nav of ['#site-nav','[aria-label="Footer navigation"]']){
        const old=document.querySelector(nav),fresh=parsed.querySelector(nav);if(old&&fresh)old.replaceChildren(...Array.from(fresh.childNodes));
      }
      if(!refresh){history[replace?'replaceState':'pushState']({community_scroll:replace?scroll:0},'',url.pathname+url.search);}
      for(const key of open)content.querySelector('details[data-community-lookup="'+key+'"]')?.setAttribute('open','');
      if(focused){const input=document.getElementById(focused.id);if(input){input.focus({preventScroll:true});if(typeof input.setSelectionRange==='function'&&input.type==='search')input.setSelectionRange(focused.start,focused.end);}}
      if(progress)progress.hidden=true;
      if(restoredScroll!==null)scrollTo({top:restoredScroll,behavior:'instant'});else if(refresh||focused)scrollTo({top:scroll,behavior:'instant'});else{document.querySelector('.community-section')?.scrollIntoView({block:'start'});content.tabIndex=-1;content.focus({preventScroll:true});}
      log('community.website.load.complete',1);
    }catch(error){if(error.name==='AbortError')return;window.AppDebug?.error('browser','community.website.load.failed');
      // Fail closed: a transport error must not leave previously delivered catalog content visible.
      const message=document.createElement('div');message.className='event-card';const title=document.createElement('h2');title.textContent='The Circle is temporarily unavailable. Please try again.';const retry=document.createElement('a');retry.href=location.pathname+location.search;retry.textContent='Retry the Circle';message.append(title,retry);content.replaceChildren(message);
      document.querySelectorAll('#site-nav a,[aria-label="Footer navigation"] a').forEach(link=>{if(new URL(link.href).pathname===location.pathname)link.remove();});
    }finally{if(current===sequence){content.removeAttribute('aria-busy');if(progress)progress.hidden=true;log('community.website.load.settled',8);}}
  }
  function filter(form){log('community.website.filter.change',3);const url=new URL(form.action,location.href);url.search=new URLSearchParams(new FormData(form)).toString();void load(url,{replace:true});}
  document.addEventListener('click',event=>{const link=event.target.closest('.community-section a');if(!link||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target)return;const url=new URL(link.href);if(url.origin!==location.origin||url.pathname!==location.pathname)return;event.preventDefault();const field=link.closest('[data-community-lookup]');const lookup=field&&Array.from(url.searchParams.keys()).some(key=>key==='category_page'||key==='locality_page')?field.dataset.communityLookup:null;void load(url,{lookup});});
  document.addEventListener('submit',event=>{if(event.target.id==='communityFilters'){event.preventDefault();clearTimeout(searchTimer);filter(event.target);}});
  document.addEventListener('input',event=>{if(!event.target.matches('#communityFilters input[type="search"],[data-choice-search]'))return;clearTimeout(searchTimer);searchTimer=setTimeout(()=>filter(document.getElementById('communityFilters')),300);});
  document.addEventListener('change',event=>{if(event.target.matches('#communityFilters select'))filter(event.target.form);});
  window.addEventListener('popstate',event=>{const value=event.state?.community_scroll;void load(location.href,{refresh:true,restoredScroll:Number.isFinite(value)?Math.max(0,Math.min(10000000,value)):0});});
  window.addEventListener('pageshow',event=>{if(event.persisted)void load(location.href,{refresh:true,clearContent:true});});
  document.addEventListener('visibilitychange',()=>{if(document.hidden){controller?.abort();document.getElementById('communityContent')?.setAttribute('inert','');}else{document.getElementById('communityContent')?.removeAttribute('inert');void load(location.href,{refresh:true,clearContent:true});}});
  window.addEventListener('pagehide',()=>{controller?.abort();clearTimeout(searchTimer);});
  prepareImages();log('community.website.ready',1);
})();
