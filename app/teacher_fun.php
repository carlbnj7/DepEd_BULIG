<?php
/** Teacher dashboard animations: welcome panel, stats, class chart, reading tree, check-ins, milestones, progress, school sky, tabs. */
const TF_DEFS='<linearGradient id="t-blazer" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f8a4b"/><stop offset="1" stop-color="#145028"/></linearGradient><linearGradient id="t-skirt" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1f3d2c"/><stop offset="1" stop-color="#132a1d"/></linearGradient><linearGradient id="t-hair" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4a2f22"/><stop offset="1" stop-color="#22140d"/></linearGradient><linearGradient id="t-board" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2d5a43"/><stop offset="1" stop-color="#1c3d2c"/></linearGradient><linearGradient id="t-wood" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c98a52"/><stop offset="1" stop-color="#8a5527"/></linearGradient><linearGradient id="t-school" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff8e8"/><stop offset="1" stop-color="#f1e2c4"/></linearGradient>';
const TF_TEACHER='<g class="tf-body"><rect x="58" y="158" width="9" height="26" rx="4" fill="url(#pwg-skin)"/><rect x="73" y="158" width="9" height="26" rx="4" fill="url(#pwg-skin)"/><path d="M52 190q0-8 9-8h7v9H53q-1 0-1-1zM88 190q0-8-9-8h-7v9h15q1 0 1-1z" fill="#3a2a22"/><path d="M50 128h40l8 36H42z" fill="url(#t-skirt)"/><g class="tf-wave"><path d="M50 94 32 66" stroke="url(#pwg-skin)" stroke-width="9" stroke-linecap="round"/><path d="M30 64q-4-9 1-14l2 6q1-8 5-10l1 8q3-6 7-6l-2 9q4-3 6-1l-6 9q-6 4-14-1z" fill="#f6cfa8" stroke="#e0a77a" stroke-width="1.1" stroke-linejoin="round" transform="translate(-4 2)"/></g><path d="M44 90q26-12 52 0l-3 40H47z" fill="url(#t-blazer)" stroke="#0f3d24" stroke-width="2" stroke-linejoin="round"/><path d="M62 84l8 18 8-18z" fill="#fff"/><path d="M60 84l10 20-6 2-8-16zM80 84l-10 20 6 2 8-16z" fill="#1d6b36" stroke="#0f3d24" stroke-width="1.2" stroke-linejoin="round"/><path d="M66 86l4 34 4-34" stroke="#d6452f" stroke-width="2" fill="none"/><rect x="66" y="118" width="9" height="12" rx="2" fill="#fff" stroke="#f6c02c" stroke-width="1.5"/><rect x="68" y="121" width="5" height="2" fill="#2f8a4b"/><circle cx="45" cy="96" r="8" fill="url(#t-blazer)" stroke="#0f3d24" stroke-width="2"/><path d="M95 96 86 116" stroke="url(#t-blazer)" stroke-width="11" stroke-linecap="round"/><g><path d="M70 104l14-4 14 4v22l-14-4-14 4z" fill="url(#pf-red)"/><path d="M72 104l12-3v20l-12 3z" fill="#fffdf6"/><path d="M84 101l12 3v20l-12-3z" fill="#fff8e8"/><path d="M75 108l7-2M75 112l7-2M87 106l7 2M87 110l7 2" stroke="#b9d7bf" stroke-width="1.4" stroke-linecap="round"/><ellipse cx="86" cy="117" rx="5.5" ry="6" fill="#f6cfa8" stroke="#e0a77a" stroke-width="1.1"/></g><rect x="64" y="72" width="12" height="13" rx="5" fill="#eab58a"/><circle cx="47" cy="54" r="5.5" fill="#f6cfa8"/><circle cx="93" cy="54" r="5.5" fill="#f6cfa8"/><circle cx="47" cy="60" r="2" fill="#f6c02c"/><circle cx="93" cy="60" r="2" fill="#f6c02c"/><ellipse cx="70" cy="52" rx="23" ry="24" fill="url(#pwg-skin)"/><circle cx="70" cy="20" r="11" fill="url(#t-hair)"/><path d="M46 52q-3-28 24-28t24 28q-4-14-14-17-8 7-22 5-8 4-12 12z" fill="url(#t-hair)"/><path d="M58 30q8-4 18-2" stroke="#7a5038" stroke-width="2.5" stroke-linecap="round" fill="none"/><g class="tf-eyes"><circle cx="61" cy="55" r="2.6" fill="#2b1d16"/><circle cx="79" cy="55" r="2.6" fill="#2b1d16"/></g><circle cx="61" cy="55" r="6.5" fill="rgba(255,255,255,.18)" stroke="#5a3a26" stroke-width="2"/><circle cx="79" cy="55" r="6.5" fill="rgba(255,255,255,.18)" stroke="#5a3a26" stroke-width="2"/><path d="M67.5 55h5" stroke="#5a3a26" stroke-width="2"/><path d="M56 46q5-3 10 0M74 46q5-3 10 0" stroke="#3b2a20" stroke-width="2" stroke-linecap="round" fill="none"/><ellipse cx="55" cy="64" rx="4.5" ry="3" fill="#ff9d9d" opacity=".55"/><ellipse cx="85" cy="64" rx="4.5" ry="3" fill="#ff9d9d" opacity=".55"/><path d="M62 64q8 9 16 0" stroke="#9c2f22" stroke-width="2.4" stroke-linecap="round" fill="#fff"/></g>';
const TF_BOARD='<svg class="tf-board" aria-hidden="true" viewBox="0 0 300 150"><rect x="6" y="6" width="288" height="128" rx="6" fill="url(#t-wood)"/><rect x="14" y="14" width="272" height="112" rx="3" fill="url(#t-board)"/><g class="chalk" fill="none" stroke="#f4f1e6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path class="ch1" d="M30 46l8-20 8 20M33 38h10M54 26v20h7a5 5 0 0 0 0-10h-7 6a5 5 0 0 0 0-10h-6M84 30a8 8 0 1 0 0 12"/><path class="ch2" d="M30 68h70"/><path class="ch3" d="m118 30 4 8 9 1-7 6 2 9-8-4-8 4 2-9-7-6 9-1z"/><path class="ch4" d="M30 92q10-10 20 0t20 0 20 0"/></g><text class="ch5" x="200" y="62" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="700" font-size="17" fill="#f4f1e6" opacity=".95">Good morning,</text><text class="ch6" x="200" y="86" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="700" font-size="17" fill="#ffd772">class!</text><rect x="10" y="132" width="280" height="8" rx="3" fill="#8a5527"/><rect x="40" y="128" width="16" height="5" rx="2" fill="#fff"/><rect x="62" y="129" width="12" height="4" rx="2" fill="#ffd772"/></svg>';
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
 return '<div class="pw-overlay" data-welcome role="dialog" aria-modal="true" aria-labelledby="tf-welcome-title"><div class="pw-panel tf-panel"><button type="button" class="pw-close" data-welcome-close aria-label="Close">'.icon('close').'</button><div class="tf-art" aria-hidden="true">'.TF_BOARD.'<svg class="tf-teacher" viewBox="20 6 104 190">'.TF_TEACHER.'</svg></div><span class="pw-eyebrow">'.e(strtoupper($greet)).'</span><h2 id="tf-welcome-title">Welcome back, '.e(tf_first($u)).'!</h2><p class="pw-msg">Here is your class at a glance.</p><div class="pw-stats">'.$chip($today,'pupil learned today','pupils learned today','g').$chip($check,'needs a check-in','need a check-in','y').$chip($new,'new activity','new activities','b').'</div><div class="pw-actions"><a class="btn primary" href="?page=review">'.icon('check').'See activity history</a><button type="button" class="btn secondary" data-welcome-close>Go to my dashboard</button></div></div></div>';
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
 $av='';foreach(array_slice($need,0,4) as $p)$av.='<a href="?page=pupil&amp;id='.(int)$p['id'].'" title="'.e($p['name']).'">'.avatar($p).'</a>';
 return '<section class="tf-alert" role="status"><svg viewBox="0 0 40 40" class="tf-bell" aria-hidden="true"><path d="M20 5a3 3 0 0 1 3 3v1a10 10 0 0 1 7 10v6l3 4H7l3-4v-6a10 10 0 0 1 7-10V8a3 3 0 0 1 3-3z" fill="url(#pf-gold)" stroke="#c98200" stroke-width="1.5"/><circle class="tf-clap" cx="20" cy="33" r="3.5" fill="#c98200"/><path d="M14 16a7 7 0 0 1 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7"/></svg><div><strong>'.$c.' '.($c===1?'pupil needs':'pupils need').' a check-in</strong><span>'.e(implode(', ',$names).($c>3?' and '.($c-3).' more':'')).' · no learning in the past 7 days</span></div><div class="tf-avs">'.$av.'</div></section>';
}

/* 6 · Pupil milestones feed */
function tf_feed(array $ids):string{
 if(!$ids)return '';$in=tf_in($ids);$since=date('Y-m-d H:i:s',strtotime('-14 days'));
 $items=[];foreach(rows("SELECT u.name,u.id,b.title,b.rule_type,p.earned_at t FROM pupil_badges p JOIN badges b ON b.id=p.badge_id JOIN users u ON u.id=p.pupil_id WHERE p.pupil_id IN($in) AND p.earned_at>=? ORDER BY p.earned_at DESC LIMIT 6",[$since]) as $r)$items[]=['t'=>$r['t'],'html'=>'<span class="tf-fi-ic">'.pf_medal($r['rule_type'],34).'</span><span><b>'.e($r['name']).'</b> earned the <b>'.e($r['title']).'</b> badge'];
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
