<?php
require __DIR__.'/../app/level1.php';
function check_wav($ok,$name){if(!$ok)throw new RuntimeException($name);}
$pcm=str_repeat(pack('v',5000),16000);
$wav='RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,16000,32000,2,16).'data'.pack('V',strlen($pcm)).$pcm;
check_wav(l1_valid_pcm_wav($wav),'valid PCM');
check_wav(!l1_valid_pcm_wav(substr($wav,0,-2)),'truncated PCM');
check_wav(!l1_valid_pcm_wav(substr($wav,0,44).str_repeat("\0",32000)),'silence');
$bad=$wav;$bad[24]="\0";check_wav(!l1_valid_pcm_wav($bad),'invalid sample rate');
echo "PASS: canonical PCM WAV accepted; silence, truncation and incorrect format rejected.\n";
