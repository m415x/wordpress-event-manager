<?php
declare(strict_types=1);
$wpLoad = dirname(__DIR__, 6) . '/wp-load.php';
if (!is_file($wpLoad)) {fwrite(STDERR, "No WordPress runtime\n");exit(2);}
require_once $wpLoad;
require_once dirname(__DIR__, 3) . '/wordpress-event-manager.php';
$key=$argv[1] ?? '';
$start=$argv[2] ?? '';
if (!$key || !$start || !is_file($start)) {exit(2);}
$deadline=microtime(true)+8;
while (trim((string)file_get_contents($start))!=='GO') {
    if(microtime(true)>$deadline){fwrite(STDERR,"Barrier timeout\n");exit(3);}
    usleep(10000);
}
$result=(new WEM_Seating_Public_Abuse_Guard())->record_attempt($key, 1, 60);
fwrite(STDOUT,$result ? "ALLOW\n" : "DENY\n");
