<?php
declare(strict_types=1);
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/history-store.php';
try{
    if($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['action']??'')==='routers'){
        $routers=array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'host'=>$r['host']],allRouters(false));
        jsonOut(['ok'=>true,'routers'=>$routers]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')jsonOut(['ok'=>false,'error'=>'Method not allowed'],405);
    $router=routerById((int)($_POST['router_id']??0));
    if(!$router || !(int)$router['enabled'])jsonOut(['ok'=>false,'error'=>'Sumber MikroTik tidak tersedia'],404);
    jsonOut(['ok'=>true,'router'=>['id'=>(int)$router['id'],'name'=>$router['name'],'host'=>$router['host']],'targets'=>mrtgLiveTargets((int)$router['id']),'ts'=>time(),'sample_interval'=>5]);
}catch(Throwable $e){error_log('MRTG live data unavailable: '.get_class($e));jsonOut(['ok'=>false,'error'=>'Data ping belum tersedia. Coba beberapa saat lagi.'],503);}
