<?php
/**
 * WEM-74 ephemeral, persistent-InnoDB FK proof. Executed via wp eval-file.
 * DOES NOT install FK in production plugin; this is only a diagnostic probe.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(2); }
global $wpdb;
$parent = WEM_Event_Table_Schema::table_name();
$child = WEM_Table_Reservation_Schema::table_name();
$index = 'wem_event_table_id_event';
$fk = 'wem_reservation_table_event_fk';
$fail = static function ($reason) { WP_CLI::error('WEM74 FK PROBE FAIL: ' . $reason); };
$run = static function ($sql) use ($wpdb, $fail) {
    $result = $wpdb->query($sql);
    if ($result === false) { $fail($wpdb->last_error . ' SQL=' . $sql); }
    return $result;
};
$assert = static function ($condition, $label) use ($fail) {
    if (!$condition) { $fail($label); }
    WP_CLI::log('PASS: ' . $label);
};
$quote = static function ($name) { return '`' . str_replace('`', '``', $name) . '`'; };
$p = $quote($parent);
$r = $quote($child);
$db = $wpdb->get_var('SELECT DATABASE()');
$assert(is_string($db) && $db !== '', 'database resolved');

WEM_Event_Table_Schema::install();
WEM_Table_Reservation_Schema::install();

$assert($wpdb->get_var("SELECT COUNT(*) FROM {$p}") === '0', 'new event table empty');
$assert($wpdb->get_var("SELECT COUNT(*) FROM {$r}") === '0', 'new reservation table empty');
$types = $wpdb->get_results($wpdb->prepare(
    'SELECT TABLE_NAME, ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME IN (%s,%s)',
    $db, $parent, $child
), ARRAY_A);
$assert(count($types) === 2, 'both tables present in information_schema');
foreach ($types as $row) {
    $assert(strtoupper((string)$row['ENGINE']) === 'INNODB' && $row['TABLE_TYPE'] === 'BASE TABLE', $row['TABLE_NAME'] . ' persistent InnoDB');
}
$columns = $wpdb->get_results($wpdb->prepare(
    "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=%s AND ((TABLE_NAME=%s AND COLUMN_NAME IN ('id','event_term_id')) OR
      (TABLE_NAME=%s AND COLUMN_NAME IN ('table_id','event_term_id')))",
    $db, $parent, $child
), ARRAY_A);
$assert(count($columns) === 4, 'referential column metadata complete');
foreach ($columns as $column) {
    $assert(strtolower($column['COLUMN_TYPE']) === 'bigint(20) unsigned'
        && $column['IS_NULLABLE'] === 'NO',
        $column['TABLE_NAME'] . '.' . $column['COLUMN_NAME'] . ' unsigned BIGINT NOT NULL');
}

$install = static function () use ($wpdb,$db,$parent,$child,$p,$r,$index,$fk,$run,$assert,$fail) {
    WEM_Event_Table_Schema::install();
    WEM_Table_Reservation_Schema::install();
    $keys = $wpdb->get_results($wpdb->prepare(
        'SELECT INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s ORDER BY SEQ_IN_INDEX',
        $db,$parent,$index
    ), ARRAY_A);
    if (!$keys) {
        $run("ALTER TABLE {$p} ADD INDEX `{$index}` (`id`,`event_term_id`)");
        $keys = $wpdb->get_results($wpdb->prepare(
            'SELECT INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s ORDER BY SEQ_IN_INDEX',
            $db,$parent,$index
        ), ARRAY_A);
    }
    $assert(count($keys)===2 && $keys[0]['COLUMN_NAME']==='id'
        && $keys[1]['COLUMN_NAME']==='event_term_id', 'composite parent index exact');

    $constraint = $wpdb->get_results($wpdb->prepare(
        'SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s',
        $db,$child,$fk
    ), ARRAY_A);
    if (!$constraint) {
        $run("ALTER TABLE {$r} ADD CONSTRAINT `{$fk}` FOREIGN KEY (`table_id`,`event_term_id`) REFERENCES {$p} (`id`,`event_term_id`) ON UPDATE RESTRICT ON DELETE RESTRICT");
        $constraint = $wpdb->get_results($wpdb->prepare(
            'SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s',
            $db,$child,$fk
        ), ARRAY_A);
    }
    $assert(count($constraint)===1 && $constraint[0]['REFERENCED_TABLE_NAME']===$parent
        && $constraint[0]['UPDATE_RULE']==='RESTRICT' && $constraint[0]['DELETE_RULE']==='RESTRICT',
        'single FK with exact RESTRICT rules');
    $mapping = $wpdb->get_results($wpdb->prepare(
        'SELECT COLUMN_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s ORDER BY ORDINAL_POSITION',
        $db,$child,$fk
    ), ARRAY_A);
    $assert(count($mapping)===2 && $mapping[0]['COLUMN_NAME']==='table_id'
       && $mapping[0]['REFERENCED_COLUMN_NAME']==='id'
       && $mapping[1]['COLUMN_NAME']==='event_term_id'
       && $mapping[1]['REFERENCED_COLUMN_NAME']==='event_term_id', 'FK column mapping exact');
};
$install();
WP_CLI::log("PARENT DDL: " . $wpdb->get_row("SHOW CREATE TABLE {$p}",ARRAY_N)[1]);
WP_CLI::log("CHILD DDL: " . $wpdb->get_row("SHOW CREATE TABLE {$r}",ARRAY_N)[1]);

$assert($wpdb->insert($parent, ['event_term_id'=>7001,'table_code'=>'WEM74-PROBE-A','capacity'=>2],['%d','%s','%d'])===1,'table event A created');
$id=(int)$wpdb->insert_id;
$valid = static function ($guest, $tableId, $event) use ($wpdb,$child) {
    return $wpdb->insert($child,[
        'guest_id'=>$guest, 'table_id'=>$tableId, 'event_term_id'=>$event,
        'status'=>'active','active_slot'=>1,'created_at'=>'2026-10-21 12:00:00'
    ],['%d','%d','%d','%s','%d','%s']);
};
$assert($valid(7001,$id,7001)===1,'valid same-event reservation accepted');
$wpdb->suppress_errors(true);
$assert($valid(7002,$id,7002)===false,'cross-event reservation rejected');
$assert($valid(7003,$id+1000000,7001)===false,'missing table reference rejected');
$wpdb->suppress_errors(false);
$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$r}")===1,'invalid writes left no reservation rows');

$install();
$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$r}")===1,'reinstall retained valid reservation');
$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}")===1,'reinstall retained event table');
$wpdb->suppress_errors(true);
$assert($valid(7004,$id,7002)===false,'cross-event still rejected after reinstall');
$assert($valid(7005,$id+1000000,7001)===false,'missing table still rejected after reinstall');
$update = $wpdb->update($parent,['event_term_id'=>7002],['id'=>$id],['%d'],['%d']);
$assert($update===false,'ON UPDATE RESTRICT denied parent event reassignment');
$deleted=$wpdb->delete($parent,['id'=>$id],['%d']);
$assert($deleted===false,'ON DELETE RESTRICT denied referenced parent delete');
$wpdb->suppress_errors(false);
$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$r}")===1,'final reservation unchanged');
$assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}")===1,'final table unchanged');
WP_CLI::success('WEM-74 composite FK ephemeral persistent-InnoDB probe PASS');
