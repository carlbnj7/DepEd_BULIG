const fs=require('fs'),assert=require('assert/strict');
const css=fs.readFileSync('public/assets/theme.css','utf8'),php=fs.readFileSync('app/activity_module_fixes.php','utf8');
assert(php.includes('assets/images/characters/boy.webp'));assert(!php.includes('<svg viewBox='));assert(php.includes('width="337" height="600"'));
const centers={head:[204,61],'eye-left':[166,135],'eye-right':[233,147],nose:[194,159],mouth:[191,185],'arm-left':[70,194],'arm-right':[278,317]};
const rects={};for(const [name,pt] of Object.entries(centers)){
 const rule=css.match(new RegExp('\\.body-map>\\.body-'+name+'\\{([^}]+)\\}'))[1];const values={};for(const [,k,v] of rule.matchAll(/(left|top|width|height):([\d.]+)%/g))values[k]=+v/100;
 rects[name]=values;for(const width of [240,280,337,360,440]){const height=width*600/337;assert(Math.abs(values.left*width-pt[0]*width/337)<.01);assert(Math.abs(values.top*height-pt[1]*width/337)<.01);}
}
for(const [name,r] of Object.entries(rects))for(const [other,s] of Object.entries(rects)){if(name>=other)continue;assert(Math.abs(r.left-s.left)>=(r.width+s.width)/2 || Math.abs(r.top-s.top)>=(r.height+s.height)/2,`${name} overlaps ${other}`)}
assert(css.includes('min-width:0;min-height:0'));assert(css.includes('transform:translate(-50%,-50%)'));console.log('PASS: uniformed pupil asset, seven non-overlapping targets, alignment at five responsive widths.');
