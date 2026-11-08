<?php
declare(strict_types=1);
require_once __DIR__.'/history-store.php';
function mrtgTrafficCatalog(array $routers):array {
    $routers=array_values(array_unique(array_map('intval',$routers)));
    if(!$routers)return [];
    foreach($routers as $id)if($id<1)throw new InvalidArgumentException('Invalid source');
    $in=implode(',',$routers);
    return mrtgHistoryDb()->query("SELECT id,router_id,name,label,type,selected,last_seen,last_probe_at,last_status,last_rx_bps,last_tx_bps,last_error FROM traffic_interfaces WHERE router_id IN ($in) ORDER BY router_id,name,id")->fetch_all(MYSQLI_ASSOC);
}
function mrtgDiscoverTraffic(int $rid,array $rows,array $defaults,int $ts):void {
    foreach($rows as $row){
        if(($row['dynamic']??'false')==='true')continue;
        $name=trim((string)($row['name']??''));if($name==='')continue;
        $key=trim((string)($row['default-name']??$name));if($key==='')$key=$name;
        $label=trim((string)($defaults[$name]??$row['comment']??$name));if($label==='')$label=$name;
        mrtgQuery('INSERT INTO traffic_interfaces(router_id,port_key,name,label,type,selected,last_seen) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),label=VALUES(label),type=VALUES(type),last_seen=VALUES(last_seen)','issssii',[$rid,$key,(string)$row['name'],$label,(string)($row['type']??''),(int)array_key_exists($name,$defaults),$ts]);
    }
}
function mrtgSelectTraffic(array $ids,array $routers):void {
    if(count($ids)>1000)throw new InvalidArgumentException('Terlalu banyak interface');
    foreach($ids as $id)if(!is_int($id) || $id<1)throw new InvalidArgumentException('Interface tidak valid');
    $ids=array_values(array_unique($ids));$routers=array_values(array_unique(array_map('intval',$routers)));
    foreach($routers as $rid)if($rid<1)throw new InvalidArgumentException('Invalid source');
    if(!$routers){if($ids)throw new InvalidArgumentException('Interface tidak tersedia');return;}
    $chosen=array_fill_keys($ids,true);$db=mrtgHistoryDb();$db->begin_transaction();
    try{
        $in=implode(',',$routers);
        $catalog=$db->query("SELECT id,selected,last_status FROM traffic_interfaces WHERE router_id IN ($in) ORDER BY id FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
        $allowed=[];$old=[];foreach($catalog as $row){$id=(int)$row['id'];$allowed[$id]=true;if((int)$row['selected'])$old[$id]=true;}
        foreach($ids as $id)if(!isset($allowed[$id]))throw new InvalidArgumentException('Interface tidak tersedia');
        foreach($catalog as $row){
            $id=(int)$row['id'];$select=isset($chosen[$id]);$was=isset($old[$id]);if($select===$was)continue;
            if($select)mrtgQuery("UPDATE traffic_interfaces SET selected=1,counter_at=0,rx_counter=NULL,tx_counter=NULL,last_status='UNKNOWN',last_rx_bps=NULL,last_tx_bps=NULL,last_error='Menunggu sampel baru' WHERE id=?",'i',[$id]);
            else{
                mrtgQuery("UPDATE traffic_interfaces SET selected=0,last_status='UNKNOWN',last_rx_bps=NULL,last_tx_bps=NULL,last_error='Pencatatan tidak dipilih' WHERE id=?",'i',[$id]);
                if($row['last_status']==='OFFLINE')mrtgQuery('UPDATE traffic_outages SET uncertain=1 WHERE interface_id=? AND recovered_at IS NULL','i',[$id]);
            }
        }
        $db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function mrtgRecordTraffic(int $id,?array $row,int $ts,?int $uptime,string $error=''):void {
    $db=mrtgHistoryDb();$db->begin_transaction();
    try{
        $state=mrtgQuery('SELECT * FROM traffic_interfaces WHERE id=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
        if(!$state || !(int)$state['selected'] || $ts<=(int)$state['last_probe_at']){$db->commit();return;}
        $status='UNKNOWN';
        if($row!==null){
            if(($row['disabled']??'false')==='true' || ($row['running']??null)==='false')$status='OFFLINE';
            elseif(($row['running']??null)==='true')$status='ONLINE';
        }
        $rate=$status==='ONLINE'?mrtgCounterRate($state,$row['rx-byte']??null,$row['tx-byte']??null,$ts,$uptime):null;
        $sampleStatus=$status==='ONLINE' && !$rate?'UNKNOWN':$status;
        $rx=$rate['rx_bps']??null;$tx=$rate['tx_bps']??null;$duration=(int)($rate['duration']??0);
        mrtgQuery('INSERT INTO traffic_samples(interface_id,ts,status,rx_bps,tx_bps,duration) VALUES(?,?,?,?,?,?)','iisddi',[$id,$ts,$sampleStatus,$rx,$tx,$duration]);
        mrtgSummaryAdd('T',$id,0,$ts,$sampleStatus,$rx,$tx,(float)$duration,(int)$state['last_probe_at']>0 && $ts-(int)$state['last_probe_at']>30);
        $action=mrtgOutageAction($state,$status,$ts);$open=$state['open_outage_id']===null?null:(int)$state['open_outage_id'];$uncertain=(int)$action['uncertain'];
        if($action['open']){
            mrtgQuery('INSERT INTO traffic_outages(interface_id,started_at,last_offline_at,partial_start,uncertain) VALUES(?,?,?,?,?)','iiiii',[$id,$ts,$ts,(int)$action['partial'],$uncertain]);$open=(int)$db->insert_id;
        }elseif($action['close']){
            mrtgQuery('UPDATE traffic_outages SET recovered_at=?,uncertain=GREATEST(uncertain,?) WHERE id=?','iii',[$ts,$uncertain,$open]);$open=null;
        }elseif($action['touch']){
            if($status==='OFFLINE')mrtgQuery('UPDATE traffic_outages SET last_offline_at=?,uncertain=GREATEST(uncertain,?) WHERE id=?','iii',[$ts,$uncertain,$open]);
            else mrtgQuery('UPDATE traffic_outages SET uncertain=1 WHERE id=?','i',[$open]);
        }
        $counterRx=$row!==null?mrtgCounterValue($row['rx-byte']??null):null;$counterTx=$row!==null?mrtgCounterValue($row['tx-byte']??null):null;
        if($counterRx!==null && $counterTx!==null){$counterAt=$ts;$counterRx=(string)$counterRx;$counterTx=(string)$counterTx;$storedUptime=$uptime;}
        elseif(mrtgCounterRebooted($state,$ts,$uptime)){$counterAt=0;$counterRx=null;$counterTx=null;$storedUptime=$uptime;}
        else{$counterAt=(int)$state['counter_at'];$counterRx=$state['rx_counter'];$counterTx=$state['tx_counter'];$storedUptime=$state['uptime_seconds']===null?null:(int)$state['uptime_seconds'];}
        if($status==='ONLINE' && !$rate && $error==='')$error='Menghitung kecepatan dari dua sampel counter';
        mrtgQuery('UPDATE traffic_interfaces SET last_probe_at=?,last_status=?,last_rx_bps=?,last_tx_bps=?,rx_counter=?,tx_counter=?,counter_at=?,uptime_seconds=?,open_outage_id=?,last_error=? WHERE id=?','isddssiiisi',[$ts,$status,$rx,$tx,$counterRx,$counterTx,$counterAt,$storedUptime,$open,$error,$id]);
        $db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function mrtgTrafficHistory(int $id,int $step=0,int $range=86400):array {
    $window=mrtgWindow($range,$step);$now=time();$since=$now-$range;
    $state=mrtgQuery('SELECT * FROM traffic_interfaces WHERE id=?','i',[$id])->get_result()->fetch_assoc();
    if(!$state)throw new InvalidArgumentException('Interface tidak ditemukan');
    $data=mrtgAggregateHistory('T',$id,0,$since,$now,$window['step']);
    $counts=mrtgQuery('SELECT COUNT(*) incidents,SUM(recovered_at IS NULL) ongoing FROM traffic_outages WHERE interface_id=? AND started_at<=? AND (recovered_at IS NULL OR recovered_at>=?)','iii',[$id,$now,$since])->get_result()->fetch_assoc();
    $data['stats']['outage_count']=(int)$counts['incidents'];$data['stats']['ongoing_outages']=(int)($counts['ongoing']??0);
    $events=mrtgQuery('SELECT * FROM traffic_outages WHERE interface_id=? AND started_at<=? AND (recovered_at IS NULL OR recovered_at>=?) ORDER BY started_at DESC LIMIT 100','iii',[$id,$now,$since])->get_result()->fetch_all(MYSQLI_ASSOC);
    $last=mrtgQuery('SELECT * FROM traffic_outages WHERE interface_id=? ORDER BY started_at DESC LIMIT 1','i',[$id])->get_result()->fetch_assoc();
    $data['outages']=array_map(fn($row)=>mrtgExportOutage($row,$now,$state),$events);
    $data['last_outage']=$last?mrtgExportOutage($last,$now,$state):null;
    $data['window']=$window;$data['ts']=$now;$data['since']=$since;
    return $data;
}
