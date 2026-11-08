<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
require_once __DIR__.'/src/history-layout.php';
$user=mrtgRequireLogin();$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    mrtgVerifyCsrf();
    $values=[];foreach(['current_password','new_password','confirmation'] as $key)$values[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
    try{
        if(mrtgChangePassword($user,$values['current_password'],$values['new_password'],$values['confirmation']))$success='Password berhasil diganti. Sesi lain telah dikeluarkan.';
        else{http_response_code(400);$error='Password saat ini harus benar. Password baru minimal 10 karakter dan konfirmasinya harus sama.';}
    }catch(Throwable $e){http_response_code(503);$error='Password belum dapat diganti. Coba lagi nanti.';}
}
mrtgPageStart('Ganti password',$user);
?><section class="history-panel login-panel account-panel"><div class="form-emblem"><?=mrtgIcon('lock')?></div><h1>Ganti password</h1><p class="muted">Kelola keamanan akun <b><?=mrtgHtml($user['username'])?></b>.</p><?php if($error):?><div class="alert alert-danger" role="alert"><?=mrtgHtml($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success" role="status"><?=mrtgHtml($success)?></div><?php endif;?><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=mrtgCsrf()?>"><label for="current">Password saat ini</label><input id="current" name="current_password" type="password" class="form-control" autocomplete="current-password" required><label for="new">Password baru</label><input id="new" name="new_password" type="password" class="form-control" minlength="10" maxlength="4096" autocomplete="new-password" aria-describedby="passwordHelp" required><div class="field-help" id="passwordHelp">Gunakan minimal 10 karakter.</div><label for="confirmation">Ulangi password baru</label><input id="confirmation" name="confirmation" type="password" class="form-control" minlength="10" maxlength="4096" autocomplete="new-password" required><button class="btn btn-primary auth-submit" type="submit">Simpan password <?=mrtgIcon('check')?></button></form></section><?php mrtgPageEnd();
