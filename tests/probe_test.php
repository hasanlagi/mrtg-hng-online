<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../src/history-core.php';
$source=__DIR__.'/../src/history-probe.php';if(is_file($source))require $source;
function probeCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
probeCheck(function_exists('mrtgProbeTargets'),'Independent target probing is missing');
$targets=[1=>['host'=>'good.test'],2=>['host'=>'bad.test'],3=>['host'=>'later.test']];
$connection=fn()=>new class{
    public function command(string $path,array $args):array{if($args['address']==='bad.test')throw new RuntimeException('hostname resolution failed');return [['time'=>'2ms']];}
    public function close():void{}
};
$observations=[];
mrtgProbeTargets($targets,$connection,function($id,$observation)use(&$observations){$observations[$id]=$observation;});
probeCheck($observations[1]['status']==='ONLINE' && $observations[2]['status']==='UNKNOWN' && $observations[3]['status']==='ONLINE','One failed hostname must not suppress later targets');
$observations=[];
mrtgProbeTargets($targets,function(){throw new RuntimeException('source offline');},function($id,$observation)use(&$observations){$observations[$id]=$observation;});
probeCheck(count($observations)===3 && array_unique(array_column($observations,'status'))===['UNKNOWN'],'A failed source must not invent target outages');
echo "PASS 2 target isolation checks\n";
