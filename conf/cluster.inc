<?php

$GLOBALS['collection'] = array (
  
    'web' => array (
        'source' => 'MyCluster',
        'metrics' => array (
            'rps' => array ('apache-80_rps'),
        ),
        'hosts' => array (
            'web1.domainname',
        ),
    ),


    'myapp' => array (
        'source' => 'MyCluster',
        'metrics' => array (
            'mem' => array ('jvm_mem_used','/jvm_mem_max'),
            'disk' => array('-myapp_disk_free', '/myapp_disk_total'),
            'rps' => array ('myapp_rps'),
        ),
        'hosts' => array (
            'myapp1.domainname',
            'myapp2.domainname',
            'myapp3.domainname',
            'myapp4.domainname',
        ),
    ),

  
    'db' => array (
        'source' => 'MyCluster',
        'metrics' => array (
            'disk' => array ('-mysql-3306_disk_free','/mysql-3306_disk_total'),
            'rps' => array ('mysql-3306_alter_table_qps','mysql-3306_create_table_qps','mysql-3306_delete_multi_qps','mysql-3306_delete_qps','mysql-3306_drop_table_qps','mysql-3306_insert_qps','mysql-3306_insert_select_qps','mysql-3306_select_qps','mysql-3306_truncate_qps','mysql-3306_update_multi_qps','mysql-3306_update_qps'),
        ),
        'hosts' => array (
            'db1.domainname',
        ),
    ),
  
);

?>
