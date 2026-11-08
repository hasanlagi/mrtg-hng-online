<?php
declare(strict_types=1);
function mrtgTimeMs(mixed $raw): ?float {
    $s=trim((string)$raw);
    if ($s==='' || str_starts_with($s,'-')) return null;
    if (is_numeric($s)) return is_finite((float)$s) ? (float)$s : null;
    if (preg_match('/^(\d+):(\d{2}):(\d{2}(?:\.\d+)?)$/',$s,$m)) {
        return ((int)$m[1]*3600+(int)$m[2]*60+(float)$m[3])*1000;
    }
    if (!preg_match('/^(?:\d+(?:\.\d+)?(?:ms|us|µs|ns|s))+$/u',$s)) return null;
    preg_match_all('/(\d+(?:\.\d+)?)(ms|us|µs|ns|s)/u',$s,$parts,PREG_SET_ORDER);
    $ms=0.0;
    foreach($parts as $part) $ms+=(float)$part[1]*(['s'=>1000,'ms'=>1,'us'=>0.001,'µs'=>0.001,'ns'=>0.000001][$part[2]]);
    return is_finite($ms)?$ms:null;
}
function mrtgObservation(array $rows): array {
    $times=[];$failed=false;
    foreach($rows as $row){
        $ms=mrtgTimeMs($row['time']??'');
        if($ms!==null)$times[]=$ms;
        if(preg_match('/timeout|unreachable|no route|host down|failed/i',(string)($row['status']??'')))$failed=true;
    }
    if($times)return ['status'=>'ONLINE','ms'=>array_sum($times)/count($times)];
    return ['status'=>$failed?'OFFLINE':'UNKNOWN','ms'=>null];
}
function mrtgOutageAction(?array $state,string $status,int $ts): array {
    $out=['ignore'=>false,'open'=>false,'close'=>false,'touch'=>false,'uncertain'=>false,'partial'=>false];
    if($state && $ts<=(int)$state['last_probe_at']){$out['ignore']=true;return $out;}
    $gap=$state && $ts-(int)$state['last_probe_at']>30;
    $unknown=!$state || $state['last_status']==='UNKNOWN' || $gap;
    $open=!empty($state['open_outage_id']);
    if($status==='OFFLINE'){
        $out[$open?'touch':'open']=true;
        $out['partial']=!$open && $unknown;
        $out['uncertain']=$unknown;
    }elseif($open){
        if($status==='ONLINE'){$out['close']=true;$out['uncertain']=$unknown;}
        else{$out['touch']=true;$out['uncertain']=true;}
    }
    return $out;
}
function mrtgNewPasswordValid(string $new,string $confirmation):bool {
    return strlen($new)<=4096 && preg_match_all('/./us',$new)>=10 && hash_equals($new,$confirmation);
}
function mrtgSessionValid(array $session,?array $user,int $now):bool {
    return $user!==null && (int)$user['enabled']===1
        && (int)($session['user_id']??0)===(int)$user['id']
        && (int)($session['password_version']??0)===(int)$user['password_version']
        && $now-(int)($session['last_seen']??0)<=1800
        && $now-(int)($session['created_at']??0)<=28800
        && (int)($session['created_at']??0)<=$now;
}
function mrtgTargets():array {
    return [1=>['name'=>'Google DNS','host'=>'8.8.8.8'],2=>['name'=>'Cloudflare DNS','host'=>'1.1.1.1'],3=>['name'=>'Google','host'=>'google.com'],4=>['name'=>'YouTube','host'=>'youtube.com'],5=>['name'=>'WhatsApp','host'=>'whatsapp.com'],6=>['name'=>'Facebook','host'=>'facebook.com'],7=>['name'=>'Instagram','host'=>'instagram.com'],8=>['name'=>'TikTok','host'=>'tiktok.com'],9=>['name'=>'Netflix','host'=>'netflix.com']];
}
function mrtgMissingBuckets(array $timestamps,int $step,int $now):array {
    $missing=[];$last=null;
    foreach($timestamps as $ts){
        $ts=(int)$ts;
        if($last!==null && $ts-$last>30){
            for($bucket=(int)(floor(($last+1)/$step)*$step);$bucket<=$ts;$bucket+=$step)$missing[$bucket]=true;
        }
        $last=$ts;
    }
    if($last!==null && $now-$last>30){
        for($bucket=(int)(floor(($last+1)/$step)*$step);$bucket<=$now;$bucket+=$step)$missing[$bucket]=true;
    }
    return $missing;
}
