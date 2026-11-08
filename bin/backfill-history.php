<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/src/history-summary.php';
mrtgBackfillSummaries();
$row=mrtgHistoryDb()->query('SELECT COUNT(*) n FROM history_rollups')->fetch_assoc();
echo 'Historical summaries ready: '.$row['n']." buckets\n";
