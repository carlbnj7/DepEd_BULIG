<?php
/** Teacher dashboard animations: welcome panel, stats, class chart, reading tree, check-ins, milestones, progress, school sky, tabs. */
const TF_DEFS='<linearGradient id="t-blazer" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f8a4b"/><stop offset="1" stop-color="#145028"/></linearGradient><linearGradient id="t-skirt" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1f3d2c"/><stop offset="1" stop-color="#132a1d"/></linearGradient><linearGradient id="t-hair" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4a2f22"/><stop offset="1" stop-color="#22140d"/></linearGradient><linearGradient id="t-board" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2d5a43"/><stop offset="1" stop-color="#1c3d2c"/></linearGradient><linearGradient id="t-wood" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c98a52"/><stop offset="1" stop-color="#8a5527"/></linearGradient><linearGradient id="t-school" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff8e8"/><stop offset="1" stop-color="#f1e2c4"/></linearGradient>';
const TF_TEACHER='<g class="tf-body"><path d="M57 150h9v30h-9zM74 150h9v30h-9z" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><path d="M51 188q0-9 9-9h7v10H52q-1 0-1-1zM89 188q0-9-9-9h-7v10h15q1 0 1-1z" fill="#1f1a1a" stroke="#4a2a18" stroke-width="1.6"/><path d="M64 189v5M76 189v5" stroke="#1f1a1a" stroke-width="2.4"/><path d="M49 116h42l3 38H46z" fill="#6b4a2e" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M70 140v14" stroke="#4e3420" stroke-width="1.5"/><path d="M48 40q-10 44 0 76h16q-10-34-6-70zM92 40q10 44 0 76H76q10-34 6-70z" fill="#8b5a2b" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M93 88q8 14 0 28" fill="none" stroke="#4a2a18" stroke-width="11" stroke-linecap="round"/><path d="M93 88q8 14 0 28" fill="none" stroke="#ffd9b8" stroke-width="8" stroke-linecap="round"/><path d="M46 82q24-10 48 0l-2 36H48z" fill="#e9c48f" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M46 82l-8 12 8 5 4-6zM94 82l8 12-8 5-4-6z" fill="#e9c48f" stroke="#4a2a18" stroke-width="1.8" stroke-linejoin="round"/><path d="M60 76l10 8-12 4zM80 76l-10 8 12 4z" fill="#f4dcb2" stroke="#4a2a18" stroke-width="1.6" stroke-linejoin="round"/><path d="M62 82l8 24 8-24" stroke="#2f7a45" stroke-width="2.2" fill="none"/><rect x="64.5" y="104" width="11" height="15" rx="2" fill="#fff" stroke="#2f7a45" stroke-width="1.8"/><rect x="67" y="108" width="6" height="5" fill="#9fd8a8"/><g transform="rotate(-8 84 110)"><rect x="74" y="96" width="22" height="28" rx="2" fill="#b33a3a" stroke="#4a2a18" stroke-width="1.8"/><path d="M78 96v28" stroke="#8f2a2a" stroke-width="2"/></g><circle cx="88" cy="114" r="5" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><g class="tf-wave"><path d="M48 88 34 62" fill="none" stroke="#4a2a18" stroke-width="11" stroke-linecap="round"/><path d="M48 88 34 62" fill="none" stroke="#ffd9b8" stroke-width="8" stroke-linecap="round"/><path transform="translate(32 55) scale(1 1)" d="M-8 6q-4-12 1-17l3 8q0-11 5-13l2 10q2-10 7-9l-1 10q4-6 8-4l-5 12q-8 8-20 3z" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.8" stroke-linejoin="round"/></g><path d="M47 42q-8 34 0 64q6 5 12-1-7-26-4-56zM93 42q8 34 0 64q-6 5-12-1 7-26 4-56z" fill="#8b5a2b" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><rect x="64" y="64" width="12" height="12" rx="4" fill="#f0b48a"/><circle cx="47" cy="48" r="5" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><circle cx="93" cy="48" r="5" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><ellipse cx="70" cy="46" rx="24" ry="23" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.9"/><path d="M47 50q-4-30 23-30 27 0 23 30-4-12-12-17-6 8-15 9 4-4 4-8-8 8-14 8-6 2-9 8z" fill="#8b5a2b" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M58 26q9-4 18-2" stroke="#b07a40" stroke-width="2" stroke-linecap="round" fill="none"/><rect x="84" y="31" width="10" height="6" rx="3" transform="rotate(30 89 34)" fill="#f6e3b0" stroke="#b98a3e" stroke-width="1.4"/><path d="M58 37q5-3 9-1M73 36q5-2 9 1" stroke="#4a2a18" stroke-width="2" stroke-linecap="round" fill="none"/><g class="tf-eyes"><path d="M56 42l-2.6 -2M66 42l2.6 -2" stroke="#4a2a18" stroke-width="1.4" stroke-linecap="round"/><ellipse cx="61" cy="46" rx="5" ry="6" fill="#fff" stroke="#4a2a18" stroke-width="1.5"/><ellipse cx="61.4" cy="46.7" rx="3.8" ry="4.7" fill="#3b2214"/><ellipse cx="61.4" cy="47.2" rx="2" ry="2.5" fill="#1a0d06"/><circle cx="62.8" cy="44.3" r="1.8" fill="#fff"/><circle cx="59.6" cy="48.4" r=".8" fill="#fff"/><path d="M74 42l-2.6 -2M84 42l2.6 -2" stroke="#4a2a18" stroke-width="1.4" stroke-linecap="round"/><ellipse cx="79" cy="46" rx="5" ry="6" fill="#fff" stroke="#4a2a18" stroke-width="1.5"/><ellipse cx="79.4" cy="46.7" rx="3.8" ry="4.7" fill="#3b2214"/><ellipse cx="79.4" cy="47.2" rx="2" ry="2.5" fill="#1a0d06"/><circle cx="80.8" cy="44.3" r="1.8" fill="#fff"/><circle cx="77.6" cy="48.4" r=".8" fill="#fff"/></g><path d="M69 53q1.5 1.5 3 0" stroke="#c47a50" stroke-width="1.5" stroke-linecap="round" fill="none"/><ellipse cx="54" cy="55" rx="4.875" ry="3.0" fill="#ff8a80" opacity=".55"/><ellipse cx="86" cy="55" rx="4.875" ry="3.0" fill="#ff8a80" opacity=".55"/><path d="M60.9 57q9.1 11.2 18.2 0q-9.1 2.0999999999999996 -18.2 0z" fill="#8f2a1c" stroke="#4a2a18" stroke-width="1.7" stroke-linejoin="round"/><path d="M62.3 57.7q7.699999999999999 1.8199999999999998 15.399999999999999 0l-.6 1.8199999999999998q-7.279999999999999 1.54 -14.559999999999999 0z" fill="#fff"/><path d="M65.8 62.6q4.199999999999999 3.5 8.399999999999999 0q-4.199999999999999 -2.38 -8.399999999999999 0z" fill="#ff7d7d"/></g>';
const TF_TEACHER_M='<g class="tf-body"><path d="M48 120h44l2 62H72l-2-44-2 44H46z" fill="#4a3324" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M57 128v50M83 128v50" stroke="#5e4433" stroke-width="1.4"/><path d="M44 190q0-9 9-9h15v10H45q-1 0-1-1zM96 190q0-9-9-9H72v10h23q1 0 1-1z" fill="#1f1a1a" stroke="#4a2a18" stroke-width="1.6"/><rect x="48" y="116" width="44" height="6" fill="#1f1a1a" stroke="#4a2a18" stroke-width="1.4"/><rect x="66" y="116.5" width="8" height="5" rx="1" fill="#c9ced3"/><path d="M93 88q8 14 0 28" fill="none" stroke="#4a2a18" stroke-width="11" stroke-linecap="round"/><path d="M93 88q8 14 0 28" fill="none" stroke="#ffd9b8" stroke-width="8" stroke-linecap="round"/><path d="M45 82q25-10 50 0l-2 36H47z" fill="#f2e7c6" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M45 82l-8 13 8 5 4-6zM95 82l8 13-8 5-4-6z" fill="#f2e7c6" stroke="#4a2a18" stroke-width="1.8" stroke-linejoin="round"/><path d="M60 76l10 8-12 4zM80 76l-10 8 12 4z" fill="#f8f0d8" stroke="#4a2a18" stroke-width="1.6" stroke-linejoin="round"/><path d="M70 86v30" stroke="#d6c79c" stroke-width="1.4"/><path d="M62 82l8 24 8-24" stroke="#2f7a45" stroke-width="2.2" fill="none"/><rect x="64.5" y="104" width="11" height="15" rx="2" fill="#fff" stroke="#2f7a45" stroke-width="1.8"/><rect x="67" y="108" width="6" height="5" fill="#9fd8a8"/><g transform="rotate(-8 84 110)"><rect x="74" y="96" width="22" height="28" rx="2" fill="#2d5aa0" stroke="#4a2a18" stroke-width="1.8"/><path d="M78 96v28" stroke="#1f3f73" stroke-width="2"/></g><circle cx="88" cy="114" r="5" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><g class="tf-wave"><path d="M48 88 34 62" fill="none" stroke="#4a2a18" stroke-width="11" stroke-linecap="round"/><path d="M48 88 34 62" fill="none" stroke="#ffd9b8" stroke-width="8" stroke-linecap="round"/><path transform="translate(32 55) scale(1 1)" d="M-8 6q-4-12 1-17l3 8q0-11 5-13l2 10q2-10 7-9l-1 10q4-6 8-4l-5 12q-8 8-20 3z" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.8" stroke-linejoin="round"/></g><rect x="64" y="64" width="12" height="12" rx="4" fill="#f0b48a"/><circle cx="47" cy="48" r="5" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><circle cx="93" cy="48" r="5" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.6"/><ellipse cx="70" cy="46" rx="24" ry="23" fill="#ffd9b8" stroke="#4a2a18" stroke-width="1.9"/><path d="M47 47q-3-14 4-20l-2-6 7 3q4-6 11-6l-1-5 6 5q7-3 13 1l1-5 4 7q8 4 9 14l4-1-2 6q3 6 0 12-3-9-8-12 0 5-2 7-4-8-10-10 1 4-1 7-5-6-12-6 1 4-2 6-4-4-9-3 0 4-3 7-3-4-7-4z" fill="#3b2414" stroke="#4a2a18" stroke-width="1.9" stroke-linejoin="round"/><path d="M56 25q8-4 16-2" stroke="#6b4a32" stroke-width="2" stroke-linecap="round" fill="none"/><path d="M58 37q5-3 9-1M73 36q5-2 9 1" stroke="#4a2a18" stroke-width="2" stroke-linecap="round" fill="none"/><g class="tf-eyes"><ellipse cx="61" cy="46" rx="5" ry="6" fill="#fff" stroke="#4a2a18" stroke-width="1.5"/><ellipse cx="61.4" cy="46.7" rx="3.8" ry="4.7" fill="#3b2214"/><ellipse cx="61.4" cy="47.2" rx="2" ry="2.5" fill="#1a0d06"/><circle cx="62.8" cy="44.3" r="1.8" fill="#fff"/><circle cx="59.6" cy="48.4" r=".8" fill="#fff"/><ellipse cx="79" cy="46" rx="5" ry="6" fill="#fff" stroke="#4a2a18" stroke-width="1.5"/><ellipse cx="79.4" cy="46.7" rx="3.8" ry="4.7" fill="#3b2214"/><ellipse cx="79.4" cy="47.2" rx="2" ry="2.5" fill="#1a0d06"/><circle cx="80.8" cy="44.3" r="1.8" fill="#fff"/><circle cx="77.6" cy="48.4" r=".8" fill="#fff"/></g><path d="M69 53q1.5 1.5 3 0" stroke="#c47a50" stroke-width="1.5" stroke-linecap="round" fill="none"/><ellipse cx="54" cy="55" rx="4.875" ry="3.0" fill="#ff8a80" opacity=".55"/><ellipse cx="86" cy="55" rx="4.875" ry="3.0" fill="#ff8a80" opacity=".55"/><path d="M60.9 57q9.1 11.2 18.2 0q-9.1 2.0999999999999996 -18.2 0z" fill="#8f2a1c" stroke="#4a2a18" stroke-width="1.7" stroke-linejoin="round"/><path d="M62.3 57.7q7.699999999999999 1.8199999999999998 15.399999999999999 0l-.6 1.8199999999999998q-7.279999999999999 1.54 -14.559999999999999 0z" fill="#fff"/><path d="M65.8 62.6q4.199999999999999 3.5 8.399999999999999 0q-4.199999999999999 -2.38 -8.399999999999999 0z" fill="#ff7d7d"/></g>';
const TF_BOARD='<svg class="tf-board" aria-hidden="true" viewBox="0 0 300 150"><rect x="6" y="6" width="288" height="128" rx="6" fill="url(#t-wood)"/><rect x="14" y="14" width="272" height="112" rx="3" fill="url(#t-board)"/><g class="chalk" fill="none" stroke="#f4f1e6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path class="ch1" d="M30 46l8-20 8 20M33 38h10M54 26v20h7a5 5 0 0 0 0-10h-7 6a5 5 0 0 0 0-10h-6M84 30a8 8 0 1 0 0 12"/><path class="ch2" d="M30 68h70"/><path class="ch3" d="m118 30 4 8 9 1-7 6 2 9-8-4-8 4 2-9-7-6 9-1z"/><path class="ch4" d="M30 92q10-10 20 0t20 0 20 0"/></g><text class="ch5" x="200" y="62" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="700" font-size="17" fill="#f4f1e6" opacity=".95">Good morning,</text><text class="ch6" x="200" y="86" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="700" font-size="17" fill="#ffd772">class!</text><rect x="10" y="132" width="280" height="8" rx="3" fill="#8a5527"/><rect x="40" y="128" width="16" height="5" rx="2" fill="#fff"/><rect x="62" y="129" width="12" height="4" rx="2" fill="#ffd772"/></svg>';
/** Teachers pick their character (female or male) in My profile. */
function tf_is_male(?int $id=null):bool{$id=$id??(int)(current_user()['id']??0);return $id&&(string)val('SELECT setting_value FROM settings WHERE setting_key=?',['teacher_char_'.$id])==='male';}
function tf_set_sex(int $id,string $sex):void{if(in_array($sex,['female','male'],true))q('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['teacher_char_'.$id,$sex]);}
function tf_teacher_art():string{return '<g class="tf-body"><image href="'.char_src(tf_is_male()?'teacher-male':'teacher-female').'" x="14" y="2" width="116" height="194" preserveAspectRatio="xMidYMax meet"/></g>';}
function tf_defs():string{return '<svg class="pf-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs>'.PF_DEFS.TF_DEFS.'</defs></svg>';}
function tf_in(array $ids):string{return $ids?implode(',',array_map('intval',$ids)):'0';}
function tf_days():array{$d=[];for($i=6;$i>=0;$i--)$d[]=date('Y-m-d',strtotime("-$i days"));return $d;}
function tf_per_day(string $sql,array $ids):array{$c=array_fill_keys(tf_days(),0);if(!$ids)return $c;foreach(rows(sprintf($sql,tf_in($ids)),[tf_days()[0]]) as $r){$k=substr((string)$r['d'],0,10);if(isset($c[$k]))$c[$k]=(int)$r['n'];}return $c;}
function tf_completions_by_day(array $ids):array{return tf_per_day("SELECT DATE(submitted_at) d,COUNT(*) n FROM activity_completion WHERE pupil_id IN(%s) AND status IN ('completed','approved') AND submitted_at>=? GROUP BY DATE(submitted_at)",$ids);}
function tf_active_by_day(array $ids):array{return tf_per_day('SELECT day d,COUNT(*) n FROM learning_days WHERE pupil_id IN(%s) AND day>=? GROUP BY day',$ids);}
function tf_needs_checkin(array $p,array $s):bool{return $p['active']&&(!$s['last_activity_date']||$s['last_activity_date']<date('Y-m-d',strtotime('-7 days')));}
function tf_first(array $u):string{return explode(' ',trim($u['name']))[0];}
function tf_ago(string $t):string{$s=time()-strtotime($t);if($s<60)return 'Just now';if($s<3600)return intdiv($s,60).' min ago';if($s<86400)return intdiv($s,3600).' '.(intdiv($s,3600)===1?'hour':'hours').' ago';if($s<172800)return 'Yesterday';return date('M j',strtotime($t));}

/* 1 · Welcome panel after sign-in */
function teacher_welcome(array $u):string{
 if(empty($_SESSION['teacher_welcome']))return '';unset($_SESSION['teacher_welcome']);
 $tid=(int)$u['id'];$h=(int)date('G');$greet=$h<12?'Good morning':($h<18?'Good afternoon':'Good evening');
 $today=(int)val('SELECT COUNT(DISTINCT d.pupil_id) FROM learning_days d JOIN teacher_pupils t ON t.pupil_id=d.pupil_id WHERE t.teacher_id=? AND d.day=?',[$tid,date('Y-m-d')]);
 $check=(int)val("SELECT COUNT(*) FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id LEFT JOIN pupil_streaks s ON s.pupil_id=t.pupil_id WHERE t.teacher_id=? AND u.active=1 AND (s.last_activity_date IS NULL OR s.last_activity_date<?)",[$tid,date('Y-m-d',strtotime('-7 days'))]);
 $new=(int)val("SELECT COUNT(*) FROM activity_completion c JOIN teacher_pupils t ON t.pupil_id=c.pupil_id WHERE t.teacher_id=? AND c.status IN ('completed','approved') AND c.submitted_at>=?",[$tid,date('Y-m-d H:i:s',strtotime('-1 day'))]);
 $chip=fn($n,$one,$many,$tone)=>'<span class="pw-stat tf-chip"><i class="tf-dot tf-dot-'.$tone.'"></i><b>'.$n.'</b> '.($n===1?$one:$many).'</span>';
 return '<div class="pw-overlay" data-welcome role="dialog" aria-modal="true" aria-labelledby="tf-welcome-title"><div class="pw-panel tf-panel"><button type="button" class="pw-close" data-welcome-close aria-label="Close">'.icon('close').'</button><div class="tf-art" aria-hidden="true">'.TF_BOARD.'<svg class="tf-teacher" viewBox="20 6 104 190">'.tf_teacher_art().'</svg></div><span class="pw-eyebrow">'.e(strtoupper($greet)).'</span><h2 id="tf-welcome-title">Welcome back, '.e(tf_first($u)).'!</h2><p class="pw-msg">Here is your class at a glance.</p><div class="pw-stats">'.$chip($today,'pupil learned today','pupils learned today','g').$chip($check,'needs a check-in','need a check-in','y').$chip($new,'new activity','new activities','b').'</div><div class="pw-actions"><a class="btn primary" href="?page=qcheck">'.icon('chart').'See class results</a><button type="button" class="btn secondary" data-welcome-close>Go to Home</button></div></div></div>';
}

/* 2 · Stat cards with count-up and 7-day trend */
function tf_spark(array $vals,string $tone):string{
 $max=max(1,max($vals));$n=count($vals);$pts=[];foreach(array_values($vals) as $i=>$v)$pts[]=round($i*100/($n-1),1).' '.round(26-$v/$max*22,1);
 return '<svg class="tf-spark tf-'.$tone.'" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><path d="M'.implode(' L',$pts).'" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
}
function tf_stats(array $ids,int $completed,int $steps):string{
 $tile=fn($ic,$tone,$n,$label,$spark)=>'<div class="tstat tf-stat"><span class="tf-ic tf-ic-'.$tone.'">'.icon($ic).'</span><div><strong data-count="'.$n.'">'.$n.'</strong><span>'.$label.'</span></div>'.$spark.'</div>';
 return '<div class="tdash-stats tf-stats">'.$tile('people','g',count($ids),'Pupils',$ids?tf_spark(tf_active_by_day($ids),'g'):'').$tile('check','y',$completed,'Completed activities',$ids?tf_spark(tf_completions_by_day($ids),'y'):'').$tile('book','b',$steps,'Learning steps','').'</div>';
}

/* 3 · Class activity chart, last 7 days */
function tf_chart(array $ids):string{
 $c=tf_completions_by_day($ids);$max=max(1,max($c));$total=array_sum($c);$w=46;$gap=14;$x=10;$bars='';$labels='';$i=0;
 foreach($c as $day=>$n){$h=$n?max(6,round($n/$max*112)):3;$today=$day===date('Y-m-d');
  $bars.='<g class="tf-bar'.($today?' tf-today':'').'"><rect x="'.$x.'" y="'.(130-$h).'" width="'.$w.'" height="'.$h.'" rx="8"/><text x="'.($x+$w/2).'" y="'.(124-$h).'" text-anchor="middle">'.$n.'</text></g>';
  $labels.='<text x="'.($x+$w/2).'" y="150" text-anchor="middle" class="tf-day">'.($today?'Today':date('D',strtotime($day))).'</text>';$x+=$w+$gap;$i++;}
 return '<section class="card tf-chart"><div class="section-heading"><h2>Class activity · last 7 days</h2><strong class="tf-total"><span data-count="'.$total.'">'.$total.'</span> '.($total===1?'activity':'activities').'</strong></div><svg viewBox="0 0 430 156" role="img" aria-label="Completed activities per day for the last 7 days, '.$total.' in total"><path d="M4 131H426" stroke="#e2ebe0" stroke-width="2"/>'.$bars.$labels.'</svg></section>';
}

/* 4 · Class reading tree, one fruit per pupil */
function tf_tree(array $pupils,array $stats,array $badgeWeek):string{
 $slots=[[108,86],[140,64],[178,74],[200,110],[120,118],[160,104],[92,124],[186,40],[214,82],[150,30],[132,92],[170,128],[100,60],[226,112],[118,40],[196,140],[146,140],[76,100],[236,72],[166,52],[128,140],[210,56],[84,78],[150,82]];
 $s='<svg viewBox="0 0 300 220" class="tf-tree-svg" role="img" aria-label="Class reading tree with '.count($pupils).' pupils"><ellipse cx="150" cy="210" rx="110" ry="9" fill="#2a5a2a" opacity=".16"/><path d="M140 210c4-30 2-60-6-86l14 10c4-14 10-20 18-24-6 12-8 22-6 34 4-6 10-10 18-12-10 10-16 22-18 38-1 14 0 28 2 40z" fill="#8a5527"/>';
 foreach([[150,82,64,'#4cae5e'],[98,104,44,'#3e9a4a'],[204,102,46,'#3e9a4a'],[124,56,40,'#5cbf6a'],[182,58,42,'#5cbf6a'],[150,40,34,'#6fcf7b']] as [$cx,$cy,$r,$c])$s.='<circle class="tf-can" cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.$c.'"/>';
 $s.='<path d="M110 60q20-20 40-14" stroke="#9ee6a6" stroke-width="5" stroke-linecap="round" fill="none" opacity=".6"/>';$n=[0,0,0];
 foreach(array_slice($pupils,0,count($slots)) as $i=>$p){[$x,$y]=$slots[$i];$st=$stats[(int)$p['id']];$k=tf_needs_checkin($p,$st)?'a':(isset($badgeWeek[(int)$p['id']])?'y':'g');$n[['g'=>0,'y'=>1,'a'=>2][$k]]++;
  [$a,$b]=['g'=>['#ff6f61','#d6452f'],'y'=>['#ffd23f','#e09a00'],'a'=>['#ffb35c','#e2561c']][$k];
  $s.='<a href="?page=pupil&amp;id='.(int)$p['id'].'" class="tf-fruit tf-f'.$k.' tf-fd'.($i%12).'"><title>'.e($p['name']).' · '.['g'=>'Learning well','y'=>'New badge this week','a'=>'Needs a check-in'][$k].'</title><circle cx="'.$x.'" cy="'.$y.'" r="8" fill="'.$a.'" stroke="'.$b.'" stroke-width="1.5"/><path d="M'.$x.' '.($y-8).'q2-5 6-5" stroke="#5a3a26" stroke-width="1.6" fill="none"/><circle cx="'.($x-3).'" cy="'.($y-3).'" r="2.2" fill="#fff" opacity=".6"/></a>';}
 $more=count($pupils)>count($slots)?'<p class="tf-more">Showing '.count($slots).' of '.count($pupils).' pupils</p>':'';
 return '<section class="card tf-tree"><div class="section-heading"><h2>Class reading tree</h2></div><div class="tf-tree-stage">'.$s.'</svg><ul class="tf-legend"><li><i class="tf-dot tf-dot-r"></i>Learning well · '.$n[0].'</li><li><i class="tf-dot tf-dot-y"></i>New badge this week · '.$n[1].'</li><li><i class="tf-dot tf-dot-o"></i>Needs a check-in · '.$n[2].'</li></ul></div>'.$more.'<p class="tf-hint">Each fruit is a pupil. Tap one to open their progress.</p></section>';
}

/* 5 · Check-in reminder */
function tf_checkin(array $need):string{
 if(!$need)return '';$names=array_map(fn($p)=>tf_first($p),array_slice($need,0,3));$c=count($need);
 /* One clear button per pupil (picture, first name, arrow) that opens their progress; the rest are counted. */
 $av='';foreach(array_slice($need,0,3) as $p)$av.='<a class="tf-ci" href="?page=pupil&amp;id='.(int)$p['id'].'" title="See '.e($p['name']).'’s progress">'.avatar($p).'<span>'.e(tf_first($p)).'</span>'.icon('arrow').'</a>';
 if($c>3)$av.='<a class="tf-ci tf-ci-more" href="?page=progress">+'.($c-3).' more'.icon('arrow').'</a>';
 return '<section class="tf-alert" role="status"><svg viewBox="0 0 40 40" class="tf-bell" aria-hidden="true"><path d="M20 5a3 3 0 0 1 3 3v1a10 10 0 0 1 7 10v6l3 4H7l3-4v-6a10 10 0 0 1 7-10V8a3 3 0 0 1 3-3z" fill="url(#pf-gold)" stroke="#c98200" stroke-width="1.5"/><circle class="tf-clap" cx="20" cy="33" r="3.5" fill="#c98200"/><path d="M14 16a7 7 0 0 1 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7"/></svg><div><strong>'.$c.' '.($c===1?'pupil needs':'pupils need').' a check-in</strong><span>'.e(implode(', ',$names).($c>3?' and '.($c-3).' more':'')).' · no learning in the past 7 days</span></div><div class="tf-cis">'.$av.'</div></section>';
}

/* 6 · Pupil milestones feed */
function tf_feed(array $ids):string{
 if(!$ids)return '';$in=tf_in($ids);$since=date('Y-m-d H:i:s',strtotime('-14 days'));
 $items=[];foreach(rows("SELECT u.name,u.id,b.title,b.rule_type,b.threshold_value,p.earned_at t FROM pupil_badges p JOIN badges b ON b.id=p.badge_id JOIN users u ON u.id=p.pupil_id WHERE p.pupil_id IN($in) AND p.earned_at>=? ORDER BY p.earned_at DESC LIMIT 6",[$since]) as $r)$items[]=['t'=>$r['t'],'html'=>'<span class="tf-fi-ic">'.pf_medal($r['rule_type'],34,true,true,(int)$r['threshold_value']).'</span><span><b>'.e($r['name']).'</b> earned the <b>'.e($r['title']).'</b> badge'];
 foreach(rows("SELECT u.name,l.subtitle,m.level_id,p.completed_at t FROM pupil_progress p JOIN lessons l ON l.id=p.lesson_id JOIN modules m ON m.id=l.module_id JOIN users u ON u.id=p.pupil_id WHERE p.pupil_id IN($in) AND p.completed_at>=? ORDER BY p.completed_at DESC LIMIT 6",[$since]) as $r)$items[]=['t'=>$r['t'],'html'=>'<span class="tf-fi-ic tf-ic tf-ic-b">'.icon('star').'</span><span><b>'.e($r['name']).'</b> finished <b>'.e(level_label((int)$r['level_id']).' · '.$r['subtitle']).'</b>'];
 usort($items,fn($a,$b)=>strcmp((string)$b['t'],(string)$a['t']));$items=array_slice($items,0,5);
 $out='<section class="card tf-feed"><div class="section-heading"><h2>Pupil milestones</h2><span class="muted">Last 2 weeks</span></div>';
 if(!$items)return $out.'<p class="tf-empty">'.icon('star').'New badges and finished lessons will appear here.</p></section>';
 $out.='<ul>';foreach($items as $i=>$it){$new=strtotime((string)$it['t'])>=time()-86400;$out.='<li class="tf-fi'.($new?' tf-new':'').'">'.$it['html'].'<small>'.e(tf_ago((string)$it['t'])).'</small></span>'.($new?'<em>NEW</em>':'').'</li>';}
 return $out.'</ul></section>';
}

/* 7 · Level progress bar for a class list row */
function tf_progress(int $pid,int $level,bool $warn):string{
 $c=one('SELECT COUNT(*) total,COUNT(p.completed_at) done FROM lessons l JOIN modules m ON m.id=l.module_id LEFT JOIN pupil_progress p ON p.lesson_id=l.id AND p.pupil_id=? WHERE m.level_id=? AND l.published=1'.GRADE_SQL,[$pid,$level,pupil_grade($pid)]);
 $t=max(1,(int)$c['total']);$d=(int)$c['done'];$pct=(int)round($d/$t*100);
 return '<div class="tf-prog'.($warn||$pct<10?' tf-prog-warn':'').'"><progress data-pf-fill max="'.$t.'" value="'.$d.'" aria-label="'.$d.' of '.$t.' lessons in the starting level"></progress><small>'.$d.' / '.$t.' lessons · '.$pct.'%</small></div>';
}

/* 8 · Feedback saved toast */
function tf_feedback_toast():string{
 if(empty($_SESSION['tf_feedback']))return '';$n=(string)$_SESSION['tf_feedback'];unset($_SESSION['tf_feedback']);
 return '<div class="tf-toast" role="status"><svg viewBox="0 0 60 60" aria-hidden="true"><circle cx="30" cy="30" r="25" fill="none" stroke="#7cc576" stroke-width="5"/><path d="M19 31l8 8 15-16" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg><span><b>Feedback saved</b>'.e($n).' will see it on their next visit.</span></div>';
}

/* 9 · School sky for the teacher banner */
function tf_sky():string{
 $h=(int)date('G');$p=$h>=5&&$h<12?'m':($h>=12&&$h<17?'a':'e');
 $s='<svg class="identity-art pf-sky tf-sky pf-sky-'.$p.'" viewBox="0 0 320 110" preserveAspectRatio="xMidYMax slice" role="img" aria-label="Your school under '.($p==='m'?'a morning':($p==='a'?'an afternoon':'an evening')).' sky"><rect width="320" height="110" fill="url(#pf-sky'.$p.')"/>';
 $cloud=fn($x,$y,$sc,$c)=>'<g class="pf-drift"><path transform="translate('.$x.' '.$y.') scale('.$sc.')" d="M0 14a10 10 0 0 1 16-9 13 13 0 0 1 24 3 8 8 0 0 1 2 16H4a9 9 0 0 1-4-10z" fill="'.$c.'"/></g>';
 if($p==='m')$s.='<circle cx="270" cy="30" r="34" fill="url(#pf-glow)"/><circle cx="270" cy="30" r="15" fill="url(#pf-sun)"/>'.$cloud(40,18,.8,'#fff');
 elseif($p==='a')$s.='<g class="pf-sink"><circle cx="276" cy="66" r="40" fill="url(#pf-glow)"/><circle cx="276" cy="66" r="18" fill="#ffc35a"/></g>'.$cloud(30,18,.9,'#ffd1c2');
 else{foreach([[20,20],[60,40],[100,14],[230,50],[300,20],[36,60],[250,14]] as $i=>[$x,$y])$s.='<circle class="pf-tw pf-t'.($i%3).'" cx="'.$x.'" cy="'.$y.'" r="1.5" fill="#fff"/>';$s.='<circle cx="276" cy="30" r="14" fill="url(#pf-moon)"/><circle cx="283" cy="25" r="12" fill="#1d2b5c"/>';}
 $g=['m'=>'#8fd27c','a'=>'#b98a6a','e'=>'#1f3049'][$p];$wall=['m'=>'url(#t-school)','a'=>'#f4d6c4','e'=>'#3a4a72'][$p];$win=['m'=>'#8fd3ff','a'=>'#ffe08a','e'=>'#ffd772'][$p];$tr=$p==='e'?['#132036','#0f1a2e']:['#4c9a52','#5cb85c'];
 $s.='<path d="M-10 96q170-24 340 0v20H-10z" fill="'.$g.'"/><rect x="120" y="46" width="110" height="52" fill="'.$wall.'" stroke="rgba(0,0,0,.15)"/><path d="M114 48l61-24 61 24z" fill="#d6452f"/><rect x="164" y="74" width="22" height="24" fill="#8a5527"/>';
 foreach([130,150,196,214] as $x)$s.='<rect class="'.($p==='e'?'pf-window':'').'" x="'.$x.'" y="58" width="12" height="11" fill="'.$win.'" stroke="#fff" stroke-width="1.5"/>';
 return $s.'<path d="M175 24V6" stroke="#6b5a45" stroke-width="2"/><path class="tf-flag" d="M176 6h16l-4 4 4 4h-16z" fill="#2f74c4"/><rect x="250" y="70" width="3" height="20" fill="#7a5230"/><circle cx="251" cy="66" r="11" fill="'.$tr[0].'"/><rect x="90" y="74" width="3" height="18" fill="#7a5230"/><circle cx="91" cy="70" r="10" fill="'.$tr[1].'"/></svg>';
}

/** Top 10 pupils (v172) on Teacher Home, for the grade and section chosen above it. Ranked by BULIG's average score on
    checked activities (each activity counts once, its best try), then by activities done, then by XP. A pupil needs
    at least one checked activity to be listed. The board is one picture (an SVG drawn like the reading ladder: sky,
    hills and a podium of level blocks), so it can be saved as a picture (leaderboard.js) or printed. Phones also get
    a simple list under it. Filters: one level (only that level's activities count) or all levels, and all time, this
    month or this week (?lb_level=, ?lb_period=). */
const TF_TOP_PERIODS=['all'=>'All time','month'=>'This month','week'=>'This week'];
function tf_top_rows(array $ids,int $level=0,string $period='all'):array{
 if(!$ids)return [];$since=['month'=>date('Y-m-01 00:00:00'),'week'=>date('Y-m-d 00:00:00',strtotime('monday this week'))][$period]??null;
 $w=($level?' AND m.level_id='.$level:'').($since?' AND c.submitted_at>=?':'');
 return rows('SELECT u.id,u.name,u.avatar_path,u.role,pa.level_id,ROUND(100*SUM(c.score)/SUM(c.max_score)) pct,COUNT(*) done,
   (SELECT COALESCE(SUM(x.amount),0) FROM xp_transactions x WHERE x.pupil_id=u.id) xp
  FROM activity_completion c JOIN users u ON u.id=c.pupil_id AND u.active=1 LEFT JOIN pupil_level_assignments pa ON pa.pupil_id=u.id
  JOIN activities ac ON ac.id=c.activity_id JOIN lessons l ON l.id=ac.lesson_id JOIN modules m ON m.id=l.module_id
  WHERE c.pupil_id IN('.tf_in($ids).") AND c.status IN ('completed','approved') AND c.max_score>0".$w."
  GROUP BY u.id,u.name,u.avatar_path,u.role,pa.level_id ORDER BY pct DESC,done DESC,xp DESC,u.name LIMIT 10",$since?[$since]:[]);
}
/** The filter row: level and period, keeping the grade and section chosen above. */
function tf_top_filter(int $level,string $period):string{
 $keep='';foreach(['grade_level','section_id'] as $k)if(isset($_GET[$k]))$keep.='<input type="hidden" name="'.$k.'" value="'.(int)$_GET[$k].'">';
 $lv='<option value="0">All levels</option>';foreach(rows('SELECT id FROM bulig_levels WHERE published=1 ORDER BY '.level_order_sql('id')) as $l)$lv.='<option value="'.(int)$l['id'].'"'.($level===(int)$l['id']?' selected':'').'>'.e(level_label((int)$l['id'])).'</option>';
 $pd='';foreach(TF_TOP_PERIODS as $k=>$t)$pd.='<option value="'.$k.'"'.($period===$k?' selected':'').'>'.$t.'</option>';
 return '<form method="get" action="#tf-top" class="lb-filter"><input type="hidden" name="page" value="dashboard">'.$keep
  .'<label>Level<select name="lb_level" data-autosubmit>'.$lv.'</select></label><label>Time<select name="lb_period" data-autosubmit>'.$pd.'</select></label><button class="btn secondary pg-go">Show</button></form>';
}
function tf_leaderboard(array $ids,string $label=''):string{
 $level=max(0,(int)($_GET['lb_level']??0));$period=isset(TF_TOP_PERIODS[$_GET['lb_period']??''])?(string)$_GET['lb_period']:'all';
 $rows=tf_top_rows($ids,$level,$period);$label=implode(' · ',array_filter([$label,$level?level_label($level):'All levels',$period!=='all'?TF_TOP_PERIODS[$period]:'']));
 $h='<section class="card tf-top" id="tf-top"><div class="section-heading"><h2>'.icon('star').'Top 10 pupils</h2><small>By BULIG’s average score</small></div>'.tf_top_filter($level,$period);
 if(!$rows)return $h.'<p class="muted">'.($level||$period!=='all'?'No checked activities here yet. Choose another level or time.':'The board fills in when pupils finish activities that BULIG checks.').'</p></section>';
 $h.='<div class="lb-tools"><button type="button" class="btn secondary" data-lb-save>'.icon('download').'Save as picture</button><button type="button" class="btn secondary" data-lb-print>'.icon('print').'Print</button></div>';
 $h.='<div class="lb-art" id="lb-art">'.tf_leaderboard_svg($rows,$label).'</div><ol class="lb-list">';
 foreach($rows as $i=>$r){$n=$i+1;
  $h.='<li class="lb-row'.($n<=3?' lb-p'.$n:'').'"><span class="lb-n">'.$n.'</span>'.avatar($r).'<a class="lb-name" href="?page=pupil&amp;id='.(int)$r['id'].'">'.e($r['name']).'</a>'
   .'<small class="lb-meta">'.e($r['level_id']?level_label((int)$r['level_id']):'No level yet').' · '.(int)$r['done'].' '.((int)$r['done']===1?'activity':'activities').' · '.number_format((int)$r['xp']).' XP</small>'
   .'<b class="lb-pct">'.(int)$r['pct'].'%</b></li>';}
 return $h.'</ol><script src="'.asset_url('leaderboard.js').'" defer></script></section>';
}
/** The gold crown on the 1st place (base centred on $x,$y): five points with gold balls, a band with jewels, a shine
    and two sparkles; tilted a little, like a crown just put on. */
function tf_crown(float $x,float $y):string{
 $tips=[[-40,-34],[-20,-46],[0,-56],[20,-46],[40,-34]];
 $g='<g transform="translate('.$x.' '.$y.') rotate(-8)"><ellipse cx="0" cy="4" rx="40" ry="5" fill="#000" opacity=".12"/>'
  .'<path d="M-36 0 L-40 -34 L-29 -13 L-20 -46 L-10 -19 L0 -56 L10 -19 L20 -46 L29 -13 L40 -34 L36 0Z" fill="url(#lbCrown)" stroke="#b07d00" stroke-width="2.5" stroke-linejoin="round"/>'
  .'<path d="M-33 -4 L-36 -27 L-28 -11 L-20 -38 L-14 -19Z" fill="#fff" opacity=".35"/>';
 foreach($tips as [$tx,$ty])$g.='<circle cx="'.$tx.'" cy="'.$ty.'" r="5.5" fill="#fff3b0" stroke="#b07d00" stroke-width="2"/>';
 $g.='<rect x="-39" y="-12" width="78" height="16" rx="5" fill="url(#lbCrownBand)" stroke="#b07d00" stroke-width="2.5"/>'
  .'<ellipse cx="0" cy="-4" rx="6" ry="5" fill="#e2374a" stroke="#fff" stroke-width="1.5"/><ellipse cx="-20" cy="-4" rx="4.2" ry="3.6" fill="#2a6cc0" stroke="#fff" stroke-width="1.2"/><ellipse cx="20" cy="-4" rx="4.2" ry="3.6" fill="#2fa04f" stroke="#fff" stroke-width="1.2"/>'
  .'<path d="M0 -36 l3 5 -3 5 -3 -5Z" fill="#e2374a" stroke="#fff" stroke-width="1"/>'
  .'<circle cx="-2" cy="-6" r="1.6" fill="#fff" opacity=".8"/></g>';
 $spark=fn($sx,$sy,$k,$c)=>'<path transform="translate('.$sx.' '.$sy.') scale('.$k.')" d="M0 -10 Q1.5 -1.5 10 0 Q1.5 1.5 0 10 Q-1.5 1.5 -10 0 Q-1.5 -1.5 0 -10Z" fill="'.$c.'"/>';
 return $g.$spark($x-58,$y-44,1.1,'#ffd23f').$spark($x+56,$y-58,.8,'#fff').$spark($x+50,$y-20,.6,'#ffd23f');
}
/** The board picture: 1200 x 820, drawn with the reading ladder's own scenery (app.js, "My reading board"): the meadow
    and sky, the smiling sun, mountains, clouds, birds, trees, flowers and books, the Reading Castle, the 3D level
    blocks on a sandy stone base, a wooden sign and ribbon medals. */
function tf_leaderboard_svg(array $rows,string $label):string{
 $x=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_XML1,'UTF-8');
 $cut=fn($s,$n)=>mb_strlen($s)>$n?mb_substr($s,0,$n-1).'…':$s;
 $img=fn($r)=>$x(($r['avatar_path']??'')!==''&&local_image_file((string)$r['avatar_path'])?$r['avatar_path']:'assets/design/default-avatar.svg');
 $W=1200;$H=820;$C=RB_COLORS;
 /* the ladder's scenery pieces */
 $tree=fn($tx,$ty,$k,$c1)=>'<g transform="translate('.$tx.' '.$ty.') scale('.$k.')"><ellipse cx="4" cy="20" rx="26" ry="7" fill="#2a5a2a" opacity=".18"/><rect x="-6" y="-8" width="12" height="28" rx="4" fill="#8a5527"/><circle cx="0" cy="-30" r="26" fill="#3e9a4a"/><circle cx="-16" cy="-16" r="18" fill="#3e9a4a"/><circle cx="16" cy="-16" r="18" fill="#3e9a4a"/><circle cx="-4" cy="-34" r="20" fill="'.$c1.'"/><circle cx="-14" cy="-20" r="12" fill="'.$c1.'"/><circle cx="-10" cy="-42" r="8" fill="#fff" opacity=".22"/></g>';
 $pine=fn($tx,$ty,$k)=>'<g transform="translate('.$tx.' '.$ty.') scale('.$k.')"><ellipse cx="3" cy="16" rx="20" ry="6" fill="#2a5a2a" opacity=".18"/><rect x="-4" y="2" width="8" height="14" fill="#7a4a22"/><path d="M0 -58 L-22 -20 H22Z" fill="#2f8a4b"/><path d="M0 -40 L-26 4 H26Z" fill="#267a40"/></g>';
 $flower=function($fx,$fy,$c){$p='';foreach([0,72,144,216,288] as $an)$p.='<circle cx="'.round(cos(deg2rad($an))*5,1).'" cy="'.round(-18+sin(deg2rad($an))*5,1).'" r="4.2" fill="'.$c.'"/>';return '<g transform="translate('.$fx.' '.$fy.')"><path d="M0 0v-14" stroke="#3e9a4a" stroke-width="2.5"/>'.$p.'<circle cx="0" cy="-18" r="3" fill="#ffd23f"/></g>';};
 $bush=fn($bx,$by)=>'<g transform="translate('.$bx.' '.$by.')"><circle cx="-14" cy="0" r="13" fill="#3e9a4a"/><circle cx="6" cy="-5" r="16" fill="#4cae5e"/><circle cx="23" cy="2" r="11" fill="#3e9a4a"/><circle cx="2" cy="-8" r="3" fill="#e2463a"/><circle cx="14" cy="-2" r="3" fill="#e2463a"/></g>';
 $books=fn($bx,$by)=>'<g transform="translate('.$bx.' '.$by.')"><rect x="-22" y="-8" width="44" height="10" rx="2" fill="#d6452f"/><rect x="-18" y="-18" width="40" height="10" rx="2" fill="#2a6cc0"/><rect x="-20" y="-28" width="38" height="10" rx="2" fill="#f6c02c"/></g>';
 $shroom=fn($mx,$my)=>'<g transform="translate('.$mx.' '.$my.')"><rect x="-3" y="-8" width="6" height="10" rx="2" fill="#fff4dc"/><path d="M-11 -8q11-16 22 0z" fill="#e2463a"/><circle cx="-4" cy="-12" r="2" fill="#fff"/></g>';
 $cloud=fn($cx,$cy,$k)=>'<path transform="translate('.$cx.' '.$cy.') scale('.$k.')" d="M0 14a10 10 0 0 1 16-9 13 13 0 0 1 24 3 8 8 0 0 1 2 16H4a9 9 0 0 1-4-10z" fill="#fff"/>';
 $ribbon=function($cx,$cy,$k,$col){$pts='';for($i=0;$i<32;$i++){$r=($i%2?20:24)*$k;$t=$i*M_PI/16;$pts.=round($cx+$r*cos($t),1).','.round($cy+$r*sin($t),1).' ';}
  return '<path d="M'.($cx-12*$k).' '.($cy+10*$k).'l-8 '.(26*$k).' 8-5 6 8 6-'.(28*$k).'z" fill="'.$col[1].'"/><path d="M'.($cx+12*$k).' '.($cy+10*$k).'l8 '.(26*$k).'-8-5-6 8-6-'.(28*$k).'z" fill="'.$col[0].'"/><polygon points="'.trim($pts).'" fill="#ffd23f" stroke="#c99400" stroke-width="1.5"/><circle cx="'.$cx.'" cy="'.$cy.'" r="'.(15*$k).'" fill="'.$col[0].'" stroke="#fff" stroke-width="2"/>'
   .'<path d="M'.$cx.' '.($cy-9*$k).'l'.(2.6*$k).' '.(5.6*$k).' '.(6*$k).' '.(.7*$k).'-'.(4.5*$k).' '.(4.1*$k).' '.(1.2*$k).' '.(6*$k).'-'.(5.3*$k).'-'.(3*$k).'-'.(5.3*$k).' '.(3*$k).' '.(1.2*$k).'-'.(6*$k).'-'.(4.5*$k).'-'.(4.1*$k).' '.(6*$k).'-'.(.7*$k).'z" fill="#fff"/>';};
 /* a 3D level block, exactly like the ladder's */
 $block=fn($bx,$by,$bw,$bh,$col)=>'<path d="M'.$bx.' '.$by.'L'.($bx+12).' '.($by-10).'H'.($bx+$bw+12).'L'.($bx+$bw).' '.$by.'Z" fill="'.$col[0].'" opacity=".75"/><rect x="'.$bx.'" y="'.$by.'" width="'.$bw.'" height="'.$bh.'" fill="'.$col[0].'"/><path d="M'.($bx+$bw).' '.$by.'L'.($bx+$bw+12).' '.($by-10).'V'.($by+$bh-10).'L'.($bx+$bw).' '.($by+$bh).'Z" fill="'.$col[1].'"/><rect x="'.$bx.'" y="'.$by.'" width="'.$bw.'" height="'.round($bh*.18).'" fill="#fff" opacity=".18"/>';
 $g='<svg id="lb-svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 '.$W.' '.$H.'" width="'.$W.'" height="'.$H.'" role="img" aria-label="Top 10 pupils leaderboard" font-family="Poppins, Segoe UI, Arial, sans-serif">'
  .'<defs><linearGradient id="lbSky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#bfe8ff"/><stop offset="1" stop-color="#eaf8ff"/></linearGradient>'
  .'<linearGradient id="lbGrass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d9f2c4"/><stop offset=".5" stop-color="#c8eab0"/><stop offset="1" stop-color="#b7e09c"/></linearGradient>'
  .'<linearGradient id="lbStone" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff1cc"/><stop offset="1" stop-color="#f2d9a0"/></linearGradient>'
  .'<linearGradient id="lbMtn" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#a8d7a0"/><stop offset="1" stop-color="#cde9c0"/></linearGradient>'
  .'<linearGradient id="lbCastle" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff8e8"/><stop offset="1" stop-color="#ead9b4"/></linearGradient>'
  .'<radialGradient id="lbSun" cx="40%" cy="35%" r="65%"><stop offset="0" stop-color="#fff6b0"/><stop offset=".6" stop-color="#ffd23f"/><stop offset="1" stop-color="#f6a700"/></radialGradient>'
  .'<pattern id="lbTuft" width="60" height="44" patternUnits="userSpaceOnUse"><path d="M10 30q2-8 4 0q2-10 4 0M40 12q2-7 4 0q2-9 4 0" stroke="#9fd284" stroke-width="2" fill="none" stroke-linecap="round"/></pattern>'
  .'<linearGradient id="lbCrown" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff1a8"/><stop offset=".45" stop-color="#ffd23f"/><stop offset="1" stop-color="#e9a90f"/></linearGradient>'
  .'<linearGradient id="lbCrownBand" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f6c02c"/><stop offset="1" stop-color="#c99400"/></linearGradient>'
  .'<clipPath id="lbC0" clipPathUnits="objectBoundingBox"><circle cx=".5" cy=".5" r=".5"/></clipPath></defs>';
 /* meadow, sky, sun, mountains, clouds, birds */
 $g.='<rect width="'.$W.'" height="'.$H.'" fill="url(#lbGrass)"/><rect width="'.$W.'" height="'.$H.'" fill="url(#lbTuft)" opacity=".7"/><rect width="'.$W.'" height="200" fill="url(#lbSky)"/>';
 $g.='<g transform="translate(96 84) scale(1.1)">';for($k=0;$k<12;$k++)$g.='<rect x="-3" y="-56" width="6" height="16" rx="3" fill="#ffd23f" transform="rotate('.($k*30).')"/>';
 $g.='<circle r="32" fill="url(#lbSun)"/><circle cx="-10" cy="-4" r="3.4" fill="#5a3a12"/><circle cx="10" cy="-4" r="3.4" fill="#5a3a12"/><path d="M-11 8q11 10 22 0" stroke="#5a3a12" stroke-width="3" fill="none" stroke-linecap="round"/><circle cx="-18" cy="6" r="5" fill="#ff8fab" opacity=".6"/><circle cx="18" cy="6" r="5" fill="#ff8fab" opacity=".6"/></g>';
 $g.='<path d="M0 200 L144 100 L288 175 L456 82 L624 188 L792 112 L960 178 L1104 106 L1200 162 V225 H0Z" fill="url(#lbMtn)"/><path d="M438 100 L456 82 L474 100Z M778 126 L792 112 L806 126Z M1090 120 L1104 106 L1118 120Z" fill="#fff"/><path d="M0 212 Q300 162 600 200 T1200 194 V262 H0Z" fill="#c8eab0"/>';
 $g.=$cloud(230,24,1.6).$cloud(880,40,1.3).$cloud(1010,128,.9);
 $g.='<g transform="translate(300 60)">'.implode('',array_map(fn($b)=>'<path d="M'.$b[0].' '.$b[1].'q5-5 10 0q5-5 10 0" stroke="#3b5d74" stroke-width="2" fill="none" stroke-linecap="round"/>',[[0,10],[22,0],[40,14]])).'</g>';
 /* the Reading Castle on the hill (the goal of the ladder) */
 $g.='<g transform="translate(1086 168) scale(.78)"><path d="M-90 70 Q0 30 90 70Z" fill="#9fd284"/><rect x="-56" y="-10" width="112" height="80" fill="url(#lbCastle)" stroke="#c9b48a" stroke-width="2"/><rect x="-70" y="-40" width="34" height="110" fill="url(#lbCastle)" stroke="#c9b48a" stroke-width="2"/><rect x="36" y="-40" width="34" height="110" fill="url(#lbCastle)" stroke="#c9b48a" stroke-width="2"/><path d="M-74 -40 L-53 -72 L-32 -40Z M32 -40 L53 -72 L74 -40Z" fill="#2a6cc0"/><path d="M-60 -10 L0 -50 L60 -10Z" fill="#d6452f"/><rect x="-14" y="30" width="28" height="40" rx="14" fill="#8a5527"/><path d="M0 -50 V-82" stroke="#6b5a45" stroke-width="3"/><path d="M1 -82h24l-6 6 6 6H1z" fill="#f6c02c"/></g>';
 /* the title: a wooden sign like the ladder's START HERE sign */
 $sub=trim(($label!==''?$label.' · ':'').date('F j, Y'));
 $g.='<rect x="430" y="118" width="10" height="70" fill="#8a5527"/><rect x="760" y="118" width="10" height="70" fill="#8a5527"/>'
  .'<rect x="320" y="16" width="560" height="112" rx="18" fill="#fff8e8" stroke="#b08a4e" stroke-width="4"/><text x="600" y="70" text-anchor="middle" font-size="44" font-weight="800" fill="#17633a" letter-spacing="1">TOP 10 PUPILS</text>'
  .'<text x="600" y="104" text-anchor="middle" font-size="16" font-weight="700" fill="#6b5a45">'.$x($cut($sub,64)).'</text>'
  .'<image href="assets/bulig-logo.png" x="1000" y="12" width="182" height="76" preserveAspectRatio="xMidYMid meet"/>';
 /* scenery around the podium and the list */
 $g.=$pine(48,330,.85).$tree(660,300,.62,'#6fcf7b').$pine(40,470,.7).$books(668,420).$bush(664,560);
 $fl=['#ff8fab','#9ad0ff','#ffd23f','#c3a2ff','#ff9f5a'];
 foreach(range(0,15) as $k){$fx=150+$k*68;$fy=760+(($k*37)%3)*16;$g.=$k%4===0?$bush($fx,$fy):($k%5===2?$shroom($fx,$fy):$flower($fx,$fy,$fl[$k%5]));}
 /* the podium: three level blocks on a stone base (2nd, 1st, 3rd) */
 $g.='<rect x="112" y="690" width="548" height="44" rx="6" fill="url(#lbStone)" stroke="#b08a4e" stroke-width="2"/><path d="M112 728H660" stroke="#d9bd84" stroke-width="4" stroke-dasharray="2 12"/><path d="M106 740H666" stroke="#7a5a2a" stroke-opacity=".18" stroke-width="10" stroke-linecap="round"/>';
 $pod=[[1,135,150,522,$C[6],'2ND'],[0,305,162,440,$C[1],'1ST'],[2,487,150,560,$C[2],'3RD']];
 foreach($pod as [$i,$bx,$bw,$top,$col,$place]){if(!isset($rows[$i]))continue;$r=$rows[$i];$bh=690-$top;$mx=$bx+$bw/2;
  $g.=$block($bx,$top,$bw,$bh,$col)
   .'<text x="'.$mx.'" y="'.($top+62).'" text-anchor="middle" font-size="54" font-weight="900" fill="#fff" stroke="'.$col[1].'" stroke-width="1">'.($i+1).'</text>'
   .'<text x="'.$mx.'" y="'.($top+84).'" text-anchor="middle" font-size="14" font-weight="800" fill="#fff" letter-spacing="1">'.$place.' PLACE</text>'
   .'<rect x="'.($mx-48).'" y="'.($top+93).'" width="96" height="34" rx="17" fill="#fffdf6"/><text x="'.$mx.'" y="'.($top+117).'" text-anchor="middle" font-size="22" font-weight="900" fill="#17633a">'.(int)$r['pct'].'%</text>';
  $rad=$i===0?50:42;$cx=$mx+6;$cy=$top-56-$rad;
  $g.='<ellipse cx="'.$cx.'" cy="'.($cy+$rad+8).'" rx="'.($rad*.9).'" ry="'.($rad*.22).'" fill="#000" opacity=".14"/><circle cx="'.$cx.'" cy="'.($cy+5).'" r="'.($rad+6).'" fill="'.$col[1].'"/><circle cx="'.$cx.'" cy="'.$cy.'" r="'.($rad+6).'" fill="'.$col[0].'" stroke="#fff" stroke-width="4"/>'
   .'<image href="'.$img($r).'" x="'.($cx-$rad).'" y="'.($cy-$rad).'" width="'.(2*$rad).'" height="'.(2*$rad).'" clip-path="url(#lbC0)" preserveAspectRatio="xMidYMid slice"/>';
  if($i===0)$g.=tf_crown($cx,$cy-$rad-2);
  $g.=$ribbon($cx+$rad*.82,$cy+$rad*.55,.78,$col);
  $g.='<rect x="'.($mx-88).'" y="'.($top-44).'" width="176" height="32" rx="12" fill="#fff8e8" stroke="#b08a4e" stroke-width="2.5"/><text x="'.$mx.'" y="'.($top-22).'" text-anchor="middle" font-size="17" font-weight="800" fill="#17633a">'.$x($cut((string)$r['name'],17)).'</text>';}
 /* 4th to 10th: signs on a stone step, each with a small level block */
 $g.='<rect x="700" y="232" width="470" height="486" rx="18" fill="url(#lbStone)" stroke="#b08a4e" stroke-width="3"/><path d="M700 710H1170" stroke="#d9bd84" stroke-width="4" stroke-dasharray="2 12"/>'
  .'<text x="935" y="266" text-anchor="middle" font-size="18" font-weight="800" fill="#17633a" letter-spacing="2">MORE READING STARS</text>';
 $more=array_slice($rows,3,7,true);
 if(!$more)$g.='<text x="935" y="470" text-anchor="middle" font-size="19" font-weight="700" fill="#6b5a45">Keep reading to join the board!</text>';
 $y=280;foreach($more as $i=>$r){$n=$i+1;$col=$C[($n-1)%7+1];
  $g.='<rect x="716" y="'.$y.'" width="438" height="54" rx="12" fill="#fffdf6" stroke="#d9bd84" stroke-width="2"/>'
   .$block(726,$y+8,36,38,$col).'<text x="744" y="'.($y+36).'" text-anchor="middle" font-size="22" font-weight="900" fill="#fff" stroke="'.$col[1].'" stroke-width="1">'.$n.'</text>'
   .'<circle cx="806" cy="'.($y+27).'" r="21" fill="'.$col[0].'" stroke="#fff" stroke-width="3"/><image href="'.$img($r).'" x="787" y="'.($y+8).'" width="38" height="38" clip-path="url(#lbC0)" preserveAspectRatio="xMidYMid slice"/>'
   .'<text x="838" y="'.($y+25).'" font-size="18" font-weight="800" fill="#0e3b28">'.$x($cut((string)$r['name'],22)).'</text>'
   .'<text x="838" y="'.($y+44).'" font-size="13" font-weight="600" fill="#6b5a45">'.$x(($r['level_id']?level_label((int)$r['level_id']):'No level yet').' · '.(int)$r['done'].' activities').'</text>'
   .'<text x="1140" y="'.($y+36).'" text-anchor="end" font-size="24" font-weight="900" fill="#17633a">'.(int)$r['pct'].'%</text>';$y+=62;}
 /* Toby the Trailblazer with his flag, like on the ladder */
 $g.='<image href="'.$x(friend_img('toby','flag')).'" x="0" y="560" width="128" height="230" preserveAspectRatio="xMidYMax meet"/>'
  .'<text x="600" y="812" text-anchor="middle" font-size="14" font-weight="700" fill="#17633a">BULIG · Building Up Literacy, Inspiring Growth</text>';
 return $g.'</svg>';
}
