export const RATE = 16000;
export function join(chunks) {
  const out = new Float32Array(chunks.reduce((n,c)=>n+c.length,0));
  let offset=0; for(const c of chunks){out.set(c,offset);offset+=c.length;} return out;
}
// Area-average downsampling; no per-block rounding or accumulated clock drift.
export function resample(input,rate) {
  if(rate===RATE)return input.slice();
  const ratio=rate/RATE, out=new Float32Array(Math.floor(input.length/ratio));
  for(let i=0;i<out.length;i++){
    const a=i*ratio,b=(i+1)*ratio;let sum=0;
    for(let j=Math.floor(a);j<Math.ceil(b);j++)sum+=(input[j]||0)*Math.max(0,Math.min(b,j+1)-Math.max(a,j));
    out[i]=sum/ratio;
  } return out;
}
export function rms(x){let s=0;for(const v of x)s+=v*v;return Math.sqrt(s/Math.max(1,x.length));}
export function wav(x){
  const b=new ArrayBuffer(44+x.length*2),v=new DataView(b);
  const text=(at,s)=>{for(let i=0;i<s.length;i++)v.setUint8(at+i,s.charCodeAt(i));};
  text(0,'RIFF');v.setUint32(4,36+x.length*2,true);text(8,'WAVE');text(12,'fmt ');
  v.setUint32(16,16,true);v.setUint16(20,1,true);v.setUint16(22,1,true);v.setUint32(24,RATE,true);
  v.setUint32(28,RATE*2,true);v.setUint16(32,2,true);v.setUint16(34,16,true);text(36,'data');v.setUint32(40,x.length*2,true);
  x.forEach((s,i)=>v.setInt16(44+i*2,Math.round(Math.max(-1,Math.min(1,s))*(s<0?32768:32767)),true));return b;
}
export function tokens(s){return String(s).toLowerCase().replace(/[’‘]/g,"'").match(/[a-z0-9]+(?:'[a-z]+)*/g)||[];}
const normalize=w=>w.replace(/'/g,'');
// Ordered alignment: repeats cannot award the same token twice, skipped words remain unmarked.
export function matchIndices(expected,transcript){
 const a=expected.map(normalize),b=tokens(transcript).map(normalize),rows=Array.from({length:a.length+1},()=>new Uint16Array(b.length+1));
 for(let i=a.length-1;i>=0;i--)for(let j=b.length-1;j>=0;j--)rows[i][j]=a[i]===b[j]?1+rows[i+1][j+1]:Math.max(rows[i+1][j],rows[i][j+1]);
 const hits=[];let i=0,j=0;while(i<a.length&&j<b.length){if(a[i]===b[j]){hits.push(i++);j++;}else if(rows[i+1][j]>rows[i][j+1])i++;else j++;}return hits;
}
