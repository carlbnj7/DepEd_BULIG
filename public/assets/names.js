/* BULIG names: "Shows as Juan S. Dela Cruz Jr." under the First / Middle / Last / Extension boxes,
   and the "Type the list" grid for a whole class (Enter goes to the next pupil, each row is checked while typing). */
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

 const grid=document.querySelector('[data-bk]');if(!grid)return;
 const body=grid.querySelector('[data-bk-body]'),tpl=grid.querySelector('[data-bk-tpl]'),count=grid.querySelector('[data-bk-count]'),label=grid.querySelector('[data-bk-label]'),go=grid.querySelector('[data-bk-go]');
 const MAX=300,rows=()=>[...body.querySelectorAll('[data-bk-row]')];
 const cell=(tr,k)=>tr.querySelector('[name^="t['+k+']"]');
 function check(tr){
  const last=cell(tr,'last').value.trim(),first=cell(tr,'first').value.trim(),mid=cell(tr,'middle').value.trim(),lrn=cell(tr,'lrn').value.replace(/[\s\-]/g,'');
  const sex=tr.querySelector('[name^="t[sex]"]:checked'),st=tr.querySelector('[data-bk-st]');
  if(!last&&!first&&!mid&&!lrn){tr.classList.remove('err');tr.dataset.state='';delete st.dataset.server;st.textContent='';return '';}
  const need=!first?'Type the first name':!last?'Type the last name':!sex?'Choose M or F':lrn&&!/^\d{12}$/.test(lrn)?'LRN needs 12 digits':'';
  if(st.dataset.server&&!need)return tr.dataset.state='bad';   // the server's note stays until the row is changed
  delete st.dataset.server;tr.classList.toggle('err',!!need);tr.dataset.state=need?'bad':'ok';
  st.innerHTML=need?'<span class="bad"></span>':'<span class="ok">✓ Ready</span>';if(need)st.firstChild.textContent=need;
  return tr.dataset.state;
 }
 function tally(){
  let ok=0,bad=0;rows().forEach(tr=>{const s=tr.dataset.state;if(s==='ok')ok++;else if(s==='bad')bad++;});
  count.textContent=ok||bad?ok+' ready'+(bad?' · '+bad+' to fix':''):'';
  label.textContent=ok?'Create '+ok+' pupil account'+(ok===1?'':'s'):'Create pupil accounts';
  go.disabled=!ok||bad>0;
 }
 function more(n){
  const have=rows().length;for(let i=have;i<Math.min(MAX,have+n);i++){
   const tr=tpl.content.firstElementChild.cloneNode(true);
   tr.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace('[__]','['+i+']');});
   tr.querySelectorAll('input:not([type=radio])').forEach(el=>{el.setAttribute('aria-label',el.getAttribute('aria-label').replace(/pupil $/,'pupil '+(i+1)));});
   tr.querySelector('[data-bk-n]').textContent=i+1;body.appendChild(tr);}
  grid.querySelector('[data-bk-more]').hidden=rows().length>=MAX;
 }
 rows().forEach(tr=>{const st=tr.querySelector('[data-bk-st]');if(st.textContent.trim())st.dataset.server=st.textContent;check(tr);});tally();
 const edited=e=>{const tr=e.target.closest('[data-bk-row]');if(!tr)return;delete tr.querySelector('[data-bk-st]').dataset.server;check(tr);tally();};
 body.addEventListener('input',edited);body.addEventListener('change',edited);
 body.addEventListener('keydown',e=>{
  if(e.key!=='Enter'||e.isComposing)return;const el=e.target;if(!el.matches('input,select'))return;e.preventDefault();
  const tr=el.closest('[data-bk-row]'),all=rows(),i=all.indexOf(tr);const col=el.type==='radio'?'sex':(el.name.match(/^t\[(\w+)\]/)||[])[1];
  if(i===all.length-1)more(10);const next=rows()[i+1];if(!next)return;
  // a new pupil starts at the Last name; inside a column Enter keeps to that column
  const target=col==='lrn'||col==='sex'?cell(next,'last'):cell(next,col)||cell(next,'last');target.focus();if(target.select)target.select();
 });
 grid.querySelector('[data-bk-more]').addEventListener('click',()=>{const n=rows().length;more(10);const r=rows()[n];if(r)cell(r,'last').focus();});
 grid.addEventListener('submit',e=>{if(go.disabled){e.preventDefault();const bad=rows().find(tr=>tr.dataset.state==='bad');if(bad)cell(bad,'last').focus();}});
})();
