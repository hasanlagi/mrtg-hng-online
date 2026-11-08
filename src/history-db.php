<?php
declare(strict_types=1);
require_once __DIR__.'/history-core.php';
function mrtgHistoryConfig():array {
    static $config;
    if($config===null){
        $file=getenv('MRTG_HISTORY_CONFIG')?:'/etc/hng-mrtg/history.json';
        $config=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    }
    return $config;
}
function mrtgHistoryDb():mysqli {
    static $db;
    if($db instanceof mysqli)return $db;
    $c=mrtgHistoryConfig();
    $db=mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT,3);
    $db->options(MYSQLI_OPT_READ_TIMEOUT,5);
    $db->real_connect($c['host'],$c['user'],$c['password'],$c['database']);
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone='+00:00'");
    return $db;
}
function mrtgQuery(string $sql,string $types='',array $params=[]):mysqli_stmt {
    $statement=mrtgHistoryDb()->prepare($sql);
    if($types!=='')$statement->bind_param($types,...$params);
    $statement->execute();
    return $statement;
}
