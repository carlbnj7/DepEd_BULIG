<?php
/* "My month in BULIG": during the first week of a new month, a small card on the pupil's home page sums up last month
   (activities, lessons, badges, XP, best day). The pupil and their teachers also get a notification. No new tables. */

/** Shown on days 1 to 7 of a month, about the month before. */
function recap_month():?array{
 if((int)date('j')>7)return null;
 $start=strtotime(date('Y-m-01').' -1 month');
 return ['from'=>date('Y-m-01 00:00:00',$start),'to'=>date('Y-m-01 00:00:00'),'name'=>date('F',$start),'prev_from'=>date('Y-m-01 00:00:00',strtotime('-1 month',$start))];
}
function recap_stats(int $pid,array $m):array{
 $acts=(int)val("SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND status IN ('completed','approved') AND submitted_at>=? AND submitted_at<?",[$pid,$m['from'],$m['to']]);
 $before=(int)val("SELECT COUNT(*) FROM activity_completion WHERE pupil_id=? AND status IN ('completed','approved') AND submitted_at>=? AND submitted_at<?",[$pid,$m['prev_from'],$m['from']]);
 $lessons=(int)val('SELECT COUNT(*) FROM pupil_progress WHERE pupil_id=? AND completed_at>=? AND completed_at<?',[$pid,$m['from'],$m['to']]);
 $badges=(int)val('SELECT COUNT(*) FROM pupil_badges WHERE pupil_id=? AND earned_at>=? AND earned_at<?',[$pid,$m['from'],$m['to']]);
 $xp=(int)val('SELECT COALESCE(SUM(amount),0) FROM xp_transactions WHERE pupil_id=? AND created_at>=? AND created_at<?',[$pid,$m['from'],$m['to']]);
 $best=one("SELECT DAYNAME(submitted_at) d,COUNT(*) n FROM activity_completion WHERE pupil_id=? AND status IN ('completed','approved') AND submitted_at>=? AND submitted_at<? GROUP BY DAYNAME(submitted_at) ORDER BY n DESC LIMIT 1",[$pid,$m['from'],$m['to']]);
 return ['acts'=>$acts,'before'=>$before,'lessons'=>$lessons,'badges'=>$badges,'xp'=>$xp,'best'=>$best?(string)$best['d']:''];
}
/** The compact card on the pupil's home page (and the pupil's notification, once a month). */
function month_recap_card(array $u):string{
 $m=recap_month();if(!$m)return '';$pid=(int)$u['id'];$s=recap_stats($pid,$m);if(!$s['acts'])return '';
 $title='Your '.$m['name'].' in BULIG';
 if(function_exists('notif_ready')&&notif_ready()&&!val("SELECT 1 FROM notifications WHERE user_id=? AND kind='month' AND title=?",[$pid,$title]))
  notify($pid,'month',$title,$s['acts'].' '.($s['acts']===1?'activity':'activities').($s['lessons']?', '.$s['lessons'].' '.($s['lessons']===1?'lesson':'lessons'):'').($s['badges']?' and '.$s['badges'].' '.($s['badges']===1?'badge':'badges'):'').'. Great work!','?page=dashboard#month-recap');
 $diff=$s['acts']-$s['before'];$cmp=$s['before']===0?'Your first month of learning!':($diff>0?$diff.' more than last month!':($diff===0?'The same as last month. Keep going!':'Let’s read a little more this month!'));
 $sex=(string)(val('SELECT sex FROM pupil_details WHERE pupil_id=?',[$pid])?:'');
 $chip=fn($ic,$t)=>'<span class="mr-chip">'.icon($ic).e($t).'</span>';
 $h='<section class="card mr-card" id="month-recap" data-recap="'.e($m['name']).'" aria-label="'.e($title).'"><span class="mr-confetti" aria-hidden="true">'.str_repeat('<i></i>',10).'</span>';
 $h.='<div class="mr-main"><span class="eyebrow">'.e(strtoupper($title)).'</span><div class="mr-big"><b data-count="'.$s['acts'].'">'.$s['acts'].'</b><span>'.($s['acts']===1?'activity':'activities').' finished</span></div><small class="mr-cmp">'.e($cmp).'</small></div>';
 $h.='<div class="mr-chips">'.($s['lessons']?$chip('book',$s['lessons'].' '.($s['lessons']===1?'lesson':'lessons')):'').($s['badges']?$chip('star',$s['badges'].' '.($s['badges']===1?'badge':'badges')):'').($s['xp']?$chip('bolt',$s['xp'].' XP'):'').($s['best']!==''?$chip('cal','Best day: '.$s['best']):'').'</div>';
 $h.='<img class="mr-kid" src="assets/images/characters/'.($sex==='male'?'boy':'girl').'-cheer.webp" alt="" loading="lazy"><button type="button" class="mr-close" data-recap-close aria-label="Hide">'.icon('close').'</button></section>';
 return $h;
}
/** Once a month: one notification for the teacher about the whole class. */
function month_recap_teacher(array $u):void{
 $m=recap_month();if(!$m||!function_exists('notif_ready')||!notif_ready())return;$tid=(int)$u['id'];$title='Your class in '.$m['name'];
 if(val("SELECT 1 FROM notifications WHERE user_id=? AND kind='month' AND title=?",[$tid,$title]))return;
 $rows=rows("SELECT u.name,COUNT(c.id) n FROM teacher_pupils t JOIN users u ON u.id=t.pupil_id JOIN activity_completion c ON c.pupil_id=u.id AND c.status IN ('completed','approved') AND c.submitted_at>=? AND c.submitted_at<? WHERE t.teacher_id=? GROUP BY u.id,u.name ORDER BY n DESC,u.name",[$m['from'],$m['to'],$tid]);
 if(!$rows)return;$acts=array_sum(array_map(fn($r)=>(int)$r['n'],$rows));
 $lessons=(int)val('SELECT COUNT(*) FROM pupil_progress p JOIN teacher_pupils t ON t.pupil_id=p.pupil_id WHERE t.teacher_id=? AND p.completed_at>=? AND p.completed_at<?',[$tid,$m['from'],$m['to']]);
 $top=implode(', ',array_map(fn($r)=>explode(' ',trim((string)$r['name']))[0],array_slice($rows,0,3)));
 notify($tid,'month',$title,$acts.' '.($acts===1?'activity':'activities').' and '.$lessons.' '.($lessons===1?'lesson':'lessons').' finished by '.count($rows).' '.(count($rows)===1?'pupil':'pupils').'. Most active: '.$top.'.','?page=progress');
}
