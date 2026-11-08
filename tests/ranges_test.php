<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../src/history-core.php';
$source=__DIR__.'/../src/history-range.php';if(is_file($source))require $source;
$checks=0;function rangeCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
rangeCheck(function_exists('mrtgWindow'),'Extended history windows are missing');
foreach([60,300,900,3600,43200,86400,604800,2592000,7776000,15552000,31536000] as $range){$window=mrtgWindow($range,0);rangeCheck(ceil($range/$window['step'])+2<=1500,'Automatic window must have bounded points');}
rangeCheck(mrtgWindow(60,0)['step']===5,'One-minute view must retain five-second observations');
rangeCheck(mrtgWindow(31536000,0)['step']===86400,'Year view must use daily summaries');
foreach([[31536001,0],[0,0],[31536000,60],[86400,7]] as [$range,$step]){try{mrtgWindow($range,$step);rangeCheck(false,'Invalid or unbounded selection accepted');}catch(InvalidArgumentException $e){rangeCheck(true,'Invalid selection rejected');}}
$rows=[['bucket'=>960,'samples'=>2,'online_samples'=>2,'losses'=>0,'unknowns'=>0,'weight'=>5,'sum_a'=>500,'sum_b'=>250,'max_a'=>100,'max_b'=>50,'first_ts'=>1000,'last_ts'=>1005,'gap_count'=>0],['bucket'=>960,'samples'=>1,'online_samples'=>1,'losses'=>0,'unknowns'=>0,'weight'=>10,'sum_a'=>2000,'sum_b'=>1000,'max_a'=>200,'max_b'=>100,'first_ts'=>1010,'last_ts'=>1010,'gap_count'=>0]];
$result=mrtgMergeSummaries($rows,990,1020,60);
rangeCheck(abs($result['stats']['average_a']-166.6666667)<0.001,'Traffic average must be duration-weighted');
rangeCheck($result['stats']['samples']===3 && $result['stats']['max_a']===200.0,'Merged fragments must retain count and peak');
$rows[1]['first_ts']=1100;$rows[1]['last_ts']=1100;$rows[1]['bucket']=1080;
$result=mrtgMergeSummaries($rows,990,1110,60);
rangeCheck($result['stats']['gap_buckets']>0,'Sparse summaries must expose recorder gaps');
rangeCheck(function_exists('mrtgCounterRate'),'Interface counter normalization is missing');
$old=['rx_counter'=>'1000','tx_counter'=>'2000','counter_at'=>100,'uptime_seconds'=>1000];
$rate=mrtgCounterRate($old,'2000','3000',105,1005);
rangeCheck($rate===['rx_bps'=>1600.0,'tx_bps'=>1600.0,'duration'=>5],'Counter bytes must become real bits per second');
rangeCheck(mrtgCounterRate($old,'900','3000',105,1005)===null,'Counter reset must never create a spike');
rangeCheck(mrtgCounterRate($old,'2000','3000',105,5)===null,'Router reboot must invalidate rate even if counter exceeded old value');
rangeCheck(mrtgCounterRate($old,'2000','3000',140,1040)===null,'Long gap must not fabricate current traffic');
rangeCheck(mrtgCounterRate($old,'1000','2000',105,1005)['rx_bps']===0.0,'Valid zero traffic must not become unavailable');
rangeCheck(mrtgCounterRate($old,'99999999999999999999999','3000',105,1005)===null,'Oversized counters must not lose precision');
rangeCheck(mrtgCounterRate(null,'1000','2000',100,1000)===null,'First counter observation cannot be a traffic rate');
rangeCheck(mrtgCounterValue(null)===null && mrtgCounterValue('')===null,'Missing counter must not become valid zero');
rangeCheck(mrtgCounterRate(['rx_counter'=>'1000','tx_counter'=>'2000','counter_at'=>100,'uptime_seconds'=>5],'9000','10000',110,6)===null,'Short-uptime reboot must be detected from uptime progression');
echo "PASS $checks range, aggregation and counter checks\n";
