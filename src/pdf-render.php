<?php
declare(strict_types=1);
require_once __DIR__.'/pdf-report.php';

function mrtgRenderPdf(array $report,string $autoload='/opt/hng-mrtg-pdf/vendor/autoload.php',string $cache='/var/cache/hng-mrtg-pdf'):string {
    if(!is_file($autoload) || !is_dir($cache) || !is_writable($cache))throw new RuntimeException('PDF runtime unavailable');
    require_once $autoload;
    $options=new \Dompdf\Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setIsJavascriptEnabled(false);
    $options->setAllowedProtocols(['data://','file://']);
    $options->setChroot([dirname($autoload),$cache]);
    $options->setTempDir($cache);
    $options->setFontCache($cache);
    $options->setDefaultFont('DejaVu Sans');
    $dompdf=new \Dompdf\Dompdf($options);
    $dompdf->setPaper('A4','landscape');
    $dompdf->addInfo('Title','HNG MRTG - Laporan '.($report['kind']==='traffic'?'Trafik Interface':'Ping'));
    $dompdf->addInfo('Author','HNG Network Monitoring');
    $dompdf->addInfo('Subject',$report['range_label'].' | UTC+7 | Riwayat monitoring tersimpan');
    $dompdf->loadHtml(mrtgReportHtml($report),'UTF-8');
    $dompdf->render();
    $font=$dompdf->getFontMetrics()->getFont('DejaVu Sans','normal');
    $dompdf->getCanvas()->page_text(32,575,'HNG Network Monitoring  |  MRTG  |  UTC+7',$font,8,[.4,.48,.55]);
    $dompdf->getCanvas()->page_text(700,575,'Halaman {PAGE_NUM} / {PAGE_COUNT}',$font,8,[.4,.48,.55]);
    return $dompdf->output();
}
