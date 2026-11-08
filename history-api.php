<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
mrtgRequireLogin(true);session_write_close();
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/history-store.php';
if($_SERVER['REQUEST_METHOD']!=='GET')jsonOut(['ok'=>false,'error'=>'Method not allowed'],405);
try{
    $rid=filter_var($_GET['router_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $router=$rid?routerById($rid):null;
    if(!$router || !(int)$router['enabled'])jsonOut(['ok'=>false,'error'=>'Sumber MikroTik tidak tersedia'],404);
    $range=filter_var($_GET['range']??86400,FILTER_VALIDATE_INT);
    $step=filter_var($_GET['step']??0,FILTER_VALIDATE_INT);
    try{$window=mrtgWindow($range===false?0:$range,$step===false?-1:$step);}catch(InvalidArgumentException $e){jsonOut(['ok'=>false,'error'=>$e->getMessage()],400);}
    if(($_GET['mode']??'')==='overview'){
        $targets=mrtgLiveTargets($rid);
        foreach($targets as &$target){
            unset($target['history']);
            $last=mrtgQuery('SELECT * FROM ping_outages WHERE router_id=? AND target_id=? ORDER BY started_at DESC LIMIT 1','ii',[$rid,$target['id']])->get_result()->fetch_assoc();
            $target['last_outage']=$last?mrtgExportOutage($last,time(),['last_status'=>$target['status'],'last_probe_at'=>$target['ts']]):null;
            $counts=mrtgQuery('SELECT COUNT(*) n FROM ping_outages WHERE router_id=? AND target_id=? AND started_at<=? AND (recovered_at IS NULL OR recovered_at>=?)','iiii',[$rid,$target['id'],time(),time()-$range])->get_result()->fetch_assoc();
            $target['outage_count']=(int)$counts['n'];
        }unset($target);
        jsonOut(['ok'=>true,'targets'=>$targets,'ts'=>time()]);
    }
    $tid=filter_var($_GET['target_id']??null,FILTER_VALIDATE_INT);
    if(!$tid || !isset(mrtgTargets()[$tid]))jsonOut(['ok'=>false,'error'=>'Pilihan grafik tidak valid'],400);
    jsonOut(['ok'=>true,'data'=>mrtgHistory($rid,$tid,$step,$range),'target'=>mrtgTargets()[$tid],'router'=>['id'=>$rid,'name'=>$router['name'],'host'=>$router['host']]]);
}catch(Throwable $e){error_log('MRTG history unavailable: '.get_class($e));jsonOut(['ok'=>false,'error'=>'Riwayat belum dapat dibaca. Coba lagi nanti.'],503);}
