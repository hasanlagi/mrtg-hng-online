<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
require_once __DIR__.'/src/history-layout.php';
require_once __DIR__.'/src/history-range.php';
$user=mrtgRequireLogin();mrtgPageStart('Grafik trafik interface',$user);
?>
<section class="history-panel">
  <div class="traffic-heading"><div><h1>Grafik trafik · interface yang dipilih</h1><p class="muted mb-0">Download (RX) dan upload (TX) dari counter MikroTik. Riwayat tetap dicatat saat halaman ditutup.</p></div><button type="button" class="btn btn-sm btn-outline-info" id="trafficChoose" aria-expanded="false">Pilih interface</button></div>
  <form id="trafficPicker" hidden><input type="hidden" name="csrf" value="<?=mrtgCsrf()?>"><div class="traffic-pick-actions"><button type="button" class="btn btn-sm btn-outline-light" id="trafficPickAll">Pilih semua</button><button type="button" class="btn btn-sm btn-outline-light" id="trafficPickNone">Kosongkan</button></div><div id="trafficChoices" class="traffic-choices">Memuat daftar interface...</div><button type="submit" class="btn btn-sm btn-primary" id="trafficSave" disabled>Simpan pilihan</button></form>
  <div class="history-filters mt-3"><label><span>Rentang riwayat</span><select class="form-select form-select-sm" id="trafficRange" aria-label="Rentang riwayat trafik"><?php foreach(mrtgRanges() as $seconds=>$label):?><option value="<?=$seconds?>" <?=$seconds===3600?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><label><span>Interval rata-rata</span><select class="form-select form-select-sm" id="trafficStep" aria-label="Interval rata-rata trafik"><option value="0">Otomatis</option><option value="60">1 menit</option><option value="300">5 menit</option><option value="900">15 menit</option><option value="3600">1 jam</option><option value="43200">12 jam</option><option value="86400">1 hari</option></select></label><div class="report-controls"><button class="btn btn-sm btn-outline-info" id="trafficRefresh" type="button">Perbarui grafik</button><button class="btn btn-sm btn-outline-light report-download" id="downloadTrafficPdf" type="button" data-report-kind="traffic" data-report-status="trafficReportStatus">Download PDF</button></div></div><div id="trafficReportStatus" class="report-status" role="status" aria-live="polite"></div>
  <div id="trafficError" class="alert alert-danger mt-3" role="alert"></div><div id="trafficMessage" class="history-note" role="status"></div>
  <div class="history-note">Jumlah putus dihitung per kejadian link DOWN yang teramati, bukan per sampel. Rentang panjang menampilkan rata-rata data yang tersedia; waktu putus tetap tercatat terpisah.</div>
</section>
<div id="trafficCards" class="traffic-grid"></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script><script src="history-range.js?v=20261103b"></script><script src="traffic-history.js?v=20261103b"></script><script src="report-download.js?v=20261103a"></script>
<?php mrtgPageEnd();
