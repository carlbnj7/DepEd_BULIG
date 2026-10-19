<?php
/** Sections: the Sections page (Grade first, then the name; one card per section with Add pupils, View pupils,
   Report cards, Rename and Remove), and "+ Add a new section…" in the Section lists of Add pupils.
   A teacher cannot add the same section twice. Another teacher's section with the same grade and name only shows a
   warning while typing (a class can be shared). Remove works only for a section with no pupils. */

function section_label(array $s):string{return 'Grade '.(int)$s['grade_level'].' · '.$s['name'];}
function section_key(int $grade,string $name):string{return $grade.'|'.mb_strtolower(trim(preg_replace('/\s+/',' ',$name)));}

/** For the warning while typing: this teacher's sections, and other teachers' sections with the teacher's name. */
function section_known(int $tid):array{
 $mine=[];$others=[];
 foreach(rows('SELECT s.teacher_id,s.grade_level,s.name,u.name t FROM sections s JOIN users u ON u.id=s.teacher_id WHERE 1'.(sections_archive_supported()?' AND s.archived_at IS NULL':''),[]) as $s){
  $k=section_key((int)$s['grade_level'],(string)$s['name']);if((int)$s['teacher_id']===$tid)$mine[$k]=1;else $others[$k]=$s['t'];}
 return ['mine'=>$mine,'others'=>$others];
}
/** Grade + Section name boxes, "Shows as …" and the warning (assets/names.js fills them in while typing). */
function section_fields(int $tid,string $grade='grade_level',string $name='name',string $after=''):string{
 $g='';for($i=1;$i<=6;$i++)$g.='<option value="'.$i.'">Grade '.$i.'</option>';
 return '<label>Grade<select name="'.$grade.'" data-sx-grade>'.$g.'</select></label><label>Section name<input name="'.$name.'" maxlength="80" autocomplete="off" autocapitalize="words" placeholder="e.g. Sampaguita" data-sx-name></label>'.$after
  .'<p class="sx-prev" data-sx-prev hidden>Shows as <b data-sx-out></b><span data-sx-tail></span></p>'
  .'<p class="sx-warn" data-sx-warn hidden>'.icon('alert').'<span data-sx-msg></span></p>';
}
function section_data(int $tid):string{return ' data-sx="'.e(json_encode(section_known($tid))).'"';}

/** The Section list of Add pupils, with "+ Add a new section…" and its small box (no need to leave the page). */
function section_select(array $sections,int $selected,int $tid,string $label='Section'):string{
 $s='<label>'.$label.'<select name="section_id" required data-sx-select><option value="">Choose a section</option>';
 foreach($sections as $x)$s.='<option value="'.$x['id'].'"'.((int)$x['id']===$selected?' selected':'').'>'.e(section_label($x)).'</option>';
 $s.='<option value="new">+ Add a new section…</option></select></label>';
 return $s.'<div class="sx-pick" data-sx-pick'.section_data($tid).' hidden><h3>Add a new section</h3><div class="sx-row">'
  .section_fields($tid,'new_grade','new_section','<button type="button" class="btn secondary" data-sx-cancel>Cancel</button>').'</div></div>';
}

/** The section chosen in a form: one of the teacher's sections, or a new one typed in the small box (made here). Call inside a transaction. */
function section_from_post(int $tid):array{
 if(($_POST['section_id']??'')!=='new')return owned_section((int)($_POST['section_id']??0),$tid);
 [$grade,$name]=section_new_input();return section_make($tid,$grade,$name);
}
function section_new_input():array{
 $grade=(int)($_POST['new_grade']??0);$name=import_name_case(mb_substr((string)($_POST['new_section']??''),0,80));
 if($name===''||$grade<1||$grade>6)fail('Type the name of the new section and choose its grade.');
 return [$grade,$name];
}
function section_make(int $tid,int $grade,string $name):array{
 $id=(int)val('SELECT id FROM sections WHERE teacher_id=? AND grade_level=? AND LOWER(name)=LOWER(?)'.(sections_archive_supported()?' AND archived_at IS NULL':''),[$tid,$grade,$name]);
 if(!$id){q('INSERT INTO sections(teacher_id,grade_level,name) VALUES(?,?,?)',[$tid,$grade,$name]);$id=(int)db()->lastInsertId();audit('save_section',(string)$id);}
 return owned_section($id,$tid);
}

