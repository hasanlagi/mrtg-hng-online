<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
require_once __DIR__.'/src/history-layout.php';
$user = mrtgRequireLogin();
$view = $_GET['view'] ?? 'offline';
$pages = mrtgPppoePages();
if (!is_string($view) || !isset($pages[$view])) {
    http_response_code(404);
    exit('Kategori PPPoE tidak tersedia.');
}
// Prepare the navigation's logout token before releasing the session lock.
$logoutToken = mrtgCsrf();
session_write_close();
require_once __DIR__.'/src/helpers.php';

$rows = [];
$errors = [];
try {
    $routers = allRouters(false);
    foreach ($routers as $router) {
        try {
            $snapshot = pppoeSnapshot($router, true);
            foreach (['active' => true, 'inactive' => false] as $key => $connected) {
                foreach ($snapshot[$key] as $row) {
                    $blocked = !empty($row['disabled']) || in_array(strtolower(trim($row['profile'] ?? '')), ['blokir', 'isolir'], true);
                    $category = $blocked ? 'blocked' : ($connected ? 'online' : 'offline');
                    if ($category === $view) $rows[] = $row + ['connected' => $connected];
                }
            }
        } catch (Throwable $error) {
            $errors[] = 'Data dari '.$router['name'].' tidak dapat diambil.';
        }
    }
    if (!$routers) $errors[] = 'Belum ada MikroTik aktif.';
} catch (Throwable $error) {
    $errors[] = 'Daftar MikroTik tidak dapat dibaca.';
}
usort($rows, function (array $a, array $b) use ($view): int {
    if ($view === 'offline' && ($a['last_logged_out'] ?? '') !== ($b['last_logged_out'] ?? '')) {
        return strcmp($b['last_logged_out'], $a['last_logged_out']);
    }
    return strnatcasecmp($a['name'], $b['name']) ?: strnatcasecmp($a['router'], $b['router']);
});
$columns = match ($view) {
    'online' => ['name' => 'Pelanggan', 'router' => 'MikroTik', 'profile' => 'Profil', 'address' => 'IP', 'uptime' => 'Durasi online'],
    'offline' => ['name' => 'Pelanggan', 'router' => 'MikroTik', 'profile' => 'Profil', 'last_logged_out' => 'Terakhir putus', 'last_disconnect_reason' => 'Alasan putus', 'comment' => 'Catatan'],
    'blocked' => ['name' => 'Pelanggan', 'router' => 'MikroTik', 'profile' => 'Profil', 'status' => 'Sesi', 'last_logged_out' => 'Terakhir putus', 'comment' => 'Catatan'],
};
$time = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('d/m/Y H:i:s');
mrtgPageStart($pages[$view], $user, logoutToken: $logoutToken);
?>
<section class="history-panel">
  <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Kategori PPPoE">
    <?php foreach ($pages as $category => $label): ?>
      <a href="pppoe.php?view=<?=mrtgHtml($category)?>"
         class="btn <?= $view === $category ? 'btn-primary' : 'btn-outline-info' ?>"<?= $view === $category ? ' aria-current="page"' : '' ?>><?=mrtgHtml($label)?></a>
    <?php endforeach; ?>
  </nav>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div><h1 class="mb-1"><?=mrtgHtml($pages[$view])?></h1>
      <p class="muted mb-0">Diambil <?=mrtgHtml($time)?> WIB</p></div>
    <a class="btn btn-outline-info" href="pppoe.php?view=<?=mrtgHtml($view)?>">Perbarui daftar</a>
  </div>
  <?php foreach ($errors as $message): ?>
    <div class="alert alert-warning" role="status"><?=mrtgHtml($message)?></div>
  <?php endforeach; ?>
  <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
    <label class="flex-grow-1">Cari pelanggan atau profil
      <input id="pppoeSearch" type="search" class="form-control mt-1" autocomplete="off">
    </label>
    <span id="pppoeCount" class="badge text-bg-info mb-2" aria-live="polite"><?=count($rows)?> <?= $view === 'online' ? 'sesi' : 'akun' ?></span>
  </div>
  <div class="table-responsive" style="max-height:none" tabindex="0" aria-label="<?=mrtgHtml($pages[$view])?>">
    <table class="table table-hover align-middle">
      <thead><tr><?php foreach ($columns as $label): ?><th scope="col"><?=mrtgHtml($label)?></th><?php endforeach; ?></tr></thead>
      <tbody id="pppoeRows">
        <?php foreach ($rows as $row): ?>
          <tr><?php foreach ($columns as $key => $label): ?>
            <td><?php if ($key === 'status'): ?>
              <span class="badge <?=$row['connected'] ? 'text-bg-success' : 'text-bg-secondary'?>"><?=$row['connected'] ? 'Online' : 'Offline'?></span>
            <?php else: ?><?=mrtgHtml(($row[$key] ?? '') !== '' ? $row[$key] : '—')?><?php endif; ?></td>
          <?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p id="pppoeEmpty" class="muted mt-3 mb-0" <?= $rows ? 'hidden' : '' ?>><?= $errors && !$rows ? 'Data belum tersedia.' : 'Tidak ada pelanggan yang cocok.' ?></p>
</section>
<script>
const search = document.getElementById('pppoeSearch');
const rows = Array.from(document.getElementById('pppoeRows').rows);
const unit = <?=json_encode($view === 'online' ? 'sesi' : 'akun')?>;
search.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    let count = 0;
    for (const row of rows) {
        row.hidden = !row.textContent.toLowerCase().includes(query);
        if (!row.hidden) count++;
    }
    document.getElementById('pppoeCount').textContent = `${count} / ${rows.length} ${unit}`;
    document.getElementById('pppoeEmpty').hidden = count !== 0;
});
</script>
<?php mrtgPageEnd();
