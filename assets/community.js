(() => {
  'use strict';
  const log=(event,level=3)=>window.AppDebug?.info('browser',event,level);
  let controller=null,sequence=0,searchTimer=null,refreshTimer=null;
  const progress=document.createElement('p');progress.className='community-progress';progress.hidden=true;progress.setAttribute('role','status');document.body.append(progress);
  function schedule(){log('community.website.refresh.schedule',8);clearTimeout(refreshTimer);if(!document.hidden)refreshTimer=setTimeout(()=>void load(location.href,{refresh:true}),30000);}
  async function load(value,{replace=false,refresh=false,lookup=null,restoredScroll=null}={}){
    log('community.website.load.start',1);const url=new URL(value,location.href);if(url.origin!==location.origin)return;
    controller?.abort();controller=new AbortController();const current=++sequence,content=document.getElementById('communityContent');if(!content)return;
    const active=document.activeElement,focused=content.contains(active)&&active.id?{id:active.id,start:active.selectionStart,end:active.selectionEnd}:null;
    const open=focused?Array.from(content.querySelectorAll('details[open]')).map(item=>item.dataset.communityLookup):(lookup?[lookup]:[]);const scroll=scrollY;if(!refresh&&!replace)history.replaceState({community_scroll:scroll},'',location.href);
    content.setAttribute('aria-busy','true');progress.textContent=refresh?'Updating Community…':'Loading Community…';progress.hidden=false;
    try{
      const response=await fetch(url,{cache:'no-store',credentials:'omit',redirect:'error',signal:controller.signal,headers:{Accept:'text/html'}});
      const html=await response.text();if(current!==sequence)return;const parsed=new DOMParser().parseFromString(html,'text/html'),next=parsed.getElementById('communityContent');
      if(!next)throw new Error('Community unavailable');
      content.replaceChildren(...Array.from(next.childNodes));
      // Reconcile the shared navigation using the server's fresh availability gate.
      for(const nav of ['#site-nav','[aria-label="Footer navigation"]']){
        const old=document.querySelector(nav),fresh=parsed.querySelector(nav);if(old&&fresh)old.replaceChildren(...Array.from(fresh.childNodes));
      }
      if(!refresh){history[replace?'replaceState':'pushState']({community_scroll:replace?scroll:0},'',url.pathname+url.search);}
      for(const key of open)content.querySelector('details[data-community-lookup="'+key+'"]')?.setAttribute('open','');
      if(focused){const input=document.getElementById(focused.id);if(input){input.focus({preventScroll:true});if(typeof input.setSelectionRange==='function'&&input.type==='search')input.setSelectionRange(focused.start,focused.end);}}
      if(restoredScroll!==null)scrollTo({top:restoredScroll,behavior:'instant'});else if(refresh||focused)scrollTo({top:scroll,behavior:'instant'});else{document.querySelector('.community-section')?.scrollIntoView({block:'start'});content.tabIndex=-1;content.focus({preventScroll:true});}
      log('community.website.load.complete',1);
    }catch(error){if(error.name==='AbortError')return;window.AppDebug?.error('browser','community.website.load.failed');
      // Fail closed: a transport error must not leave previously delivered catalog content visible.
      const message=document.createElement('div');message.className='event-card';const title=document.createElement('h2');title.textContent='Community is temporarily unavailable. Please try again.';const retry=document.createElement('a');retry.href=location.pathname+location.search;retry.textContent='Retry Community';message.append(title,retry);content.replaceChildren(message);
      document.querySelectorAll('#site-nav a,[aria-label="Footer navigation"] a').forEach(link=>{if(new URL(link.href).pathname===location.pathname)link.remove();});
    }finally{if(current===sequence){content.removeAttribute('aria-busy');progress.hidden=true;schedule();}}
  }
  function filter(form){log('community.website.filter.change',3);const url=new URL(form.action,location.href);url.search=new URLSearchParams(new FormData(form)).toString();void load(url,{replace:true});}
  document.addEventListener('click',event=>{const link=event.target.closest('.community-section a');if(!link||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target)return;const url=new URL(link.href);if(url.origin!==location.origin||url.pathname!==location.pathname)return;event.preventDefault();const field=link.closest('[data-community-lookup]');const lookup=field&&Array.from(url.searchParams.keys()).some(key=>key==='category_page'||key==='locality_page')?field.dataset.communityLookup:null;void load(url,{lookup});});
  document.addEventListener('submit',event=>{if(event.target.id==='communityFilters'){event.preventDefault();clearTimeout(searchTimer);filter(event.target);}});
  document.addEventListener('input',event=>{if(!event.target.matches('#communityFilters input[type="search"],[data-choice-search]'))return;clearTimeout(searchTimer);searchTimer=setTimeout(()=>filter(document.getElementById('communityFilters')),300);});
  document.addEventListener('change',event=>{if(event.target.matches('#communityFilters select'))filter(event.target.form);});
  window.addEventListener('popstate',event=>{const value=event.state?.community_scroll;void load(location.href,{refresh:true,restoredScroll:Number.isFinite(value)?Math.max(0,Math.min(10000000,value)):0});});
  window.addEventListener('pageshow',event=>{if(event.persisted){document.getElementById('communityContent')?.replaceChildren();void load(location.href,{refresh:true});}});
  document.addEventListener('visibilitychange',()=>{clearTimeout(refreshTimer);if(document.hidden){controller?.abort();document.getElementById('communityContent')?.setAttribute('inert','');}else{document.getElementById('communityContent')?.removeAttribute('inert');document.getElementById('communityContent')?.replaceChildren();void load(location.href,{refresh:true});}});
  window.addEventListener('pagehide',()=>{controller?.abort();clearTimeout(searchTimer);clearTimeout(refreshTimer);});
  schedule();log('community.website.ready',1);
})();
