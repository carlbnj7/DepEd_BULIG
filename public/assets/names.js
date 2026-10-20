/* BULIG names and sections: "Shows as Juan S. Dela Cruz Jr." under the First / Middle / Last / Extension boxes;
   "Shows as Grade 2 · Sampaguita" and the same-section warning when adding a section; "+ Add a new section…" in the
   Section lists; and the "Type the list" grid for a whole class (Enter goes to the next pupil, each row is checked while
   typing, one section for all or a section on each row, and a starting level on each row). */
(function(){
 'use strict';
 const tidy=s=>{s=s.trim().replace(/\s+/g,' ');return s&&(s===s.toUpperCase()||s===s.toLowerCase())?s.toLowerCase().replace(/(^|[\s\-'])(\S)/g,(m,a,b)=>a+b.toUpperCase()):s;};
 const shown=(f,m,l,x)=>[tidy(f),m.trim()?m.trim()[0].toUpperCase()+'.':'',tidy(l),x].filter(Boolean).join(' ');

 document.querySelectorAll('[data-nm]').forEach(row=>{
  const form=row.closest('form'),prev=form&&form.querySelector('[data-nm-prev]'),out=prev&&prev.querySelector('[data-nm-out]');if(!out)return;
  const v=n=>(form.elements[n]||{value:''}).value;
  const run=()=>{const f=v('first_name').trim(),l=v('last_name').trim();prev.hidden=!(f||l);out.textContent=shown(f,v('middle_name'),l,v('name_ext'));};
  row.addEventListener('input',run);row.addEventListener('change',run);run();
 });

 /* a new section: Grade + name, "Shows as …", and a warning when it already exists */
 document.querySelectorAll('[data-sx]').forEach(box=>{
  let known={mine:{},others:{}};try{known=JSON.parse(box.dataset.sx)||known;}catch(e){}
  const g=box.querySelector('[data-sx-grade]'),n=box.querySelector('[data-sx-name]'),prev=box.querySelector('[data-sx-prev]'),out=box.querySelector('[data-sx-out]'),tail=box.querySelector('[data-sx-tail]'),warn=box.querySelector('[data-sx-warn]'),msg=box.querySelector('[data-sx-msg]');if(!g||!n)return;
  if(tail&&box.matches('[data-sx-pick]'))tail.textContent='BULIG adds it when you save.';
  const run=()=>{const name=tidy(n.value),label='Grade '+g.value+' · '+name,key=g.value+'|'+name.toLowerCase();prev.hidden=!name;out.textContent=label;
   const who=known.others[key];warn.hidden=!name||!(known.mine[key]||who);
   if(known.mine[key])msg.textContent='You already have '+label+'. Choose it from your sections instead.';
   else if(who)msg.textContent=label+' is already handled by '+who+'. If you share this class, ask your administrator. Or use a different name.';};
  g.addEventListener('change',run);n.addEventListener('input',run);run();
 });
 /* "+ Add a new section…" opens the small box under the Section list */
 document.querySelectorAll('[data-sx-select]').forEach(sel=>{
  const lab=sel.closest('label'),pick=lab&&lab.nextElementSibling;if(!pick||!pick.matches('[data-sx-pick]'))return;
  const name=pick.querySelector('[data-sx-name]'),cancel=pick.querySelector('[data-sx-cancel]');
  const run=()=>{const on=sel.value==='new'&&!sel.disabled;pick.hidden=!on;name.required=on;};
  sel.addEventListener('change',()=>{run();if(sel.value==='new')name.focus();});
  if(cancel)cancel.addEventListener('click',()=>{sel.value='';run();sel.focus();});run();
 });

 const grid=document.querySelector('[data-bk]');if(!grid)return;
 const body=grid.querySelector('[data-bk-body]'),tpl=grid.querySelector('[data-bk-tpl]'),count=grid.querySelector('[data-bk-count]'),label=grid.querySelector('[data-bk-label]'),go=grid.querySelector('[data-bk-go]');
 const MAX=300,rows=()=>[...body.querySelectorAll('[data-bk-row]')],who=grid.dataset.who||'pupil';   // the pupils' list, or the admin's teacher list
 const cell=(tr,k)=>tr.querySelector('[name^="t['+k+']"]');
 const many=()=>grid.dataset.mode==='many',lvAll=grid.querySelector('[data-bk-lvall]'),topSec=grid.querySelector('.bk-onesec [data-sx-select]');
 function check(tr){
  const last=cell(tr,'last').value.trim(),first=cell(tr,'first').value.trim(),mid=cell(tr,'middle').value.trim(),lrn=cell(tr,'lrn')?cell(tr,'lrn').value.replace(/[\s\-]/g,''):'',tsec=cell(tr,'section')?cell(tr,'section').value.trim():'',gr=cell(tr,'grade');
  const sex=tr.querySelector('[name^="t[sex]"]:checked'),st=tr.querySelector('[data-bk-st]');
  if(!last&&!first&&!mid&&!lrn&&!tsec){tr.classList.remove('err');tr.dataset.state='';delete st.dataset.server;st.textContent='';return '';}
  if(many()&&!cell(tr,'sec').value){const up=rows().slice(0,rows().indexOf(tr)).reverse().find(r=>cell(r,'sec').value);if(up)cell(tr,'sec').value=cell(up,'sec').value;}   // same section as the pupil above
  const need=!first?'Type the first name':!last?'Type the last name':!sex?'Choose M or F':many()&&!cell(tr,'sec').value?'Choose a section':lrn&&!/^\d{12}$/.test(lrn)?'LRN needs 12 digits':tsec&&gr&&!gr.value?'Choose the grade of the section':'';
  if(st.dataset.server&&!need)return tr.dataset.state='bad';   // the server's note stays until the row is changed
  delete st.dataset.server;tr.classList.toggle('err',!!need);tr.dataset.state=need?'bad':'ok';
  st.innerHTML=need?'<span class="bad"></span>':'<span class="ok">✓ Ready</span>';if(need)st.firstChild.textContent=need;
  return tr.dataset.state;
 }
 function tally(){
  let ok=0,bad=0;rows().forEach(tr=>{const s=tr.dataset.state;if(s==='ok')ok++;else if(s==='bad')bad++;});
  count.textContent=ok||bad?ok+' ready'+(bad?' · '+bad+' to fix':''):'';
  label.textContent=ok?'Create '+ok+' '+who+' account'+(ok===1?'':'s'):'Create '+who+' accounts';
  go.disabled=!ok||bad>0;
 }
 function more(n){
  const have=rows().length;for(let i=have;i<Math.min(MAX,have+n);i++){
   const tr=tpl.content.firstElementChild.cloneNode(true);
   tr.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace('[__]','['+i+']');});
   tr.querySelectorAll('input:not([type=radio])').forEach(el=>{el.setAttribute('aria-label',el.getAttribute('aria-label').replace(/(pupil|teacher) $/,'$1 '+(i+1)));});
   tr.querySelector('[data-bk-n]').textContent=i+1;const lv=cell(tr,'level');if(lv&&lvAll&&[...lv.options].some(o=>o.value===lvAll.value))lv.value=lvAll.value;body.appendChild(tr);}
  grid.querySelector('[data-bk-more]').hidden=rows().length>=MAX;
 }
 /* one section for everyone, or a section on each row */
 const setMode=()=>{const v=(grid.querySelector('[data-bk-mode]:checked')||{}).value||'one';grid.dataset.mode=v;
  grid.querySelectorAll('[data-bk-mode]').forEach(r=>r.closest('label').classList.toggle('on',r.checked));
  if(topSec){topSec.disabled=v==='many';topSec.required=v!=='many';topSec.dispatchEvent(new Event('change'));}};
 grid.querySelectorAll('[data-bk-mode]').forEach(r=>r.addEventListener('change',()=>{setMode();rows().forEach(check);tally();}));setMode();
 /* "Starting level for everyone" fills every row the teacher has not changed by hand */
 const paintLv=sel=>sel.classList.toggle('chg',!!lvAll&&sel.value!==lvAll.value);
 if(lvAll)lvAll.addEventListener('change',()=>{rows().forEach(tr=>{const s=cell(tr,'level');if(!s.dataset.own&&[...s.options].some(o=>o.value===lvAll.value))s.value=lvAll.value;paintLv(s);});});
 body.addEventListener('change',e=>{if(e.target.matches('[data-bk-lv]')){e.target.dataset.own='1';paintLv(e.target);}});
 rows().forEach(tr=>{const st=tr.querySelector('[data-bk-st]');if(st.textContent.trim())st.dataset.server=st.textContent;check(tr);});tally();
 const edited=e=>{const tr=e.target.closest('[data-bk-row]');if(!tr)return;delete tr.querySelector('[data-bk-st]').dataset.server;check(tr);tally();};
 body.addEventListener('input',edited);body.addEventListener('change',edited);
 body.addEventListener('keydown',e=>{
  if(e.key!=='Enter'||e.isComposing)return;const el=e.target;if(!el.matches('input,select'))return;e.preventDefault();
  const tr=el.closest('[data-bk-row]'),all=rows(),i=all.indexOf(tr);const col=el.type==='radio'?'sex':(el.name.match(/^t\[(\w+)\]/)||[])[1];
  if(i===all.length-1)more(10);const next=rows()[i+1];if(!next)return;
  // a new pupil starts at the Last name; inside a column Enter keeps to that column
  const target=['lrn','sex','sec','level','grade','section'].includes(col)?cell(next,'last'):cell(next,col)||cell(next,'last');target.focus();if(target.select)target.select();
 });
 grid.querySelector('[data-bk-more]').addEventListener('click',()=>{const n=rows().length;more(10);const r=rows()[n];if(r)cell(r,'last').focus();});
 grid.addEventListener('submit',e=>{if(go.disabled){e.preventDefault();const bad=rows().find(tr=>tr.dataset.state==='bad');if(bad)cell(bad,'last').focus();}});
})();
