<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
require_once __DIR__.'/src/history-layout.php';
$error='';
try{
    if(mrtgAuthUser()){header('Location: history.php');exit;}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        mrtgVerifyCsrf();
        $username=is_string($_POST['username']??null)?trim($_POST['username']):'';
        $password=is_string($_POST['password']??null)?$_POST['password']:'';
        if(strlen($username)<=80 && mrtgAttemptLogin($username,$password,(string)($_SERVER['REMOTE_ADDR']??''))){header('Location: history.php',true,303);exit;}
        $error=http_response_code()===429?'Terlalu banyak percobaan. Coba lagi dalam 15 menit.':'Username atau password tidak sesuai.';
    }
}catch(Throwable $e){error_log('MRTG login unavailable: '.get_class($e));http_response_code(503);$error='Layanan login sementara tidak tersedia. Coba lagi nanti.';}
mrtgPageStart('Login riwayat monitoring',null,'login');
?>
<div class="auth-shell">
  <section class="auth-intro" aria-label="Tentang monitoring">
    <div class="eyebrow"><span></span> PUSAT MONITORING JARINGAN</div>
    <p class="auth-headline">Pantau koneksi.<br> <span>Pahami trafik.</span></p>
    <p class="auth-description">Riwayat ping, trafik interface, dan catatan koneksi dalam satu tempat.</p>
    <div class="auth-network" aria-hidden="true"><svg viewBox="0 0 460 150" fill="none"><path class="network-track" d="M30 76h85l27-35 38 70 31-52 22 17h197"/><path class="network-baseline" d="M30 126h400M30 26h400M80 13v125m100-125v125m100-125v125m100-125v125"/><circle cx="30" cy="76" r="5"/><circle cx="430" cy="76" r="5"/></svg><div class="network-caption"><span>VISIBILITAS JARINGAN</span><span>HNG / MRTG</span></div></div>
    <div class="auth-features"><span><?=mrtgIcon('pulse')?>Riwayat ping</span><span><?=mrtgIcon('chart')?>Trafik RX / TX</span><span><?=mrtgIcon('network')?>Catatan putus</span></div>
  </section>
  <section class="history-panel login-panel">
    <div class="form-emblem"><?=mrtgIcon('lock')?></div><h1>Masuk ke monitoring</h1><p class="muted auth-form-description">Gunakan akun Anda untuk membuka riwayat dan monitoring lengkap.</p>
    <?php if($error):?><div class="alert alert-danger" role="alert"><?=mrtgHtml($error)?></div><?php endif;?>
    <form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=mrtgCsrf()?>">
      <label for="username">Username</label><input class="form-control" id="username" name="username" maxlength="80" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Masukkan username" required>
      <label for="password">Password</label><input class="form-control" id="password" name="password" type="password" maxlength="4096" autocomplete="current-password" placeholder="Masukkan password" required>
      <button class="btn btn-primary auth-submit" type="submit">Masuk ke monitoring <?=mrtgIcon('arrow')?></button>
    </form>
    <div class="auth-public-note"><?=mrtgIcon('home')?><span>Hanya melihat status terkini? <a href="index.php">Buka portal utama</a></span></div>
  </section>
</div>
<?php mrtgPageEnd();
