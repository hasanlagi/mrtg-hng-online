<?php
declare(strict_types=1);
require_once __DIR__.'/history-db.php';
require_once __DIR__.'/history-summary.php';
function mrtgRecordObservation(int $rid,int $tid,string $name,string $host,string $status,?float $ms,int $ts,string $error=''):void {
    if($rid<1 || !isset(mrtgTargets()[$tid]) || !in_array($status,['ONLINE','OFFLINE','UNKNOWN'],true) || $ts<1)throw new InvalidArgumentException('Invalid observation');
    if($status==='ONLINE' && ($ms===null || !is_finite($ms) || $ms<0))throw new InvalidArgumentException('Invalid latency');
    if($status!=='ONLINE')$ms=null;
    $db=mrtgHistoryDb();$db->begin_transaction();
    try{
        mrtgQuery('INSERT IGNORE INTO ping_state(router_id,target_id,router_name,router_host) VALUES(?,?,?,?)','iiss',[$rid,$tid,$name,$host]);
        $state=mrtgQuery('SELECT * FROM ping_state WHERE router_id=? AND target_id=? FOR UPDATE','ii',[$rid,$tid])->get_result()->fetch_assoc();
        $action=mrtgOutageAction($state,$status,$ts);
        if($action['ignore']){$db->commit();return;}
        mrtgQuery('INSERT INTO ping_samples(router_id,target_id,ts,status,ms) VALUES(?,?,?,?,?)','iiisd',[$rid,$tid,$ts,$status,$ms]);
        mrtgSummaryAdd('P',$rid,$tid,$ts,$status,$ms,null,1.0,(int)$state['last_probe_at']>0 && $ts-(int)$state['last_probe_at']>30);
        $open=$state['open_outage_id']===null?null:(int)$state['open_outage_id'];
        $uncertain=(int)$action['uncertain'];
        if($action['open']){
            mrtgQuery('INSERT INTO ping_outages(router_id,target_id,started_at,last_offline_at,partial_start,uncertain) VALUES(?,?,?,?,?,?)','iiiiii',[$rid,$tid,$ts,$ts,(int)$action['partial'],$uncertain]);
            $open=(int)$db->insert_id;
        }elseif($action['close']){
            mrtgQuery('UPDATE ping_outages SET recovered_at=?,uncertain=GREATEST(uncertain,?) WHERE id=?','iii',[$ts,$uncertain,$open]);$open=null;
        }elseif($action['touch']){
            if($status==='OFFLINE')mrtgQuery('UPDATE ping_outages SET last_offline_at=?,uncertain=GREATEST(uncertain,?) WHERE id=?','iii',[$ts,$uncertain,$open]);
            else mrtgQuery('UPDATE ping_outages SET uncertain=1 WHERE id=?','i',[$open]);
        }
        mrtgQuery('UPDATE ping_state SET router_name=?,router_host=?,last_probe_at=?,last_status=?,last_ms=?,last_error=?,open_outage_id=? WHERE router_id=? AND target_id=?','ssisdsiii',[$name,$host,$ts,$status,$ms,$error,$open,$rid,$tid]);
        $db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function mrtgLiveTargets(int $rid):array {
    $states=mrtgQuery('SELECT * FROM ping_state WHERE router_id=?','i',[$rid])->get_result()->fetch_all(MYSQLI_ASSOC);
    $map=[];foreach($states as $state)$map[(int)$state['target_id']]=$state;
    $out=[];$now=time();
    foreach(mrtgTargets() as $tid=>$target){
        $state=$map[$tid]??null;$fresh=$state && $now-(int)$state['last_probe_at']<=60;
        $points=mrtgQuery('SELECT ts,status,ms FROM ping_samples WHERE router_id=? AND target_id=? ORDER BY ts DESC LIMIT 60','ii',[$rid,$tid])->get_result()->fetch_all(MYSQLI_ASSOC);
        $history=[];$last=null;
        foreach(array_reverse($points) as $point){
            $ts=(int)$point['ts'];
            if($last!==null && $ts-$last>30)$history[]=['ts'=>$last+1,'ms'=>null];
            $history[]=['ts'=>$ts,'ms'=>$point['status']==='ONLINE'?(float)$point['ms']:null];$last=$ts;
        }
        $out[]=$target+['id'=>$tid,'status'=>$fresh?$state['last_status']:'UNKNOWN','ms'=>$fresh && $state['last_status']==='ONLINE'?(float)$state['last_ms']:null,'ts'=>$state?(int)$state['last_probe_at']:null,'history'=>$history];
    }
    return $out;
}
function mrtgExportOutage(array $row,int $now,?array $state):array {
    $ongoing=$row['recovered_at']===null;
    $fresh=$state && $state['last_status']==='OFFLINE' && $now-(int)$state['last_probe_at']<=60;
    $end=$ongoing?($fresh?$now:(int)$row['last_offline_at']):(int)$row['recovered_at'];
    return ['id'=>(int)$row['id'],'started_at'=>(int)$row['started_at'],'recovered_at'=>$ongoing?null:(int)$row['recovered_at'],'last_confirmed_at'=>(int)$row['last_offline_at'],'duration_seconds'=>max(0,$end-(int)$row['started_at']),'ongoing'=>$ongoing,'partial_start'=>(bool)$row['partial_start'],'uncertain'=>(bool)$row['uncertain']||($ongoing&&!$fresh)];
}
function mrtgHistory(int $rid,int $tid,int $step=0,int $range=86400):array {
    if(!isset(mrtgTargets()[$tid]))throw new InvalidArgumentException('Invalid target');
    $window=mrtgWindow($range,$step);$step=$window['step'];$now=time();$since=$now-$range;
    $data=mrtgAggregateHistory('P',$rid,$tid,$since,$now,$step);$stats=$data['stats'];
    $stats['average_ms']=$stats['average_a'];$stats['max_ms']=$stats['max_a'];
    $points=array_map(fn($point)=>$point+['ms'=>$point['a']],$data['points']);
    $state=mrtgQuery('SELECT * FROM ping_state WHERE router_id=? AND target_id=?','ii',[$rid,$tid])->get_result()->fetch_assoc();
    $outages=mrtgQuery('SELECT * FROM ping_outages WHERE router_id=? AND target_id=? AND started_at<=? AND (recovered_at IS NULL OR recovered_at>=?) ORDER BY started_at DESC LIMIT 100','iiii',[$rid,$tid,$now,$since])->get_result()->fetch_all(MYSQLI_ASSOC);
    $counts=mrtgQuery('SELECT COUNT(*) incidents,SUM(recovered_at IS NULL) ongoing FROM ping_outages WHERE router_id=? AND target_id=? AND started_at<=? AND (recovered_at IS NULL OR recovered_at>=?)','iiii',[$rid,$tid,$now,$since])->get_result()->fetch_assoc();
    $stats['outage_count']=(int)$counts['incidents'];$stats['ongoing_outages']=(int)($counts['ongoing']??0);
    $last=mrtgQuery('SELECT * FROM ping_outages WHERE router_id=? AND target_id=? ORDER BY started_at DESC LIMIT 1','ii',[$rid,$tid])->get_result()->fetch_assoc();
    return ['points'=>$points,'stats'=>$stats,'outages'=>array_map(fn($row)=>mrtgExportOutage($row,$now,$state?:null),$outages),'last_outage'=>$last?mrtgExportOutage($last,$now,$state?:null):null,'ts'=>$now,'since'=>$since,'window'=>$window];
}
