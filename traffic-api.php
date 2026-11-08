<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
mrtgRequireLogin(true);
if($_SERVER['REQUEST_METHOD']==='POST')mrtgVerifyCsrf();
session_write_close();
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/traffic-store.php';
try{
    $sources=[];foreach(allRouters(false) as $router)$sources[(int)$router['id']]=['name'=>$router['name'],'host'=>$router['host']];
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $raw=$_POST['ids']??null;
        if(!is_string($raw)||strlen($raw)>60000)jsonOut(['ok'=>false,'error'=>'Pilihan interface tidak valid'],422);
        try{$ids=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException $e){jsonOut(['ok'=>false,'error'=>'Pilihan interface tidak valid'],422);}
        if(!is_array($ids))jsonOut(['ok'=>false,'error'=>'Pilihan interface tidak valid'],422);
        try{mrtgSelectTraffic($ids,array_keys($sources));}catch(InvalidArgumentException $e){jsonOut(['ok'=>false,'error'=>$e->getMessage()],422);}
        jsonOut(['ok'=>true,'message'=>'Pilihan interface tersimpan. Sampel baru akan muncul dalam beberapa detik.']);
    }
    if($_SERVER['REQUEST_METHOD']!=='GET')jsonOut(['ok'=>false,'error'=>'Method not allowed'],405);
    $catalog=mrtgTrafficCatalog(array_keys($sources));
    foreach($catalog as &$interface){$source=$sources[(int)$interface['router_id']];$interface['router_name']=$source['name'];$interface['router_host']=$source['host'];$interface['id']=(int)$interface['id'];$interface['selected']=(bool)$interface['selected'];}unset($interface);
    $mode=$_GET['mode']??'';
    if($mode==='catalog')jsonOut(['ok'=>true,'interfaces'=>$catalog]);
    $range=filter_var($_GET['range']??3600,FILTER_VALIDATE_INT);$step=filter_var($_GET['step']??0,FILTER_VALIDATE_INT);
    try{$window=mrtgWindow($range===false?0:$range,$step===false?-1:$step);}catch(InvalidArgumentException $e){jsonOut(['ok'=>false,'error'=>$e->getMessage()],400);}
    $selected=array_values(array_filter($catalog,fn($row)=>$row['selected']));
    if($mode==='live'){
        foreach($selected as &$interface){
            $id=$interface['id'];$last=mrtgQuery('SELECT * FROM traffic_outages WHERE interface_id=? ORDER BY started_at DESC LIMIT 1','i',[$id])->get_result()->fetch_assoc();
            $interface['last_outage']=$last?mrtgExportOutage($last,time(),$interface):null;
            $counts=mrtgQuery('SELECT COUNT(*) n FROM traffic_outages WHERE interface_id=? AND started_at<=? AND (recovered_at IS NULL OR recovered_at>=?)','iii',[$id,time(),time()-$range])->get_result()->fetch_assoc();$interface['outage_count']=(int)$counts['n'];
            if(time()-(int)$interface['last_probe_at']>60){$interface['last_status']='UNKNOWN';$interface['last_rx_bps']=null;$interface['last_tx_bps']=null;}
        }unset($interface);
        jsonOut(['ok'=>true,'interfaces'=>$selected,'ts'=>time()]);
    }
    $id=filter_var($_GET['interface_id']??null,FILTER_VALIDATE_INT);$interface=null;
    foreach($selected as $row)if($row['id']===$id){$interface=$row;break;}
    if(!$interface)jsonOut(['ok'=>false,'error'=>'Interface tidak dipilih atau tidak tersedia'],404);
    jsonOut(['ok'=>true,'interface'=>$interface,'data'=>mrtgTrafficHistory($id,$step,$range)]);
}catch(Throwable $e){error_log('MRTG traffic unavailable: '.get_class($e));jsonOut(['ok'=>false,'error'=>'Data trafik belum dapat dibaca. Coba lagi nanti.'],503);}
