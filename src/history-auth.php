<?php
declare(strict_types=1);
require_once __DIR__.'/history-db.php';
function mrtgSessionStart():void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1');
    session_name('HNGMRTG');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    header('Cache-Control: no-store, private, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
function mrtgAuthUser():?array {
    mrtgSessionStart();
    $id=(int)($_SESSION['user_id']??0);
    if(!$id)return null;
    $user=mrtgQuery('SELECT id,username,password_hash,password_version,enabled FROM users WHERE id=?','i',[$id])->get_result()->fetch_assoc();
    if(!mrtgSessionValid($_SESSION,$user?:null,time())){$_SESSION=[];return null;}
    $_SESSION['last_seen']=time();
    return $user;
}
function mrtgRequireLogin(bool $json=false):array {
    try{$user=mrtgAuthUser();}
    catch(Throwable $e){http_response_code(503);header('Content-Type: '.($json?'application/json':'text/plain').'; charset=utf-8');echo $json?'{"ok":false,"error":"Layanan login sementara tidak tersedia"}':'Layanan login sementara tidak tersedia.';exit;}
    if($user)return $user;
    if($json){http_response_code(401);header('Content-Type: application/json; charset=utf-8');echo '{"ok":false,"error":"Silakan login untuk melihat riwayat"}';exit;}
    header('Location: login.php',true,302);exit;
}
function mrtgCsrf():string {
    mrtgSessionStart();
    if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function mrtgVerifyCsrf():void {
    mrtgSessionStart();
    if(!isset($_SESSION['csrf']) || !is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])){
        http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo 'Sesi formulir tidak valid. Muat ulang halaman.';exit;
    }
}
function mrtgRateKeys(string $username,string $ip):array {
    $secret=mrtgHistoryConfig()['secret'];
    return [hash_hmac('sha256','ip:'.$ip,$secret),hash_hmac('sha256','user:'.strtolower($username),$secret)];
}
function mrtgRateAllowed(string $username,string $ip,int $now):bool {
    foreach(mrtgRateKeys($username,$ip) as $key){
        $row=mrtgQuery('SELECT blocked_until FROM auth_limiter WHERE fingerprint=?','s',[$key])->get_result()->fetch_assoc();
        if($row && (int)$row['blocked_until']>$now)return false;
    }
    return true;
}
function mrtgNoteFailure(string $username,string $ip,int $now):void {
    foreach(mrtgRateKeys($username,$ip) as $key)mrtgQuery('INSERT INTO auth_limiter(fingerprint,attempts,last_attempt,blocked_until) VALUES(?,1,?,0) ON DUPLICATE KEY UPDATE attempts=IF(last_attempt<=?,1,attempts+1),blocked_until=IF(attempts>=8,?,0),last_attempt=?','siiii',[$key,$now,$now-900,$now+900,$now]);
}
function mrtgClearRate(string $username,string $ip):void {
    foreach(mrtgRateKeys($username,$ip) as $key)mrtgQuery('DELETE FROM auth_limiter WHERE fingerprint=?','s',[$key]);
}
function mrtgAttemptLogin(string $username,string $password,string $ip):bool {
    mrtgSessionStart();$now=time();
    $user=mrtgQuery('SELECT id,username,password_hash,password_version,enabled FROM users WHERE username=?','s',[$username])->get_result()->fetch_assoc();
    $identity=$user?(string)$user['username']:$username;
    if(!mrtgRateAllowed($identity,$ip,$now)){http_response_code(429);return false;}
    $hash=$user['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    $valid=strlen($password)<=4096 && password_verify($password,$hash);
    if(!$valid || !$user || !(int)$user['enabled']){mrtgNoteFailure($identity,$ip,$now);return false;}
    mrtgClearRate($identity,$ip);
    $_SESSION=[];session_regenerate_id(true);
    $_SESSION=['user_id'=>(int)$user['id'],'password_version'=>(int)$user['password_version'],'created_at'=>$now,'last_seen'=>$now];
    return true;
}
function mrtgChangePassword(array $user,string $current,string $new,string $confirmation):bool {
    if(strlen($current)>4096 || !password_verify($current,$user['password_hash']) || !mrtgNewPasswordValid($new,$confirmation))return false;
    $hash=password_hash($new,PASSWORD_DEFAULT);
    $query=mrtgQuery('UPDATE users SET password_hash=?,password_version=password_version+1 WHERE id=? AND password_version=?','sii',[$hash,(int)$user['id'],(int)$user['password_version']]);
    if($query->affected_rows!==1)return false;
    session_regenerate_id(true);$_SESSION['password_version']=(int)$user['password_version']+1;
    $_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['last_seen']=time();
    return true;
}
