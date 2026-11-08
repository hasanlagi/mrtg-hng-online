<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source = __DIR__.'/../src/history-core.php';
if (is_file($source)) require $source;
$checks=0;
function check(bool $ok,string $message):void{global $checks;$checks++;if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
check(function_exists('mrtgTimeMs'),'MRTG history normalization is missing');
foreach ([['0ms',0.0],['1ms250us',1.25],['350us',0.35],['1s20ms',1020.0],['00:00:01.250',1250.0],['3.5ms',3.5],['timeout',null],['',null],['12ms garbage',null],['-1ms',null]] as [$input,$want]) {
    $got=mrtgTimeMs($input);
    check($want===null?$got===null:abs($got-$want)<0.00001,'normalize '.json_encode($input));
}
check(mrtgObservation([['time'=>'250us']])===['status'=>'ONLINE','ms'=>0.25],'microseconds remain online');
check(mrtgObservation([['time'=>'0ms']])===['status'=>'ONLINE','ms'=>0.0],'zero latency remains online');
check(mrtgObservation([['status'=>'timeout']])===['status'=>'OFFLINE','ms'=>null],'explicit timeout is an outage');
check(mrtgObservation([])['status']==='UNKNOWN','missing API rows are not an outage');
check(mrtgObservation([['time'=>'garbled']])['status']==='UNKNOWN','malformed API time is not an outage');
$online=['last_status'=>'ONLINE','last_probe_at'=>100,'open_outage_id'=>null];
$offline=['last_status'=>'OFFLINE','last_probe_at'=>105,'open_outage_id'=>7];
$unknown=['last_status'=>'UNKNOWN','last_probe_at'=>110,'open_outage_id'=>7];
$a=mrtgOutageAction(null,'OFFLINE',100);check($a['open']&&$a['partial'],'first offline observation has uncertain start');
$a=mrtgOutageAction($online,'OFFLINE',105);check($a['open']&&!$a['partial'],'online to timeout opens one outage');
$a=mrtgOutageAction($offline,'OFFLINE',110);check($a['touch']&&!$a['open']&&!$a['uncertain'],'repeat timeout updates same outage');
$a=mrtgOutageAction($offline,'UNKNOWN',110);check($a['touch']&&$a['uncertain']&&!$a['close'],'source failure does not recover an outage');
$a=mrtgOutageAction($unknown,'ONLINE',115);check($a['close']&&$a['uncertain'],'recovery after missing data remains uncertain');
$a=mrtgOutageAction($offline,'ONLINE',110);check($a['close']&&!$a['uncertain'],'confirmed recovery closes outage');
$a=mrtgOutageAction($online,'UNKNOWN',105);check(!$a['open']&&!$a['close'],'source failure alone never opens outage');
$a=mrtgOutageAction($offline,'OFFLINE',500);check($a['touch']&&$a['uncertain'],'collector gap marks outage uncertain');
$a=mrtgOutageAction($offline,'ONLINE',104);check($a['ignore'],'old observation cannot rewind state');
check(!mrtgNewPasswordValid('short','short'),'reject short new password');
check(!mrtgNewPasswordValid('LongPassword123','DifferentPassword123'),'reject mismatched confirmation');
check(mrtgNewPasswordValid('LongPassword123','LongPassword123'),'accept matching new password');
$user=['id'=>1,'enabled'=>1,'password_version'=>2];
$session=['user_id'=>1,'password_version'=>2,'created_at'=>100,'last_seen'=>100];
check(mrtgSessionValid($session,$user,110),'current session accepted');
check(!mrtgSessionValid($session,['id'=>1,'enabled'=>1,'password_version'=>3],110),'password change invalidates old session');
check(!mrtgSessionValid($session,$user,1901),'idle session expires');
check(!mrtgSessionValid($session,null,110),'removed account denied');
check(function_exists('mrtgMissingBuckets'),'Graph recording gaps must be detected');
check(mrtgMissingBuckets([100,105,170],60,175)===[60=>true,120=>true],'Recording pause inside bins must split the graph');
check(mrtgMissingBuckets([100,105,110],60,115)===[],'Continuous samples must keep graph connected');
check(mrtgMissingBuckets([100,105],60,200)===[60=>true,120=>true,180=>true],'Stale last sample must not draw current data');
echo "PASS $checks core checks\n";
