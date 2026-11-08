<?php
declare(strict_types=1);
require_once __DIR__.'/history-core.php';
/** Reconnect after a target trap: the old RouterOS stream may contain !done. */
function mrtgProbeTargets(array $targets,callable $connect,callable $record):int {
    $api=null;$sourceFailed=false;$recorded=0;
    try{
        foreach($targets as $id=>$target){
            if(!$api && !$sourceFailed){
                try{$api=$connect();}catch(Throwable $e){$sourceFailed=true;}
            }
            $observation=['status'=>'UNKNOWN','ms'=>null];
            if(!$sourceFailed){
                try{$observation=mrtgObservation($api->command('/ping',['address'=>$target['host'],'count'=>1,'interval'=>'200ms']));}
                catch(Throwable $e){$api->close();$api=null;}
            }
            $record($id,$observation);$recorded++;
        }
    }finally{if($api)$api->close();}
    return $recorded;
}
