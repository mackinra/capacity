<?php

$GLOBALS['cfg'] = array (
    'env_name' => 'Production',
    'rrd_frontend_url' => 'http://admin.domainname/ganglia/?r=week&s=by%2520hostname&c=',
    'rrd_frontend_url_host_param' => 'h',
    'wiki_edit_url' => 'http://admin.domainname/mediawiki/index.php?action=edit&title=Capacity_Notes_for_',
    'rrd_resolution_mins' => 30,  // number of minutes to average over, so quick spikes/outliers are not used
    'config_backups_to_keep' => 7,  // how many backups to rotate of cluster.inc config
    'popup_graphs' => 0,
    'graph_height' => 300,
    'highlighted_cluster_pattern' => '/(^db|\-db)/i',
    'highlighted_cluster_color' => '#7ACAFF',
    'rrdtool' => 'rrdtool',
    'gnuplot' => 'gnuplot',
    'font_path' => '/usr/share/fonts/truetype',
);

$GLOBALS['db'] = array (
    'host'     => 'localhost',
    'database' => 'capacity',
    'user'     => 'capacity',
    'password' => 'planning',
);

$GLOBALS['defaults'] = array (
    'metrics' => array (
        'cpu' => array('cpu_user', 'cpu_system', 'cpu_nice'),
        'mem' => array('-mem_free', '-mem_buffers', '-mem_cached', '/mem_total'),
        'disk' => array('-disk_free', '/disk_total'),
    ),
    'rrd_root' => '/var/lib/ganglia/rrds/',
    'rrd_suffix' => '.rrd',
);

require_once('cluster.inc');

?>
