<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use PHPUnit\Framework\TestCase;

/** Real parallel PHP/WordPress sessions; not a WP_UnitTestCase outer transaction. */
final class SeatingPublicAbuseConcurrentTest extends TestCase
{
    public function testExactlyOneParallelAttemptIsAllowedAtLimitOne(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        self::assertSame(0, (int)$wpdb->get_var('SELECT @@in_transaction'));
        self::assertTrue(function_exists('proc_open'));
        $key='wem-t6-' . wp_generate_uuid4();
        $barrier=tempnam(sys_get_temp_dir(),'wem-abuse-');
        self::assertNotFalse($barrier);
        $workers=[];
        try {
            for($i=0;$i<6;$i++){
                $pipes=[];
                $process=proc_open(
                    [PHP_BINARY,__DIR__ . '/helpers/abuse-attempt-worker.php',$key,$barrier],
                    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes
                );
                self::assertIsResource($process);
                fclose($pipes[0]);
                $workers[]=[$process,$pipes];
            }
            self::assertNotFalse(file_put_contents($barrier,'GO'));
            $results=[];
            foreach($workers as $index=>&$worker){
                [$process,$pipes]=$worker;
                $deadline=microtime(true)+12;
                while(proc_get_status($process)['running']){
                    if(microtime(true)>$deadline){self::fail('Worker timeout '.$index);}
                    usleep(10000);
                }
                $out=trim((string)stream_get_contents($pipes[1]));
                $err=trim((string)stream_get_contents($pipes[2]));
                fclose($pipes[1]);fclose($pipes[2]);
                $exit=proc_close($process);
                $worker=[null,[]];
                self::assertSame('',$err,'Worker stderr: '.$err);
                self::assertContains($exit,[0,-1]);
                self::assertContains($out,['ALLOW','DENY']);
                $results[]=$out;
            }
            unset($worker);
            self::assertSame(1,count(array_filter($results,static fn($v)=>$v==='ALLOW')),
                'Concurrent attempts must not each see a post-increment shared count and all deny.');
            self::assertSame(5,count(array_filter($results,static fn($v)=>$v==='DENY')));
        } finally {
            foreach($workers as $worker){
                if(is_resource($worker[0])) {proc_terminate($worker[0]);proc_close($worker[0]);}
                foreach($worker[1] as $pipe){if(is_resource($pipe))fclose($pipe);}
            }
            if(is_file($barrier))unlink($barrier);
            $option='wem_i3_rate_' . hash_hmac('sha256',$key,wp_salt('auth'));
            $wpdb->delete($wpdb->options,['option_name'=>$option]);
        }
    }
}
