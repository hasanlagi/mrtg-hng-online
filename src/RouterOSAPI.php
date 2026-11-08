<?php
declare(strict_types=1);

class RouterOSAPI {
    private $socket = null;
    public function __construct(private string $host, private int $port, private string $user, private string $pass, private bool $ssl=false) {}
    private function encodeLength(int $len): string {
        if ($len < 0x80) return chr($len);
        if ($len < 0x4000) return pack('n', $len | 0x8000);
        if ($len < 0x200000) return substr(pack('N', $len | 0xC00000), 1);
        if ($len < 0x10000000) return pack('N', $len | 0xE0000000);
        return "\xF0".pack('N', $len);
    }
    private function readExact(int $n): string {
        $out='';
        while (strlen($out)<$n) {
            $chunk=@fread($this->socket,$n-strlen($out));
            if ($chunk===false || $chunk==='') throw new RuntimeException('API connection closed');
            $out.=$chunk;
        }
        return $out;
    }
    private function readLen(): int {
        $b=ord($this->readExact(1));
        if (($b&0x80)===0) return $b;
        if (($b&0xC0)===0x80) return (($b&0x3F)<<8)|ord($this->readExact(1));
        if (($b&0xE0)===0xC0) { $x=unpack('n',$this->readExact(2))[1]; return $x&0x1FFF; }
        if (($b&0xF0)===0xE0) { $c=unpack('C3',$this->readExact(3)); return (($b&0x0F)<<24)|($c[1]<<16)|($c[2]<<8)|$c[3]; }
        return unpack('N',$this->readExact(4))[1];
    }
    private function word(string $w): string { return $this->encodeLength(strlen($w)).$w; }
    private function writeSentence(array $words): void {
        $buf=''; foreach($words as $w) $buf.=$this->word($w); $buf .= "\0";
        if (@fwrite($this->socket,$buf)===false) throw new RuntimeException('API write failed');
    }
    private function readSentence(): array {
        $out=[]; while(true){$len=$this->readLen(); if($len===0) break; $out[]=$this->readExact($len);} return $out;
    }
    private function parse(array $sentence): array {
        $type=$sentence[0]??''; $row=['!type'=>$type];
        foreach(array_slice($sentence,1) as $w){
            if($w!=='' && $w[0]==='='){
                $p=strpos($w,'=',1); if($p!==false) $row[substr($w,1,$p-1)]=substr($w,$p+1);
            }
        }
        return $row;
    }
    public function connect(): void {
        $scheme=$this->ssl?'ssl://':'tcp://'; $errno=0; $err='';
        $ctx=stream_context_create($this->ssl?['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]]:[]);
        $this->socket=@stream_socket_client($scheme.$this->host.':'.$this->port,$errno,$err,API_TIMEOUT,STREAM_CLIENT_CONNECT,$ctx);
        if(!$this->socket) throw new RuntimeException('Koneksi API gagal: '.($err?:'timeout'));
        stream_set_timeout($this->socket,(int)API_TIMEOUT,(int)((API_TIMEOUT-(int)API_TIMEOUT)*1000000));
        $this->writeSentence(['/login','=name='.$this->user,'=password='.$this->pass]);
        $reply=$this->readSentence(); $type=$reply[0]??'';
        $fields=[]; foreach(array_slice($reply,1) as $w){ if($w!=='' && $w[0]==='='){ $p=strpos($w,'=',1); if($p!==false) $fields[substr($w,1,$p-1)]=substr($w,$p+1); } }
        // RouterOS older than 6.43 may return a challenge. Support both login styles.
        if($type==='!re' && isset($fields['ret'])) {
            $challenge=hex2bin((string)$fields['ret']);
            if($challenge===false) throw new RuntimeException('Challenge API tidak valid');
            $response='00'.md5("\0".$this->pass.$challenge);
            $this->writeSentence(['/login','=name='.$this->user,'=response='.$response]);
            $reply=$this->readSentence(); $type=$reply[0]??'';
        }
        if($type==='!trap') throw new RuntimeException('Username/password API ditolak');
        if($type!=='!done' && $type!=='!re') throw new RuntimeException('Login API tidak dikenali');
    }
    public function command(string $path,array $args=[]): array {
        $words=[$path]; foreach($args as $k=>$v) $words[]=is_int($k)?(string)$v:'='.ltrim((string)$k,'=').'='.(string)$v;
        $this->writeSentence($words); $rows=[];
        while(true){
            $s=$this->readSentence(); if(!$s) continue; $parsed=$this->parse($s); $t=$parsed['!type']??'';
            if($t==='!re'){ unset($parsed['!type']); $rows[]=$parsed; }
            elseif($t==='!trap') throw new RuntimeException(($parsed['message']??'RouterOS API trap'));
            elseif($t==='!done') break;
        }
        return $rows;
    }
    public function close(): void { if(is_resource($this->socket)) @fclose($this->socket); $this->socket=null; }
    public function __destruct(){ $this->close(); }
}
