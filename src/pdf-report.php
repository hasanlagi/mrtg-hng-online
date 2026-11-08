<?php
declare(strict_types=1);

/** Pure report presentation. All observations and aggregates are supplied by the authenticated endpoint. */
function mrtgPdfEscape(mixed $value):string {
    return htmlspecialchars(is_scalar($value)?(string)$value:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
function mrtgPdfDate(?int $timestamp,bool $zone=true):string {
    if($timestamp===null)return '-';
    return (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('d/m/Y H:i:s').($zone?' UTC+7':'');
}
function mrtgPdfNumber(mixed $value,int $decimals=0):string {
    if($value===null || !is_numeric($value) || !is_finite((float)$value))return '-';
    return number_format((float)$value,$decimals,'.','');
}
function mrtgPdfBits(mixed $value):string {
    if($value===null || !is_numeric($value) || !is_finite((float)$value))return '-';
    $value=(float)$value;
    foreach([[1e9,2,'Gbps'],[1e6,1,'Mbps'],[1e3,1,'Kbps']] as [$scale,$precision,$unit])if($value>=$scale)return mrtgPdfNumber($value/$scale,$precision).' '.$unit;
    return mrtgPdfNumber($value).' bps';
}
function mrtgPdfDuration(int $seconds):string {
    $seconds=max(0,$seconds);$parts=[];
    foreach([[86400,'hari'],[3600,'jam'],[60,'menit']] as [$divisor,$unit]){
        $n=intdiv($seconds,$divisor);if($n)$parts[]=$n.' '.$unit;$seconds%=$divisor;
    }
    $parts[]=$seconds.' detik';return implode(' ',$parts);
}
function mrtgPdfStep(int $step):string {
    if($step>=86400)return mrtgPdfNumber($step/86400,0).' hari';
    if($step>=3600)return mrtgPdfNumber($step/3600,$step%3600?1:0).' jam';
    if($step>=60)return mrtgPdfNumber($step/60,$step%60?1:0).' menit';
    return $step.' detik';
}
function mrtgPdfOutageNotes(array $outage):string {
    $notes=[!empty($outage['ongoing'])?'Belum terpantau pulih':'Sudah pulih'];
    if(!empty($outage['partial_start']))$notes[]='awal tidak teramati';
    if(!empty($outage['uncertain']))$notes[]='ada jeda data';
    return implode(' / ',$notes);
}
function mrtgPdfCoord(float $number):string{return number_format($number,2,'.','');}

/** Clip only the drawn geometry; intersection values are never used for report statistics. */
function mrtgPdfClipSegment(array $segment,int $since,int $until):array {
    if(count($segment)===1)return $segment[0][0]>=$since&&$segment[0][0]<=$until?$segment:[];
    $out=[];
    $append=static function(array $point)use(&$out):void{if(!$out||$out[count($out)-1]!==$point)$out[]=$point;};
    for($i=1;$i<count($segment);$i++){
        [$t0,$v0]=$segment[$i-1];[$t1,$v1]=$segment[$i];
        if($t0===$t1){if($t0>=$since&&$t0<=$until){$append([$t0,$v0]);$append([$t1,$v1]);}continue;}
        $lower=max(min($t0,$t1),$since);$upper=min(max($t0,$t1),$until);if($lower>$upper)continue;
        $intersect=static fn(int $ts):array=>[$ts,$v0+($v1-$v0)*($ts-$t0)/($t1-$t0)];
        $append($intersect($t0<$t1?$lower:$upper));$append($intersect($t0<$t1?$upper:$lower));
    }
    return $out;
}

/** Preserve the numeric time axis and the same short/long-range gap semantics as HngHistory. */
function mrtgPdfChart(array $data,string $kind):array {
    $since=(int)($data['since']??0);$until=(int)($data['ts']??$since);$until=max($since+1,$until);
    $range=(int)($data['window']['range']??($until-$since));
    $points=array_values(array_filter($data['points']??[],static fn($point)=>is_array($point)&&((int)($point['samples']??0)>0 || !empty($point['gap']) || !empty($point['losses']) || !empty($point['unknowns']))));
    $keys=$kind==='traffic'?['a','b']:['a'];$series=[];$values=[];
    foreach($keys as $key){
        $segments=[];$segment=[];
        foreach($points as $point){
            $value=$point[$key]??null;
            $blocked=$range<=86400&&(!empty($point['gap'])||!empty($point['losses'])||!empty($point['unknowns']));
            if($blocked || $value===null || !is_numeric($value) || !is_finite((float)$value)){
                if($segment)$segments[]=$segment;$segment=[];continue;
            }
            $value=max(0.0,(float)$value);$segment[]=[(int)$point['ts'],$value];
        }
        if($segment)$segments[]=$segment;$series[$key]=[];
        foreach($segments as $run){
            $clipped=mrtgPdfClipSegment($run,$since,$until);if(!$clipped)continue;
            $series[$key][]=$clipped;foreach($clipped as $point)$values[]=$point[1];
        }
    }
    $maximum=$values?max($values):0.0;$top=$maximum>0?$maximum*1.12:1.0;
    $left=82.0;$right=978.0;$upper=24.0;$lower=209.0;$width=$right-$left;$height=$lower-$upper;
    $x=static fn(int $ts):float=>$left+($ts-$since)/($until-$since)*$width;
    $y=static fn(float $value):float=>$lower-$value/$top*$height;
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="1000" height="254" viewBox="0 0 1000 254" data-since="'.$since.'" data-until="'.$until.'">';
    $svg.='<rect width="1000" height="254" fill="#ffffff"/><defs><clipPath id="plotClip"><rect x="82" y="24" width="896" height="185"/></clipPath></defs>';
    for($i=0;$i<=4;$i++){
        $value=$top*$i/4;$py=$y($value);$label=$kind==='traffic'?mrtgPdfBits($value):mrtgPdfNumber($value,$top<10?1:0).' ms';
        $svg.='<line x1="82" y1="'.mrtgPdfCoord($py).'" x2="978" y2="'.mrtgPdfCoord($py).'" stroke="#e4ebf1" stroke-width="1"/>';
        $svg.='<text x="72" y="'.mrtgPdfCoord($py+4).'" text-anchor="end" font-family="DejaVu Sans" font-size="10" fill="#61788c">'.mrtgPdfEscape($label).'</text>';
    }
    for($i=0;$i<=5;$i++){
        $ts=(int)round($since+($until-$since)*$i/5);$px=$left+$width*$i/5;
        $format=$range<=900?'H:i:s':($range<=86400?'H:i':($range>2592000?'d/m/y':'d/m H:i'));
        $label=(new DateTimeImmutable('@'.$ts))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format($format);
        $anchor=$i===0?'start':($i===5?'end':'middle');
        $svg.='<line x1="'.mrtgPdfCoord($px).'" y1="24" x2="'.mrtgPdfCoord($px).'" y2="209" stroke="#edf1f5" stroke-width="1"/>';
        $svg.='<text x="'.mrtgPdfCoord($px).'" y="233" text-anchor="'.$anchor.'" font-family="DejaVu Sans" font-size="10" fill="#61788c">'.mrtgPdfEscape($label).'</text>';
    }
    $svg.='<g clip-path="url(#plotClip)">';
    foreach($series as $key=>$segments){
        $color=$key==='b'?'#099b6b':'#168ac7';$class=$key==='b'?'series-tx':'series-rx';
        foreach($segments as $segment){
            $coordinates=[];foreach($segment as [$ts,$value])$coordinates[]=mrtgPdfCoord($x($ts)).','.mrtgPdfCoord($y($value));
            if(count($segment)>1){
                $first=$segment[0];$last=$segment[count($segment)-1];
                $svg.='<polygon points="'.mrtgPdfCoord($x($first[0])).',209 '.implode(' ',$coordinates).' '.mrtgPdfCoord($x($last[0])).',209" fill="'.$color.'" fill-opacity="0.07"/>';
                $svg.='<polyline class="'.$class.'" points="'.implode(' ',$coordinates).'" fill="none" stroke="'.$color.'" stroke-width="1.8" stroke-linejoin="round"/>';
            }else{
                $svg.='<circle class="'.$class.'" cx="'.mrtgPdfCoord($x($segment[0][0])).'" cy="'.mrtgPdfCoord($y($segment[0][1])).'" r="2.5" fill="'.$color.'"/>';
            }
        }
    }
    foreach($points as $point){
        $ts=(int)$point['ts'];if(empty($point['losses'])||$ts<$since||$ts>$until)continue;
        $svg.='<circle class="loss-marker" cx="'.mrtgPdfCoord($x($ts)).'" cy="207" r="2.5" fill="#d94a62"/>';
    }
    $svg.='</g></svg>';
    return ['svg'=>$svg,'has_values'=>(bool)$values];
}
function mrtgPdfMetrics(array $stats,string $kind):string {
    if($kind==='traffic'){
        $metrics=[['RATA-RATA DOWNLOAD / RX',mrtgPdfBits($stats['average_a']??null),'blue'],['RATA-RATA UPLOAD / TX',mrtgPdfBits($stats['average_b']??null),'green'],['TERTINGGI DOWNLOAD / RX',mrtgPdfBits($stats['max_a']??null),''],['TERTINGGI UPLOAD / TX',mrtgPdfBits($stats['max_b']??null),'']];
    }else{
        $average=$stats['average_ms']??null;$maximum=$stats['max_ms']??null;
        $metrics=[['RATA-RATA PING',$average===null?'-':mrtgPdfNumber($average,1).' ms','blue'],['PING TERTINGGI',$maximum===null?'-':mrtgPdfNumber($maximum,1).' ms',''],['TIMEOUT / LOSS',number_format((int)($stats['losses']??0),0,',','.').' sampel','red'],['LOSS TERUKUR',($stats['loss_pct']??null)===null?'-':mrtgPdfNumber($stats['loss_pct'],2).'%','']];
    }
    $html='<table class="metrics"><tr>';
    foreach($metrics as [$label,$value,$color])$html.='<td><div class="metric-label">'.$label.'</div><div class="metric-value '.$color.'">'.mrtgPdfEscape($value).'</div></td>';
    return $html.'</tr></table>';
}
function mrtgReportHtml(array $report):string {
    $kind=$report['kind']??null;if(!in_array($kind,['traffic','ping'],true))throw new InvalidArgumentException('Jenis laporan tidak valid');
    $items=$report['items']??null;if(!is_array($items)||!$items)throw new InvalidArgumentException('Laporan tidak memiliki objek monitoring');
    $title=$kind==='traffic'?'Laporan Trafik Interface':'Laporan Monitoring Ping';
    $generated=mrtgPdfDate((int)($report['generated_at']??time()));
    $css=<<<'CSS'
@page{size:A4 landscape;margin:20pt 28pt 36pt}*{box-sizing:border-box}body{font-family:"DejaVu Sans",sans-serif;font-size:8pt;line-height:1.45;color:#243a4d;margin:0;background:#fff}h1,h2,p{margin:0}.page-start{page-break-before:always}.brandbar{width:100%;border-collapse:collapse;border-bottom:2pt solid #183b52;margin-bottom:8pt}.brandbar td{padding:0 0 6pt;vertical-align:middle}.brand{font-size:11pt;line-height:1.2;font-weight:bold;color:#123248}.brand span{color:#168ac7}.report-name{font-size:6.5pt;line-height:1.3;color:#61788c;padding-top:2pt}.generated{font-size:6.5pt;text-align:right;color:#61788c}.eyebrow{font-size:6.2pt;line-height:1.2;letter-spacing:1pt;color:#168ac7;font-weight:bold;margin:0 0 3pt}.item-title{font-size:14pt;line-height:1.2;color:#123248;font-weight:bold;word-wrap:break-word;overflow-wrap:anywhere}.identity{font-size:7pt;line-height:1.35;color:#61788c;margin-top:2pt;word-wrap:break-word;overflow-wrap:anywhere}.period{width:100%;border-collapse:collapse;margin:7pt 0}.period td{font-size:7pt;line-height:1.3;background:#f0f5f8;border-top:1pt solid #dce7ee;border-bottom:1pt solid #dce7ee;padding:5pt 9pt;vertical-align:top}.period td:last-child{text-align:right}.muted{color:#61788c}.metrics{width:100%;border-collapse:collapse;table-layout:fixed;margin:0 0 6pt}.metrics td{padding:6pt 9pt;border:1pt solid #dce7ee;background:#f8fafc;vertical-align:top}.metric-label{font-size:6pt;line-height:1.2;color:#61788c;letter-spacing:.2pt}.metric-value{font-size:12pt;font-weight:bold;line-height:1.2;margin-top:3pt;color:#183b52}.blue{color:#168ac7}.green{color:#099b6b}.red{color:#be3e54}.legend{font-size:7pt;line-height:1.3;margin:2pt 0}.legend span{margin-right:15pt}.dot{font-size:9pt;font-weight:bold}.chart{width:100%;height:185pt;display:block}.empty-note{font-size:7.5pt;color:#61788c;margin:3pt 0 5pt}.incident-row{width:100%;border-collapse:collapse;margin:5pt 0}.incident-row td{vertical-align:top;padding:6pt 9pt;border:1pt solid #dce7ee}.incident-count{width:22%;background:#f1f6fa}.incident-count strong{display:block;font-size:14pt;color:#123248}.last-label{font-size:6.2pt;line-height:1.3;color:#61788c;margin-bottom:2pt}.last-detail{font-size:7pt;line-height:1.35}.quality{font-size:6.3pt;line-height:1.4;color:#61788c;margin-top:4pt}.quality strong{color:#536b7f}.appendix h2{font-size:14pt;color:#123248;margin-bottom:4pt;word-wrap:break-word;overflow-wrap:anywhere}.appendix-note{font-size:7pt;color:#61788c;margin-bottom:9pt}.outages{width:100%;border-collapse:collapse;table-layout:fixed;font-size:7pt}.outages thead{display:table-header-group}.outages th{background:#183b52;color:#fff;font-size:6.6pt;font-weight:bold;text-align:left;padding:7pt 8pt}.outages td{border-bottom:1pt solid #e0e8ee;padding:7pt 8pt;vertical-align:top;word-wrap:break-word}.outages tbody tr:nth-child(even){background:#f5f8fa}.outages tr{page-break-inside:avoid}.outages .time{width:20%}.outages .duration{width:19%}.outages .notes{width:41%}.appendix{page-break-before:always}
CSS;
    $html='<!doctype html><html lang="id"><head><meta charset="utf-8"><title>'.mrtgPdfEscape($title).'</title><style>'.$css.'</style></head><body>';
    $header=static function()use($title,$generated):string{return '<table class="brandbar"><tr><td><div class="brand">HNG <span>Network Monitoring</span></div><div class="report-name">'.mrtgPdfEscape($title).'</div></td><td class="generated">Dibuat pada<br>'.mrtgPdfEscape($generated).'</td></tr></table>';};
    foreach($items as $index=>$item){
        $data=$item['data'];$stats=$data['stats'];$since=(int)$data['since'];$until=(int)$data['ts'];$chart=mrtgPdfChart($data,$kind);
        $interval=(int)($data['window']['step']??$report['step']??0);$label=(string)($data['window']['label']??$report['range_label']??'Periode pilihan');
        $html.='<section class="overview'.($index?' page-start':'').'">'.$header().'<div class="eyebrow">'.($kind==='traffic'?'TRAFIK INTERFACE / RX &amp; TX':'MONITORING KONEKSI / PING').'</div><h1 class="item-title">'.mrtgPdfEscape($item['title']??'').'</h1><div class="identity">'.mrtgPdfEscape($item['source']??'').' &nbsp; / &nbsp; '.mrtgPdfEscape($item['identity']??'').'</div>';
        $html.='<table class="period"><tr><td><strong>Periode: '.mrtgPdfEscape($label).'</strong><br>'.mrtgPdfEscape(mrtgPdfDate($since,false)).' - '.mrtgPdfEscape(mrtgPdfDate($until)).'</td><td>Interval rata-rata: <strong>'.mrtgPdfEscape(mrtgPdfStep($interval)).'</strong><br>'.number_format((int)($stats['samples']??0),0,',','.').' sampel tersimpan</td></tr></table>';
        $html.=mrtgPdfMetrics($stats,$kind).'<div class="legend"><span class="blue"><span class="dot">-</span> '.($kind==='traffic'?'Download / RX':'Ping').'</span>'.($kind==='traffic'?'<span class="green"><span class="dot">-</span> Upload / TX</span>':'').'<span class="red"><span class="dot">o</span> '.($kind==='traffic'?'Sampel link DOWN':'Timeout').'</span><span class="muted">Waktu UTC+7</span></div>';
        $html.='<img class="chart" alt="Grafik riwayat monitoring" src="data:image/svg+xml;base64,'.base64_encode($chart['svg']).'">';
        if(!$chart['has_values'])$html.='<p class="empty-note">Belum ada data grafik valid pada periode ini. Data kosong tidak dihitung sebagai nol.</p>';
        $last=$data['last_outage']??null;
        $html.='<table class="incident-row"><tr><td class="incident-count"><div class="last-label">KEJADIAN PUTUS / PERIODE LAPORAN</div><strong>'.number_format((int)($stats['outage_count']??0),0,',','.').' kali</strong></td><td><div class="last-label">Terakhir putus sepanjang pencatatan</div><div class="last-detail">';
        if($last){
            $outside=(int)$last['started_at']<$since||(int)$last['started_at']>$until;
            $html.='<strong>'.mrtgPdfEscape(mrtgPdfDate((int)$last['started_at'])).'</strong>'.($outside?' <span class="muted">(di luar periode laporan)</span>':'').'<br>Durasi perkiraan: '.mrtgPdfEscape(mrtgPdfDuration((int)$last['duration_seconds'])).' / '.mrtgPdfEscape(mrtgPdfOutageNotes($last));
            if($last['recovered_at']!==null)$html.=' / Pulih: '.mrtgPdfEscape(mrtgPdfDate((int)$last['recovered_at']));
        }else $html.='Belum ada putus tercatat.';
        $html.='</div></td></tr></table>';
        $html.='<p class="quality"><strong>Kelengkapan data:</strong> '.number_format((int)($stats['unknowns']??0),0,',','.').' sampel UNKNOWN / '.number_format((int)($stats['gap_buckets']??0),0,',','.').' interval dengan jeda pencatatan. Sampel pertama pada periode: '.mrtgPdfEscape(mrtgPdfDate(isset($stats['first_sample'])?(int)$stats['first_sample']:null)).'.<br>Rata-rata memakai data valid yang tersedia. '.((int)($data['window']['range']??0)>86400?'Rentang panjang tetap menampilkan rata-rata yang tersedia pada interval dengan kegagalan.':'Timeout, UNKNOWN, dan jeda pencatatan memutus garis grafik.').' Satu kejadian putus dihitung sekali meskipun sampel gagal berulang; durasi merupakan perkiraan pengamatan.</p></section>';
    }
    foreach($items as $item){
        $data=$item['data'];$events=$data['outages']??[];if(!$events)continue;
        $events=array_slice($events,0,100);$total=(int)($data['stats']['outage_count']??count($events));
        $html.='<section class="appendix">'.$header().'<div class="eyebrow">LAMPIRAN / CATATAN PUTUS</div><h2>'.mrtgPdfEscape($item['title']??'').'</h2><p class="appendix-note">Menampilkan '.count($events).' dari '.$total.' kejadian pada periode laporan'.($total>count($events)?' (daftar dibatasi pada kejadian terbaru, maksimal 100)':'').'. Waktu UTC+7. Durasi mencakup kejadian teramati secara keseluruhan, termasuk bagian yang melintasi batas periode.</p><table class="outages"><thead><tr><th class="time">Mulai putus</th><th class="time">Tersambung kembali</th><th class="duration">Durasi perkiraan</th><th class="notes">Keterangan</th></tr></thead><tbody>';
        foreach($events as $outage){
            $html.='<tr><td>'.mrtgPdfEscape(mrtgPdfDate((int)$outage['started_at'],false)).'</td><td>'.($outage['recovered_at']===null?'Belum terpantau pulih':mrtgPdfEscape(mrtgPdfDate((int)$outage['recovered_at'],false))).'</td><td>'.mrtgPdfEscape(mrtgPdfDuration((int)$outage['duration_seconds'])).'</td><td>'.mrtgPdfEscape(mrtgPdfOutageNotes($outage)).'</td></tr>';
        }
        $html.='</tbody></table></section>';
    }
    return $html.'</body></html>';
}
