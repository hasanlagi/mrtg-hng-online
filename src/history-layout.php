<?php
declare(strict_types=1);
function mrtgHtml(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function mrtgIcon(string $name):string {
    $paths=[
        'network'=>'<rect x="8" y="3" width="8" height="6" rx="1.5"/><path d="M12 9v5M4 17v-3h16v3"/><rect x="1" y="17" width="6" height="4" rx="1"/><rect x="9" y="17" width="6" height="4" rx="1"/><rect x="17" y="17" width="6" height="4" rx="1"/>',
        'home'=>'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
        'pulse'=>'<path d="M2 12h5l3-8 4 16 3-8h5"/>',
        'chart'=>'<path d="M4 3v17h17M8 15l4-5 4 3 5-7"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'logout'=>'<path d="M9 4H4v16h5M13 12h9m-4-4 4 4-4 4"/>',
        'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
    ];
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['network']).'</svg>';
}
function mrtgPageStart(string $title,?array $user=null,string $appearance='dashboard'):void {
    header('Cache-Control: no-store, private, max-age=0');
    header("Content-Security-Policy: frame-ancestors 'self'");
    $isLogin=$appearance==='login';
    $current=basename((string)($_SERVER['SCRIPT_NAME']??''));
    $links=[['index.php','Portal utama','home'],['history.php','Riwayat ping','pulse'],['traffic-history.php','Trafik interface','chart'],['account.php','Akun','user']];
    ?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#08131f"><title><?=mrtgHtml($title)?> · HNG MRTG</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="history.css?v=20261103e" rel="stylesheet"></head>
<body class="<?=$isLogin?'auth-page':'dashboard-page'?>"><a class="skip-link" href="#mainContent">Lewati navigasi</a><div class="history-main"><header class="history-nav">
  <a class="history-brand" href="index.php"><span class="brand-symbol"><?=mrtgIcon('network')?></span><span><b>HNG <span>Network Monitoring</span></b><small><?=$isLogin?'MRTG · Network overview':mrtgHtml($title)?></small></span></a>
  <nav class="history-actions" aria-label="Navigasi utama">
    <?php foreach($links as [$href,$label,$icon]): if(!$user&&$href!=='index.php')continue;?>
    <a class="nav-link<?=$current===$href?' is-active':''?>" href="<?=$href?>"<?=$current===$href?' aria-current="page"':''?>><?=mrtgIcon($icon)?><span><?=$label?></span></a>
    <?php endforeach; if($user):?><form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?=mrtgCsrf()?>"><button class="nav-link nav-logout" type="submit"><?=mrtgIcon('logout')?><span>Keluar</span></button></form><?php endif;?>
  </nav>
</header><main id="mainContent"><?php
}
function mrtgPageEnd():void{echo '</main><footer class="history-footer"><span>HNG Network Monitoring</span><span>PPPoE · Ping · Trafik interface</span></footer></div></body></html>';}
