class Capture extends AudioWorkletProcessor {
 constructor(){super();this.buffer=new Float32Array(2048);this.used=0;this.active=true;
  this.port.onmessage=e=>{if(e.data==='finish'){this.active=false;this.flush();this.port.postMessage({type:'flushed'});}};
 }
 flush(){if(this.used){const data=this.buffer.slice(0,this.used);this.port.postMessage({type:'pcm',data},[data.buffer]);this.used=0;}}
 process(inputs){if(!this.active)return false;const channels=inputs[0];if(!channels||!channels.length)return true;
  for(let i=0;i<channels[0].length;i++){let sample=0;for(const channel of channels)sample+=channel[i];this.buffer[this.used++]=sample/channels.length;if(this.used===this.buffer.length)this.flush();}return true;
 }
}
registerProcessor('bulig-capture',Capture);
