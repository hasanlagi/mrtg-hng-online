<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source=__DIR__.'/../src/pdf-report.php';if(is_file($source))require_once $source;
$checks=0;
function pdfCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
pdfCheck(function_exists('mrtgReportHtml'),'PDF report HTML renderer is missing');
function pdfFixture(string $kind='traffic'):array {
    $points=[];foreach([[1000,0.0,0.0],[1005,100.0,25.0],[1010,null,null],[1015,200.0,50.0],[1020,300.0,75.0]] as [$ts,$a,$b])$points[]=['ts'=>$ts,'a'=>$a,'b'=>$b,'samples'=>1,'losses'=>$ts===1010?1:0,'unknowns'=>0,'gap'=>false];
    return ['kind'=>$kind,'generated_at'=>0,'range_label'=>'1 menit','step'=>5,'items'=>[['title'=>'uplink <script>alert(1)</script> & "utara"','source'=>'HNG <b>router</b>','identity'=>'sfp1 & sfp2','data'=>['points'=>$points,'stats'=>['average_a'=>1234.5,'average_b'=>456.0,'max_a'=>9000.0,'max_b'=>3000.0,'average_ms'=>12.3,'max_ms'=>40.1,'samples'=>5,'losses'=>1,'unknowns'=>0,'loss_pct'=>20.0,'outage_count'=>102,'ongoing_outages'=>1,'first_sample'=>1000,'gap_buckets'=>0],'outages'=>[['started_at'=>1010,'recovered_at'=>1015,'duration_seconds'=>5,'ongoing'=>false,'partial_start'=>true,'uncertain'=>true]],'last_outage'=>['started_at'=>800,'recovered_at'=>900,'duration_seconds'=>100,'ongoing'=>false,'partial_start'=>false,'uncertain'=>false],'since'=>990,'ts'=>1050,'window'=>['range'=>60,'step'=>5,'label'=>'1 menit']]]]];
}
function pdfSvg(string $html):string {preg_match('~src="data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)"~',$html,$m);return isset($m[1])?(string)base64_decode($m[1],true):'';}
$html=mrtgReportHtml(pdfFixture());$svg=pdfSvg($html);
pdfCheck(!str_contains($html,'<script>')&&!str_contains($html,'<b>router</b>'),'Untrusted metadata must not become markup');
pdfCheck(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;')&&str_contains($html,'sfp1 &amp; sfp2'),'Metadata must survive HTML escaping');
pdfCheck(str_contains($html,'1.2 Kbps')&&str_contains($html,'456 bps'),'Summary must use actual stored weighted averages, not chart bin averages');
pdfCheck(str_contains($html,'102 kali')&&str_contains($html,'1 dari 102'),'Incident total must remain distinct from the supplied limited event list');
pdfCheck(str_contains($html,'01/01/1970 07:00:00 UTC+7'),'Generated timestamp must use explicit UTC+7, including timestamp zero');
pdfCheck(str_contains($html,'Terakhir putus sepanjang pencatatan')&&str_contains($html,'di luar periode laporan'),'All-time last outage outside selected range must be identified');
pdfCheck(str_contains($html,'awal tidak teramati')&&str_contains($html,'ada jeda data'),'Partial and uncertain events must retain their qualifications');
pdfCheck(str_contains($svg,'<clipPath')&&str_contains($svg,'clip-path="url(#plotClip)"'),'Series must be clipped to the requested time and value plot');
pdfCheck(substr_count($svg,'class="series-rx"')===2&&substr_count($svg,'class="series-tx"')===2,'A timeout must split both short-range line series');
pdfCheck(str_contains($svg,'class="loss-marker"'),'Timeout observations must retain visible failure markers');
pdfCheck(str_contains($svg,'0 bps'),'Valid zero values must be represented as zero, not missing data');
$empty=pdfFixture();$empty['items'][0]['data']['points']=[];$empty['items'][0]['data']['stats']['samples']=0;$empty['items'][0]['data']['stats']['average_a']=null;$emptyHtml=mrtgReportHtml($empty);
pdfCheck(str_contains($emptyHtml,'Belum ada data grafik valid pada periode ini'),'Empty history must have an explicit empty state');
pdfCheck(!str_contains(pdfSvg($emptyHtml),'class="series-rx"'),'No-data history must not fabricate a zero line');
$jitter=pdfFixture();$jitter['items'][0]['data']['points'][2]=['ts'=>1010,'a'=>null,'b'=>null,'samples'=>0,'losses'=>0,'unknowns'=>0,'gap'=>false];
pdfCheck(substr_count(pdfSvg(mrtgReportHtml($jitter)),'class="series-rx"')===1,'An empty unflagged bin must not introduce a false recorder gap');
$unknown=pdfFixture();$unknown['items'][0]['data']['points'][2]['losses']=0;$unknown['items'][0]['data']['points'][2]['unknowns']=1;$unknown['items'][0]['data']['stats']['unknowns']=1;$unknownHtml=mrtgReportHtml($unknown);
pdfCheck(substr_count(pdfSvg($unknownHtml),'class="series-rx"')===2&&!str_contains(pdfSvg($unknownHtml),'class="loss-marker"'),'Unknown source data must split lines without becoming a loss event');
pdfCheck(str_contains($unknownHtml,'1 sampel UNKNOWN'),'Unknown observations must be disclosed separately from loss');
$long=pdfFixture();$long['items'][0]['data']['window']['range']=604800;$long['items'][0]['data']['points'][2]['a']=150.0;$long['items'][0]['data']['points'][2]['b']=35.0;
pdfCheck(substr_count(pdfSvg(mrtgReportHtml($long)),'class="series-rx"')===1,'Long ranges must retain available means even in a bucket containing failure samples');
$pingHtml=mrtgReportHtml(pdfFixture('ping'));
pdfCheck(str_contains($pingHtml,'12.3 ms')&&str_contains($pingHtml,'40.1 ms'),'Ping summary must use ping statistics in milliseconds');
pdfCheck(!str_contains(pdfSvg($pingHtml),'class="series-tx"'),'Ping report must not invent a second traffic series');
$clipped=pdfFixture();$clipped['items'][0]['data']['points'][0]['ts']=980;$clippedSvg=pdfSvg(mrtgReportHtml($clipped));
pdfCheck(str_contains($clippedSvg,'clip-path="url(#plotClip)"')&&str_contains($clippedSvg,'data-since="990"')&&str_contains($clippedSvg,'data-until="1050"'),'Chart must preserve requested time boundaries even for the first pre-window bucket');
preg_match_all('~<(?:polyline|polygon)[^>]*points="([^"]+)"~',$clippedSvg,$clippedLines);
$bounded=true;foreach($clippedLines[1] as $line)foreach(explode(' ',$line) as $coordinate){[$px]=explode(',',$coordinate);if((float)$px<82||(float)$px>978)$bounded=false;}
pdfCheck($bounded,'Rendered line and fill coordinates must stay inside plot even when SVG clipPath is unsupported');
pdfCheck(mrtgPdfClipSegment([[980,0.0],[1005,100.0]],990,1050)===[[990,40.0],[1005,100.0]],'Left-edge geometry must intersect the original sloped line rather than clamp an outside value');
$outside=pdfFixture();$outside['items'][0]['data']['points']=[['ts'=>980,'a'=>100.0,'b'=>50.0,'samples'=>1,'losses'=>0,'unknowns'=>0,'gap'=>false]];$outsideHtml=mrtgReportHtml($outside);
pdfCheck(!str_contains(pdfSvg($outsideHtml),'class="series-rx"')&&!str_contains(pdfSvg($outsideHtml),'class="series-tx"')&&str_contains($outsideHtml,'Belum ada data grafik valid pada periode ini'),'A lone outside-window point must not be drawn or become a fake in-window zero');
$right=pdfFixture();$right['items'][0]['data']['points']=[['ts'=>1040,'a'=>0.0,'b'=>0.0,'samples'=>1,'losses'=>0,'unknowns'=>0,'gap'=>false],['ts'=>1060,'a'=>200.0,'b'=>100.0,'samples'=>1,'losses'=>0,'unknowns'=>0,'gap'=>false]];$rightSvg=pdfSvg(mrtgReportHtml($right));
preg_match('~class="series-rx" points="([^"]+)"~',$rightSvg,$rightLine);$rightPoints=explode(' ',$rightLine[1]??'');$lastRight=end($rightPoints);
pdfCheck(str_starts_with((string)$lastRight,'978.00,'),'A segment crossing the end boundary must end at the right plot edge');
pdfCheck(mrtgPdfClipSegment([[980,0.0],[1060,200.0]],990,1050)===[[990,25.0],[1050,175.0]],'A continuous line crossing both edges must preserve its two actual boundary intersections');
$zero=pdfFixture('ping');$zero['items'][0]['data']['stats']['average_ms']=0.0;$zeroHtml=mrtgReportHtml($zero);
pdfCheck(str_contains($zeroHtml,'0.0 ms'),'A valid zero-ms average must not be shown as unavailable');
try{$invalid=pdfFixture();$invalid['kind']='other';mrtgReportHtml($invalid);pdfCheck(false,'Unsupported report type was accepted');}catch(InvalidArgumentException $e){pdfCheck(true,'Unsupported report rejected');}
echo "PASS $checks PDF report fidelity and graph checks\n";