function sections_view(array $u):void{
 $tid=(int)$u['id'];$sections=teacher_sections($tid);$per=[];foreach($sections as $s)$per[(int)$s['grade_level']][]=$s;
 $counts=array_column(rows('SELECT ps.section_id,COUNT(*) n FROM pupil_sections ps JOIN sections s ON s.id=ps.section_id WHERE s.teacher_id=? GROUP BY ps.section_id',[$tid]),'n','section_id');
 echo '<div class="tdash-head"><div><span class="eyebrow">YOUR CLASSES</span><h1>Your sections</h1><p class="muted">Add each section you handle. Then add the pupils to it.</p></div></div>';
 if(!$sections)echo '<section class="card"><h2 class="sx-h">Start here</h2><p class="muted sx-sub">Two steps before your pupils can sign in.</p><div class="sx-empty"><div class="sx-step"><i>1</i><div><b>Add your section</b><span>Choose the grade and type the section name, for example Grade 1 · Rizal.</span></div></div><div class="sx-step next"><i>2</i><div><b>Add your pupils</b><span>One pupil at a time, or the whole class: type the list or upload the Excel file.</span></div></div></div></section>';
 echo '<section class="card section-add"><h2 class="sx-h">Add a section</h2><form method="post" class="sx-form"'.section_data($tid).'>'.csrf_field().'<input name="action" type="hidden" value="save_section">'
  .section_fields($tid,'grade_level','name','<button class="btn primary">'.icon('check').'Add section</button>').'</form></section>';
 if(!$sections){echo names_script();return;}
 echo '<section class="card section-board">';
 foreach($per as $g=>$list){
  echo '<div class="sx-grade"><h2>Grade '.$g.' <span>'.count($list).' '.(count($list)===1?'section':'sections').'</span></h2><div class="sx-cards">';
  foreach($list as $s){$id=(int)$s['id'];$n=(int)($counts[$id]??0);$o='';for($i=1;$i<=6;$i++)$o.='<option value="'.$i.'"'.($i===$g?' selected':'').'>Grade '.$i.'</option>';
   echo '<article class="sx-card" id="section-'.$id.'"><div class="sx-top"><span class="sx-badge">'.$g.'</span><div><strong>'.e(section_label($s)).'</strong><small>'.$n.' '.($n===1?'pupil':'pupils').'</small></div></div>'
    .'<div class="sx-act"><a class="sx-go" href="?page=accounts&amp;section='.$id.'">'.icon('useredit').'Add pupils</a><a href="?page=dashboard&amp;section_id='.$id.'">'.icon('people').'View pupils</a><a href="?page=report_card&amp;section='.$id.'">'.icon('flag').'Report cards</a>'
    .'<details class="sx-more"><summary>'.icon('edit').'Rename</summary><form method="post" class="sx-rename">'.csrf_field().'<input name="action" type="hidden" value="save_section"><input name="id" type="hidden" value="'.$id.'"><label>Grade<select name="grade_level">'.$o.'</select></label><label>Section name<input name="name" value="'.e($s['name']).'" required maxlength="80"></label><button class="btn primary small">'.icon('check').'Save</button></form></details>'
    .($n?'<span class="sx-del off" title="Remove is for empty sections only">'.icon('close').'Remove</span>':'<form method="post" class="sx-delform">'.csrf_field().'<input name="action" type="hidden" value="delete_section"><input name="id" type="hidden" value="'.$id.'"><button class="sx-del" data-confirm="Remove '.e(section_label($s)).'? It has no pupils.">'.icon('close').'Remove</button></form>')
    .'</div>'.($n?'<p class="sx-note">Remove is for empty sections only. Move or remove its pupils first.</p>':'').'</article>';}
  echo '</div></div>';}
 echo '</section>'.names_script();
}

function sections_action(string $action):void{
 if($action==='delete_section'){
  $u=require_role('teacher');$s=owned_section((int)($_POST['id']??0),(int)$u['id']);
  if(val('SELECT COUNT(*) FROM pupil_sections WHERE section_id=?',[$s['id']]))fail('This section still has pupils. Move or remove them first.');
  q('DELETE FROM sections WHERE id=? AND teacher_id=?',[$s['id'],$u['id']]);audit('delete_section',section_label($s));flash(section_label($s).' was removed.');go('?page=sections');
 }
}
