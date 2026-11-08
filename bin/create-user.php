<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/src/history-auth.php';
$username=$argv[1]??'';$password=getenv('MRTG_INITIAL_PASSWORD')?:'';
if(!preg_match('/^[a-zA-Z0-9_.@-]{1,80}$/',$username) || !mrtgNewPasswordValid($password,$password)){fwrite(STDERR,"Invalid user input\n");exit(1);}
$existing=mrtgQuery('SELECT id FROM users WHERE username=?','s',[$username])->get_result()->fetch_assoc();
if($existing){echo "Account already exists; password unchanged\n";exit;}
$hash=password_hash($password,PASSWORD_DEFAULT);
mrtgQuery('INSERT INTO users(username,password_hash) VALUES(?,?)','ss',[$username,$hash]);
echo "Requested web account created with a hashed password\n";
