<?php
declare(strict_types=1);
require_once __DIR__.'/src/helpers.php';
require_once __DIR__.'/src/history-auth.php';
$historyUser=mrtgAuthUser();
session_write_close();


set_error_handler(function(int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$action=$_GET['action'] ?? '';
try {
    if ($action === 'pppoe') {
        $allActive=[]; $allInactive=[]; $errors=[];
        foreach (allRouters(false) as $r) {
            try {
                $snap=pppoeSnapshot($r);
                $allActive=array_merge($allActive,$snap['active']);
                $allInactive=array_merge($allInactive,$snap['inactive']);
            } catch (Throwable $e) {
                $errors[]=['router'=>$r['name'],'host'=>$r['host'],'error'=>$e->getMessage()];
            }
        }
        jsonOut([
            'ok'=>true,
            'active_count'=>count($allActive),
            'inactive_count'=>count($allInactive),
            'active'=>$historyUser?$allActive:[],
            'inactive'=>$allInactive,
            'errors'=>$errors,
            'ts'=>time()
        ]);
    }
    if ($action === 'routers') {
        $rows=[];
        foreach (allRouters(false) as $r) $rows[]=['id'=>(int)$r['id'],'name'=>$r['name'],'host'=>$r['host']];
        jsonOut(['ok'=>true,'data'=>$rows]);
    }
    jsonOut(['ok'=>false,'error'=>'Action tidak valid'],400);
} catch (Throwable $e) {
    jsonOut(['ok'=>false,'error'=>$e->getMessage()],500);
}
