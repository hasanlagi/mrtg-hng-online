<?php
declare(strict_types=1);
require_once __DIR__.'/src/history-auth.php';
mrtgRequireLogin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
mrtgVerifyCsrf();$_SESSION=[];
setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_destroy();header('Location: index.php',true,303);exit;
