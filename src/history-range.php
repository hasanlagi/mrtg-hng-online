<?php
declare(strict_types=1);
require_once __DIR__.'/history-core.php';
function mrtgRanges():array{return [60=>'1 menit',300=>'5 menit',900=>'15 menit',3600=>'1 jam',43200=>'12 jam',86400=>'1 hari',604800=>'7 hari',2592000=>'1 bulan',7776000=>'3 bulan',15552000=>'6 bulan',31536000=>'1 tahun'];}
function mrtgWindow(int $range=86400,int $step=0):array {
    $automatic=[60=>5,300=>5,900=>5,3600=>15,43200=>120,86400=>300,604800=>1800,2592000=>7200,7776000=>21600,15552000=>43200,31536000=>86400];
    if(!isset($automatic[$range]) || !in_array($step,[0,5,15,30,60,120,300,900,1800,3600,7200,21600,43200,86400],true))throw new InvalidArgumentException('Rentang atau interval tidak valid');
    $step=$step?:$automatic[$range];
    if(ceil($range/$step)+2>1500)throw new InvalidArgumentException('Interval terlalu kecil untuk rentang ini. Pilih Otomatis atau interval lebih besar.');
    return ['range'=>$range,'step'=>$step,'label'=>mrtgRanges()[$range]];
}
function mrtgMergeSummaries(array $rows,int $since,int $now,int $step):array {
    usort($rows,fn($a,$b)=>(int)$a['first_ts']<=>(int)$b['first_ts']);
    $map=[];$gaps=[];$previous=null;
    $stats=['samples'=>0,'online_samples'=>0,'losses'=>0,'unknowns'=>0,'weight'=>0.0,'sum_a'=>0.0,'sum_b'=>0.0,'max_a'=>null,'max_b'=>null,'first_sample'=>null];
    foreach($rows as $row){
        if(!(int)$row['samples'])continue;
        $bucket=(int)$row['bucket'];$first=(int)$row['first_ts'];$last=(int)$row['last_ts'];
        if($previous!==null && $first-$previous>30){for($b=(int)(floor(($previous+1)/$step)*$step);$b<=$first;$b+=$step)$gaps[$b]=true;}
        if((int)$row['gap_count']>0)$gaps[$bucket]=true;
        $previous=max($previous??0,$last);
        if(!isset($map[$bucket]))$map[$bucket]=['samples'=>0,'online_samples'=>0,'losses'=>0,'unknowns'=>0,'weight'=>0.0,'sum_a'=>0.0,'sum_b'=>0.0];
        foreach(['samples','online_samples','losses','unknowns'] as $key){$value=(int)$row[$key];$stats[$key]+=$value;$map[$bucket][$key]+=$value;}
        foreach(['weight','sum_a','sum_b'] as $key){$value=(float)$row[$key];$stats[$key]+=$value;$map[$bucket][$key]+=$value;}
        foreach(['max_a','max_b'] as $key)if($row[$key]!==null)$stats[$key]=$stats[$key]===null?(float)$row[$key]:max($stats[$key],(float)$row[$key]);
        $stats['first_sample']=$stats['first_sample']===null?$first:min($stats['first_sample'],$first);
    }
    if($previous!==null && $now-$previous>30){for($b=(int)(floor(($previous+1)/$step)*$step);$b<=$now;$b+=$step)$gaps[$b]=true;}
    $points=[];
    for($bucket=(int)(floor($since/$step)*$step);$bucket<=$now;$bucket+=$step){
        $row=$map[$bucket]??null;$weight=$row?(float)$row['weight']:0.0;
        $points[]=['ts'=>$bucket,'a'=>$weight>0?$row['sum_a']/$weight:null,'b'=>$weight>0?$row['sum_b']/$weight:null,'samples'=>(int)($row['samples']??0),'losses'=>(int)($row['losses']??0),'unknowns'=>(int)($row['unknowns']??0),'gap'=>isset($gaps[$bucket])];
    }
    $stats['average_a']=$stats['weight']>0?$stats['sum_a']/$stats['weight']:null;
    $stats['average_b']=$stats['weight']>0?$stats['sum_b']/$stats['weight']:null;
    $stats['gap_buckets']=count($gaps);$denominator=$stats['samples']-$stats['unknowns'];
    $stats['loss_pct']=$denominator?round($stats['losses']/$denominator*100,2):null;
    unset($stats['sum_a'],$stats['sum_b']);
    return ['points'=>$points,'stats'=>$stats];
}
function mrtgCounterValue(mixed $value):?int {
    if($value===null || (string)$value==='')return null;
    $s=ltrim((string)$value,'0');if($s==='')$s='0';
    $limit=(string)PHP_INT_MAX;
    if(!ctype_digit($s) || strlen($s)>strlen($limit) || (strlen($s)===strlen($limit)&&strcmp($s,$limit)>0))return null;
    return (int)$s;
}
function mrtgCounterRate(?array $old,mixed $rx,mixed $tx,int $ts,?int $uptime):?array {
    if(!$old || !$old['counter_at'])return null;
    $rx=mrtgCounterValue($rx);$tx=mrtgCounterValue($tx);$rx0=mrtgCounterValue($old['rx_counter']);$tx0=mrtgCounterValue($old['tx_counter']);
    $seconds=$ts-(int)$old['counter_at'];
    if($rx===null || $tx===null || $rx0===null || $tx0===null || $rx<$rx0 || $tx<$tx0 || $seconds<=0 || $seconds>30)return null;
    if($uptime===null || $old['uptime_seconds']===null || mrtgCounterRebooted($old,$ts,$uptime))return null;
    return ['rx_bps'=>(float)(($rx-$rx0)*8/$seconds),'tx_bps'=>(float)(($tx-$tx0)*8/$seconds),'duration'=>$seconds];
}
function mrtgCounterRebooted(array $old,int $ts,?int $uptime):bool {
    if($uptime===null || $old['uptime_seconds']===null || !(int)$old['counter_at'])return false;
    $elapsed=max(0,$ts-(int)$old['counter_at']);$progress=$uptime-(int)$old['uptime_seconds'];
    return $progress<0 || $progress+2<$elapsed;
}
function mrtgUptimeSeconds(string $value):?int {
    if(preg_match('/^(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/',$value,$m) && $value!=='')return (int)($m[1]??0)*604800+(int)($m[2]??0)*86400+(int)($m[3]??0)*3600+(int)($m[4]??0)*60+(int)($m[5]??0);
    if(preg_match('/^(?:(\d+)d)?(\d+):(\d{2}):(\d{2})$/',$value,$m))return (int)($m[1]??0)*86400+(int)$m[2]*3600+(int)$m[3]*60+(int)$m[4];
    return null;
}
