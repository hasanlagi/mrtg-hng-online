<?php
declare(strict_types=1);
require_once __DIR__.'/traffic-store.php';
function mrtgCollectTrafficRouter(array $router,bool $discover):int {
    $rid=(int)$router['id'];$catalog=mrtgTrafficCatalog([$rid]);$selected=array_filter($catalog,fn($row)=>(int)$row['selected']===1);
    if(!$discover && !$selected)return 0;
    $api=null;$map=[];$uptime=null;$error='';
    try{
        $api=new RouterOSAPI((string)$router['host'],(int)$router['port'],(string)$router['username'],(string)$router['password'],(bool)$router['use_ssl']);$api->connect();
        $properties='.id,name,default-name,type,running,disabled,dynamic,comment,rx-byte,tx-byte';
        if($discover)$rows=$api->command('/interface/print',['.proplist'=>$properties]);
        else{
            $rows=[];foreach($selected as $interface){foreach($api->command('/interface/print',['.proplist'=>$properties,'?name='.$interface['name']]) as $row)$rows[]=$row;}
        }
        $resource=$api->command('/system/resource/print',['.proplist'=>'uptime']);$uptime=mrtgUptimeSeconds((string)($resource[0]['uptime']??''));
        foreach($rows as $row)$map[trim((string)($row['name']??''))]=$row;
        if($discover){
            $defaults=[];$statement=db()->prepare('SELECT interface_name,display_name FROM router_interfaces WHERE router_id=? AND enabled=1');$statement->bind_param('i',$rid);$statement->execute();
            foreach($statement->get_result() as $cfg)$defaults[trim($cfg['interface_name'])]=(string)($cfg['display_name']?:$cfg['interface_name']);
            mrtgDiscoverTraffic($rid,$rows,$defaults,time());
            $selected=array_filter(mrtgTrafficCatalog([$rid]),fn($row)=>(int)$row['selected']===1);
        }
    }catch(Throwable $e){$map=[];$error='Sumber interface tidak dapat dibaca';error_log('MRTG traffic source unavailable: router '.$rid.'; '.get_class($e));}
    finally{if($api)$api->close();}
    foreach($selected as $interface)mrtgRecordTraffic((int)$interface['id'],$map[trim($interface['name'])]??null,time(),$uptime,$error?:(!isset($map[trim($interface['name'])])?'Interface tidak ditemukan':''));
    return count($selected);
}
