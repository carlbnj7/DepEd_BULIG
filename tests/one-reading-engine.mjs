import fs from 'node:fs';import vm from 'node:vm';import assert from 'node:assert/strict';
import {join,resample,rms,wav} from '../public/assets/one-reading/core.mjs';
const code=fs.readFileSync(new URL('../public/assets/one-reading/engine.mjs',import.meta.url),'utf8').replace(/^import .*\n/,'').replace('export class ReadingEngine','class ReadingEngine').replaceAll('import.meta.url',JSON.stringify('https://bulig.test/assets/one-reading/engine.mjs'))+';globalThis.Engine=ReadingEngine;';
async function run({compressed=false,failed=false,deny=false,silent=false}={}){
 let calls=0,stops=0,node,contexts=0;const sent=[];let intervals=[];
 class AC{constructor(){contexts++;this.sampleRate=48000;this.state='running';this.audioWorklet={addModule:async()=>{}}}resume(){return Promise.resolve()}close(){contexts--;this.state='closed';return Promise.resolve()}createMediaStreamSource(){return {connect(){},disconnect(){}}}createGain(){return {gain:{},connect(){},disconnect(){}}}decodeAudioData(){return Promise.resolve({duration:3,getChannelData:()=>new Float32Array(48000).fill(.2)})}}
 class Recorder{static isTypeSupported(){return true}constructor(){this.mimeType='audio/mp4';this.state='inactive'}start(){this.state='recording'}stop(){this.state='inactive';queueMicrotask(()=>{this.ondataavailable({data:new Blob([new Uint8Array(300)])});this.onstop()})}}
 class Reader{readAsDataURL(blob){blob.arrayBuffer().then(b=>{this.result='data:'+blob.type+';base64,'+Buffer.from(b).toString('base64');this.onload()})}}
 const context=vm.createContext({join,resample,rms,wav,URL,Blob,FileReader:Reader,Float32Array,console,performance,setTimeout,clearTimeout,setInterval:fn=>{intervals.push(fn);return intervals.length},clearInterval(){},navigator:{audioSession:{type:'ambient'},mediaDevices:{getUserMedia:async()=>{calls++;if(deny)throw Error('Permission denied');const track={stop(){stops++},addEventListener(){}};return {getTracks:()=>[track],getAudioTracks:()=>[track]}}}},window:{AudioContext:AC,MediaRecorder:compressed?Recorder:null},MediaRecorder:Recorder,
 AudioWorkletNode:class{constructor(){node=this;this.port={postMessage:()=>queueMicrotask(()=>this.port.onmessage({data:{type:'flushed'}}))}}connect(){}disconnect(){}},
 Worker:class{postMessage(data){sent.push(data);queueMicrotask(()=>this.onmessage({data:data.type==='load'?{type:'ready'}:{type:'result',id:data.id,text:'Tim has a red ball'}}))}terminate(){}}});
 vm.runInContext(code,context);const engine=new context.Engine();await engine.load();
 if(deny){await assert.rejects(()=>engine.start(),/Permission denied/);assert.equal(contexts,0);engine.dispose();return;}
 for(let turn=0;turn<2;turn++){
  const clip=await engine.start();assert.equal(context.navigator.audioSession.type,'auto');node.port.onmessage({data:{type:'pcm',data:new Float32Array(144000).fill(silent?0:.2)}});
  if(failed)engine.fail(Error('Recognition failed'));
  const result=await clip.stop();assert.equal(calls,turn+1);assert.equal(stops,turn+1);assert.equal(contexts,0);assert.equal(result.seconds,3);
  if(silent){assert.equal(result.audio,'');assert.equal(result.text,null);break;}
  assert.match(result.audio,compressed?/^data:audio\/mp4/:/^data:audio\/wav/);
  assert.equal(result.text,failed?null:'Tim has a red ball');
  if(!failed)assert.equal(sent.at(-1).audio.length,48000);else{assert.match(result.error,/unavailable/);break;}
 }
 engine.dispose();
}
await run();await run({compressed:true});await run({failed:true});await run({deny:true});await run({silent:true});
console.log('PASS: one capture, shared PCM, WAV fallback, compressed capture, second attempt, permission cleanup and audio retained on model failure.');
