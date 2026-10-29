/* Lightweight report updates: private, visible-tab polling and explicit acknowledgment. */
(()=>{
 const marker=document.querySelector('[data-bug-updates]');if(!marker)return;
 let busy=false,dialog=null;const csrf=document.querySelector('meta[name="csrf"]')?.content||'';
 const el=(tag,text)=>{const n=document.createElement(tag);if(text)n.textContent=text;return n;};
 async function poll(){
  if(busy||dialog||document.hidden||navigator.onLine===false)return;busy=true;
  try{const res=await fetch('?page=bug_updates',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(!res.ok)return;const data=await res.json(),u=data.update;if(!u)return;
   dialog=el('dialog');dialog.className='hp-sheet bug-update-dialog';const box=el('div');box.className='hp-card';const title=el('h2','Bug report #'+u.report_id+' · '+u.status);title.id='bug-update-title';dialog.setAttribute('aria-labelledby',title.id);box.append(title,el('p',u.body));
   const link=el('a','View report and replies');link.className='btn secondary';link.href='?page=bug_report&id='+encodeURIComponent(u.report_id);const ok=el('button','Got it');ok.type='button';ok.className='btn primary';const status=el('p');status.setAttribute('role','status');box.append(link,ok,status);dialog.append(box);document.body.append(dialog);
   ok.addEventListener('click',async()=>{ok.disabled=true;const fd=new FormData();fd.set('action','bug_popup_ack');fd.set('csrf',csrf);fd.set('event',u.id);try{const r=await fetch('index.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});const j=await r.json();if(!r.ok||!j.ok)throw Error();dialog.close();}catch(e){status.textContent='Could not save this yet. Please try again when connected.';ok.disabled=false;}});
   dialog.addEventListener('close',()=>{dialog.remove();dialog=null;},{once:true});if(dialog.showModal)dialog.showModal();else dialog.setAttribute('open','');
  }catch(e){}finally{busy=false;}
 }
 poll();setInterval(poll,45000);document.addEventListener('visibilitychange',poll);window.addEventListener('online',poll);
})();
