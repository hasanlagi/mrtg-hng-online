<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/RouterOSAPI.php';

function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function jsonOut(mixed $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function allRouters(bool $includeDisabled=true): array {
    $sql='SELECT * FROM routers'.($includeDisabled?'':' WHERE enabled=1').' ORDER BY id ASC';
    $r=db()->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function routerById(int $id): ?array {
    $s=db()->prepare('SELECT * FROM routers WHERE id=? LIMIT 1');
    $s->bind_param('i',$id); $s->execute();
    return $s->get_result()->fetch_assoc() ?: null;
}

/**
 * Read only PPPoE data from a router. One API connection per router.
 * No interface, traffic, ping, CPU or health calls are made here.
 */
function pppoeSnapshot(array $r): array {
    $api = new RouterOSAPI(
        (string)$r['host'],
        (int)$r['port'],
        (string)$r['username'],
        (string)$r['password'],
        (bool)$r['use_ssl']
    );
    try {
        $api->connect();
        $activeRows = $api->command('/ppp/active/print');
        $secretRows = $api->command('/ppp/secret/print');

        $activeNames = [];
        foreach ($activeRows as $a) {
            $name = trim((string)($a['name'] ?? ''));
            if ($name !== '') $activeNames[$name] = true;
        }

        $active=[];
        foreach ($activeRows as $a) {
            $active[] = [
                'router_id'=>(int)$r['id'],
                'router'=>$r['name'],
                'name'=>(string)($a['name'] ?? ''),
                'service'=>(string)($a['service'] ?? ''),
                'caller_id'=>(string)($a['caller-id'] ?? $a['caller_id'] ?? ''),
                'address'=>(string)($a['address'] ?? ''),
                'uptime'=>(string)($a['uptime'] ?? ''),
                'encoding'=>(string)($a['encoding'] ?? ''),
                'profile'=>(string)($a['profile'] ?? ''),
            ];
        }

        $inactive=[];
        foreach ($secretRows as $s) {
            $name=trim((string)($s['name'] ?? ''));
            if ($name === '' || isset($activeNames[$name])) continue;
            $inactive[] = [
                'router_id'=>(int)$r['id'],
                'router'=>$r['name'],
                'name'=>$name,
                'service'=>(string)($s['service'] ?? ''),
                'profile'=>(string)($s['profile'] ?? ''),
                'disabled'=>((string)($s['disabled'] ?? 'false')) === 'true',
                'comment'=>(string)($s['comment'] ?? ''),
                'last_logged_out'=>(string)($s['last-logged-out'] ?? $s['last-logged-out-time'] ?? $s['last-logged-out-at'] ?? ''),
                'last_disconnect_reason'=>(string)($s['last-disconnect-reason'] ?? $s['last-disconnect'] ?? $s['disconnect-reason'] ?? ''),
            ];
        }

        return ['ok'=>true,'active'=>$active,'inactive'=>$inactive,'error'=>null];
    } finally {
        $api->close();
    }
}
