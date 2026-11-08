<?php
declare(strict_types=1);
require_once (getenv('MRTG_ROUTER_HELPERS')?:dirname(__DIR__).'/src/helpers.php');
require_once dirname(__DIR__).'/src/history-store.php';
require_once dirname(__DIR__).'/src/history-probe.php';
require_once dirname(__DIR__).'/src/traffic-collector.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $db=mrtgHistoryDb();
    $locked=$db->query("SELECT GET_LOCK('hng_mrtg_history_collector',0) acquired")->fetch_assoc();
    if(!(int)$locked['acquired'])exit;
    $once=in_array('--once',$argv,true);
    $discovered=[];
    do{
        $began=microtime(true);$recorded=0;
        foreach(allRouters(false) as $router){
            $rid=(int)$router['id'];
            $connect=function()use($router){
                $api=new RouterOSAPI((string)$router['host'],(int)$router['port'],(string)$router['username'],(string)$router['password'],(bool)$router['use_ssl']);
                try{$api->connect();return $api;}catch(Throwable $e){$api->close();throw $e;}
            };
            $recorded+=mrtgProbeTargets(mrtgTargets(),$connect,function($tid,$observation)use($router,$rid){
                mrtgRecordObservation($rid,$tid,$router['name'],$router['host'],$observation['status'],$observation['ms'],time(),$observation['status']==='UNKNOWN'?'Sumber atau target tidak dapat dibaca':'');
            });
            $discover=!isset($discovered[$rid]) || time()-$discovered[$rid]>=300;
            mrtgCollectTrafficRouter($router,$discover);
            if($discover)$discovered[$rid]=time();
        }
        if($once){echo 'Recorded '.$recorded." target observations\n";break;}
        usleep((int)(max(0.1,5-(microtime(true)-$began))*1000000));
    }while(true);
    $db->query("SELECT RELEASE_LOCK('hng_mrtg_history_collector')");
}catch(Throwable $e){error_log('MRTG collector stopped: '.get_class($e));exit(1);}
