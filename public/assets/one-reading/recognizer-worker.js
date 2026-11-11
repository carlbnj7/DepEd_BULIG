import {env,pipeline} from './vendor/transformers.min.js';
import {rms} from './core.mjs';
env.allowRemoteModels=false;
env.allowLocalModels=true;
env.localModelPath=new URL('./models/',import.meta.url).href;
env.useBrowserCache=true;
env.backends.onnx.wasm.wasmPaths=new URL('./vendor/',import.meta.url).href;
env.backends.onnx.wasm.numThreads=1;
env.backends.onnx.wasm.proxy=false;
let model;
self.onmessage=async({data})=>{
 try{
  if(data.type==='load'){
   model=await pipeline('automatic-speech-recognition','whisper-tiny.en',{quantized:true,progress_callback:p=>{
    if(p.status==='progress')self.postMessage({type:'progress',file:p.file,progress:p.progress});
   }});
   self.postMessage({type:'ready'});
  }else if(data.type==='transcribe'){
   if(!model)throw Error('The speech model is not ready.');
   const start=performance.now();
   const result=rms(data.audio)<0.001?{text:''}:await model(data.audio,{chunk_length_s:20,stride_length_s:3,max_new_tokens:256,do_sample:false});
   self.postMessage({type:'result',id:data.id,final:data.final,text:result.text||'',ms:Math.round(performance.now()-start)});
  }
 }catch(error){self.postMessage({type:'error',message:String(error.message||error),id:data.id});}
};
