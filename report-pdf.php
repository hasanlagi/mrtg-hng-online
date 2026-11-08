<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
$user=mrtgRequireLogin(true);
session_write_close();
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
function mrtgReportError(int $code,string $message):never {
    http_response_code($code);header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);exit;
}
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');mrtgReportError(405,'Laporan hanya dapat diunduh melalui GET.');}
require_once __DIR__.'/src/report-request.php';
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/traffic-store.php';
require_once __DIR__.'/src/pdf-render.php';
try{
    $request=mrtgReportRequest($_GET);
    $routers=allRouters(false);
    $catalog=$request['kind']==='traffic'?mrtgTrafficCatalog(array_map(fn($r)=>(int)$r['id'],$routers)):[];
    $items=mrtgReportScope($request,$routers,$catalog);
    // One active export per account keeps repeated clicks from duplicating heavy work.
    $lock='hng_mrtg_pdf_'.(int)$user['id'];
    $locked=(int)(mrtgQuery('SELECT GET_LOCK(?,0) acquired','s',[$lock])->get_result()->fetch_assoc()['acquired']??0)===1;
    if(!$locked)mrtgReportError(429,'Laporan sebelumnya sedang dibuat. Tunggu sebentar lalu coba lagi.');
    try{
        set_time_limit(90);
        foreach($items as &$item)$item['data']=$request['kind']==='traffic'
            ?mrtgTrafficHistory($item['id'],$request['step'],$request['range'])
            :mrtgHistory($item['router_id'],$item['id'],$request['step'],$request['range']);
        unset($item);
        $report=['kind'=>$request['kind'],'generated_at'=>time(),'range_label'=>$request['range_label'],'step'=>$request['step'],'items'=>$items];
        $pdf=mrtgRenderPdf($report);
    }finally{mrtgQuery('SELECT RELEASE_LOCK(?)','s',[$lock]);}
    $date=(new DateTimeImmutable('@'.$report['generated_at']))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Ymd-His');
    $filename='hng-mrtg-'.($request['kind']==='traffic'?'trafik':'ping').'-'.$date.'.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
}catch(InvalidArgumentException $e){mrtgReportError(400,$e->getMessage());}
catch(OutOfBoundsException $e){mrtgReportError(404,$e->getMessage());}
catch(LengthException $e){mrtgReportError(422,$e->getMessage());}
catch(Throwable $e){error_log('MRTG PDF unavailable: '.get_class($e));mrtgReportError(503,'Laporan PDF belum dapat dibuat. Coba lagi nanti.');}
