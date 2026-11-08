<?php
declare(strict_types=1);
require_once __DIR__.'/history-db.php';
require_once __DIR__.'/history-range.php';
function mrtgSummaryAdd(string $kind,int $source,int $target,int $ts,string $status,?float $a,?float $b,float $weight,bool $gap):void {
    $valid=$status==='ONLINE' && $a!==null && $weight>0;
    $weight=$valid?$weight:0.0;$maxA=$valid?$a:null;$maxB=$valid?$b:null;
    foreach([60,3600,86400] as $period){
        $bucket=(int)(floor($ts/$period)*$period);
        mrtgQuery('INSERT INTO history_rollups(kind,source_id,target_id,period_seconds,bucket,samples,online_samples,losses,unknowns,weight,sum_a,sum_b,max_a,max_b,first_ts,last_ts,gap_count) VALUES(?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE samples=samples+1,online_samples=online_samples+VALUES(online_samples),losses=losses+VALUES(losses),unknowns=unknowns+VALUES(unknowns),weight=weight+VALUES(weight),sum_a=sum_a+VALUES(sum_a),sum_b=sum_b+VALUES(sum_b),max_a=CASE WHEN VALUES(max_a) IS NULL THEN max_a WHEN max_a IS NULL THEN VALUES(max_a) ELSE GREATEST(max_a,VALUES(max_a)) END,max_b=CASE WHEN VALUES(max_b) IS NULL THEN max_b WHEN max_b IS NULL THEN VALUES(max_b) ELSE GREATEST(max_b,VALUES(max_b)) END,first_ts=LEAST(first_ts,VALUES(first_ts)),last_ts=GREATEST(last_ts,VALUES(last_ts)),gap_count=gap_count+VALUES(gap_count)',
            'siiiiiiidddddiii',[$kind,$source,$target,$period,$bucket,(int)($status==='ONLINE'),(int)($status==='OFFLINE'),(int)($status==='UNKNOWN'),$weight,$valid?$a*$weight:0.0,$valid?($b??0.0)*$weight:0.0,$maxA,$maxB,$ts,$ts,(int)$gap]);
    }
}
function mrtgSummaryFields():string {
    return "COUNT(*) samples,SUM(status='ONLINE') online_samples,SUM(status='OFFLINE') losses,SUM(status='UNKNOWN') unknowns,SUM(w) weight,SUM(IF(w>0,a*w,0)) sum_a,SUM(IF(w>0,COALESCE(b,0)*w,0)) sum_b,MAX(IF(w>0,a,NULL)) max_a,MAX(IF(w>0,b,NULL)) max_b,MIN(ts) first_ts,MAX(ts) last_ts,SUM(IF(previous_ts IS NOT NULL AND ts-previous_ts>30,1,0)) gap_count";
}
function mrtgRawDefinition(string $kind):array {
    if($kind==='P')return ['table'=>'ping_samples','identity'=>'router_id AS source_id,target_id','partition'=>'router_id,target_id','where'=>'router_id=? AND target_id=?','values'=>"ms a,NULL b,IF(status='ONLINE' AND ms IS NOT NULL,1,0) w"];
    if($kind==='T')return ['table'=>'traffic_samples','identity'=>'interface_id AS source_id,0 AS target_id','partition'=>'interface_id','where'=>'interface_id=?','values'=>"rx_bps a,tx_bps b,IF(status='ONLINE' AND rx_bps IS NOT NULL AND tx_bps IS NOT NULL AND duration>0,duration,0) w"];
    throw new InvalidArgumentException('Invalid history kind');
}
function mrtgRawSummary(string $kind,int $source,int $target,int $from,int $until,int $step,?int $weightSince=null):array {
    if($from>=$until)return [];
    $d=mrtgRawDefinition($kind);$identity=$kind==='P'?[$source,$target]:[$source];
    if($kind==='T'){
        $clip=max(0,$weightSince??$from);
        $d['values']="rx_bps a,tx_bps b,IF(status='ONLINE' AND rx_bps IS NOT NULL AND tx_bps IS NOT NULL AND duration>0,LEAST(duration,GREATEST(0,ts-$clip)),0) w";
    }
    $sql="SELECT FLOOR(ts / ?) * ? bucket,".mrtgSummaryFields()." FROM (SELECT ts,status,{$d['values']},LAG(ts) OVER(ORDER BY ts) previous_ts FROM {$d['table']} WHERE {$d['where']} AND ts>=? AND ts<?) readings GROUP BY bucket ORDER BY bucket";
    $params=array_merge([$step,$step],$identity,[$from,$until]);
    return mrtgQuery($sql,str_repeat('i',count($params)),$params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function mrtgAggregateHistory(string $kind,int $source,int $target,int $since,int $now,int $step):array {
    $level=$step<60?0:($step<3600?60:($step<86400?3600:86400));
    $first=$level?(int)(floor($since/$level)*$level+$level):0;
    $end=$level?(int)(floor(($now+1)/$level)*$level):0;
    if(!$level || $first>=$end)$rows=mrtgRawSummary($kind,$source,$target,$since,$now+1,$step,$since);
    else{
        $rows=mrtgQuery('SELECT FLOOR(bucket / ?) * ? bucket,SUM(samples) samples,SUM(online_samples) online_samples,SUM(losses) losses,SUM(unknowns) unknowns,SUM(weight) weight,SUM(sum_a) sum_a,SUM(sum_b) sum_b,MAX(max_a) max_a,MAX(max_b) max_b,MIN(first_ts) first_ts,MAX(last_ts) last_ts,SUM(gap_count) gap_count FROM history_rollups WHERE kind=? AND source_id=? AND target_id=? AND period_seconds=? AND bucket>=? AND bucket<? GROUP BY FLOOR(bucket / ?) ORDER BY bucket','iisiiiiii',[$step,$step,$kind,$source,$target,$level,$first,$end,$step])->get_result()->fetch_all(MYSQLI_ASSOC);
        $rows=array_merge($rows,mrtgRawSummary($kind,$source,$target,$since,$first,$step,$since),mrtgRawSummary($kind,$source,$target,$end,$now+1,$step,$since));
    }
    return mrtgMergeSummaries($rows,$since,$now,$step);
}
function mrtgBackfillSummaries():void {
    $columns='samples,online_samples,losses,unknowns,weight,sum_a,sum_b,max_a,max_b,first_ts,last_ts,gap_count';
    $update=implode(',',array_map(fn($field)=>$field.'=VALUES('.$field.')',explode(',',$columns)));
    foreach(['P','T'] as $kind){
        $d=mrtgRawDefinition($kind);
        foreach([60,3600,86400] as $period){
            $sql="INSERT INTO history_rollups(kind,source_id,target_id,period_seconds,bucket,$columns) SELECT '$kind',source_id,target_id,$period,FLOOR(ts/$period)*$period bucket,".mrtgSummaryFields()." FROM (SELECT {$d['identity']},ts,status,{$d['values']},LAG(ts) OVER(PARTITION BY {$d['partition']} ORDER BY ts) previous_ts FROM {$d['table']}) readings GROUP BY source_id,target_id,bucket ON DUPLICATE KEY UPDATE $update";
            mrtgHistoryDb()->query($sql);
        }
    }
}
