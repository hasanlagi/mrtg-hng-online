<?php
declare(strict_types=1);

$mrtgRouterConfigFile = getenv('MRTG_ROUTER_CONFIG') ?: '/etc/hng-mrtg/router-db.php';
if (!is_readable($mrtgRouterConfigFile)) {
    throw new RuntimeException('Konfigurasi database router belum tersedia.');
}
require $mrtgRouterConfigFile;
unset($mrtgRouterConfigFile);

const APP_NAME = 'IONnet Network Monitoring';
const API_TIMEOUT = 3.0;
const DEFAULT_REFRESH_MS = 1000;
