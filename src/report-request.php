<?php
declare(strict_types=1);
require_once __DIR__.'/history-range.php';

function mrtgReportRequest(array $query):array {
    $kind=$query['kind']??'traffic';
    if(!is_string($kind) || !in_array($kind,['traffic','ping'],true))throw new InvalidArgumentException('Jenis laporan tidak valid.');
    $numbers=[];
    foreach(['range'=>$kind==='traffic'?3600:86400,'step'=>0] as $key=>$default){
        $raw=$query[$key]??$default;
        if(!is_int($raw)&&!is_string($raw))throw new InvalidArgumentException('Rentang atau interval tidak valid.');
        $value=filter_var($raw,FILTER_VALIDATE_INT);
        if($value===false)throw new InvalidArgumentException('Rentang atau interval tidak valid.');
        $numbers[$key]=$value;
    }
    $window=mrtgWindow($numbers['range'],$numbers['step']);
    $result=['kind'=>$kind,'range'=>$window['range'],'step'=>$window['step'],'range_label'=>$window['label']];
    if($kind==='ping'){
        foreach(['router_id','target_id'] as $key){
            $raw=$query[$key]??null;
            if(!is_int($raw)&&!is_string($raw))throw new InvalidArgumentException('Sumber atau target ping tidak valid.');
            $value=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($value===false)throw new InvalidArgumentException('Sumber atau target ping tidak valid.');
            $result[$key]=$value;
        }
        if(!isset(mrtgTargets()[$result['target_id']]))throw new InvalidArgumentException('Target ping tidak valid.');
    }
    return $result;
}

function mrtgReportScope(array $request,array $routers,array $catalog):array {
    $sources=[];
    foreach($routers as $router)if((int)($router['enabled']??0)===1)$sources[(int)$router['id']]=$router;
    if($request['kind']==='ping'){
        $router=$sources[$request['router_id']]??null;
        if(!$router)throw new OutOfBoundsException('Sumber MikroTik tidak tersedia.');
        $target=mrtgTargets()[$request['target_id']];
        return [['id'=>$request['target_id'],'router_id'=>$request['router_id'],'title'=>$target['name'],'identity'=>$target['host'],'source'=>$router['name'].' - '.$router['host']]];
    }
    $items=[];
    foreach($catalog as $interface){
        $router=$sources[(int)$interface['router_id']]??null;
        if(!$router || !(int)$interface['selected'])continue;
        $title=trim((string)($interface['label']??''));
        $items[]=['id'=>(int)$interface['id'],'router_id'=>(int)$interface['router_id'],'title'=>$title!==''?$title:(string)$interface['name'],'identity'=>(string)$interface['name'],'source'=>$router['name'].' - '.$router['host']];
    }
    if(!$items)throw new OutOfBoundsException('Pilih minimal satu interface untuk membuat laporan.');
    if(count($items)>50)throw new LengthException('Maksimal 50 interface per laporan. Kurangi pilihan interface lalu unduh kembali.');
    return $items;
}
