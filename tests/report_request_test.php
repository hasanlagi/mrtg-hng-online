<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source=__DIR__.'/../src/report-request.php';
if(!is_file($source)){fwrite(STDERR,"FAIL PDF report request validation is not implemented\n");exit(1);}
require_once $source;
$checks=0;
function checkReport(bool $condition,string $message):void{global $checks;$checks++;if(!$condition)throw new RuntimeException($message);}
function rejectReport(array $query):void{try{mrtgReportRequest($query);}catch(InvalidArgumentException $e){checkReport(true,'reject');return;}checkReport(false,'Invalid PDF parameters must be rejected');}
$p=mrtgReportRequest(['kind'=>'traffic','range'=>'31536000','step'=>'0']);
checkReport($p['range']===31536000 && $p['step']===86400 && $p['kind']==='traffic','Year report uses existing automatic daily interval');
$p=mrtgReportRequest(['kind'=>'ping','range'=>'60','step'=>'0','router_id'=>'1','target_id'=>'2']);
checkReport($p['router_id']===1 && $p['target_id']===2 && $p['step']===5,'Ping identity and one minute range retained');
foreach([
 ['kind'=>['traffic']],['kind'=>'other'],['kind'=>'traffic','range'=>['60']],['kind'=>'traffic','range'=>'12'],
 ['kind'=>'traffic','step'=>'1'],['kind'=>'traffic','range'=>'31536000','step'=>'5'],
 ['kind'=>'ping','router_id'=>0,'target_id'=>1],['kind'=>'ping','router_id'=>'1 OR 1','target_id'=>1],
 ['kind'=>'ping','router_id'=>1,'target_id'=>[1]],['kind'=>'ping','router_id'=>1,'target_id'=>10],
] as $q)rejectReport($q);
$routers=[['id'=>1,'name'=>'HNG','host'=>'192.0.2.1','enabled'=>1]];
$catalog=[['id'=>28,'router_id'=>1,'name'=>'sfp1','label'=>'Uplink','selected'=>1],['id'=>29,'router_id'=>1,'name'=>'sfp2','label'=>'Other','selected'=>0],['id'=>99,'router_id'=>9,'name'=>'hidden','label'=>'Hidden','selected'=>1]];
$items=mrtgReportScope(mrtgReportRequest(['kind'=>'traffic']),$routers,$catalog);
checkReport(count($items)===1 && $items[0]['id']===28 && $items[0]['title']==='Uplink','Only selected interfaces of enabled sources exported');
$items=mrtgReportScope(mrtgReportRequest(['kind'=>'ping','router_id'=>1,'target_id'=>1]),$routers,[]);
checkReport(count($items)===1 && $items[0]['identity']==='8.8.8.8','Ping metadata comes from server definitions');
foreach([[],[['id'=>1,'enabled'=>0,'name'=>'HNG','host'=>'192.0.2.1']]] as $sources){try{mrtgReportScope(mrtgReportRequest(['kind'=>'ping','router_id'=>1,'target_id'=>1]),$sources,[]);checkReport(false,'Unavailable source must reject');}catch(OutOfBoundsException $e){checkReport(true,'scope rejected');}}
try{mrtgReportScope(mrtgReportRequest(['kind'=>'traffic']),$routers,[]);checkReport(false,'Empty export must reject');}catch(OutOfBoundsException $e){checkReport(true,'empty rejected');}
$many=[];for($i=1;$i<=51;$i++)$many[]=['id'=>$i,'router_id'=>1,'name'=>'eth'.$i,'label'=>'','selected'=>1];
try{mrtgReportScope(mrtgReportRequest(['kind'=>'traffic']),$routers,$many);checkReport(false,'Unbounded export must reject');}catch(LengthException $e){checkReport(true,'bounded');}
echo "PASS $checks report request and scope checks\n";
