<?php
declare(strict_types=1);
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/history-auth.php';
mrtgRequireLogin(true);

try {
    $d = db();
    jsonOut(['ok'=>true,'php'=>PHP_VERSION,'mysql'=>$d->server_info,'db'=>DB_NAME]);
} catch (Throwable $e) {
    jsonOut(['ok'=>false,'error'=>$e->getMessage()],500);
}
