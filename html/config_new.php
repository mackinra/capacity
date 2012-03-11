<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007
 */

error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', 'On');

//require_once('capacity.inc');
require_once('../conf/grab_rrd_data.inc');


$collection = $GLOBALS['collection'];
ksort($collection);
$sources = array();

function get_rrd_sources($cluster = 'defaults') {
    GLOBAL $sources;
    if (!isset($GLOBALS[$cluster]['rrd_root'])) $cluster = 'defaults';
    $path = $GLOBALS[$cluster]['rrd_root'];
    if (isset($sources[$path]) && is_array($sources[$path])) {
        return $sources[$path];
    }
    $dirs = array();
    $dh  = @opendir($path);
    while ($dh && is_resource($dh) && (($filename = readdir($dh)) !== FALSE)) {
        if ($filename{0} != '.' && is_dir($path.'/'.$filename)) {
            $dirs[] = $filename;
        }
    }
    natcasesort($dirs);
    $sources[$path] = $dirs;
    return $sources[$path];
}


print "<html><head><title>Site Capacity Tool Config</title>\n";
print "<style type=\"text/css\">\n";
print "body {background-color: #ffffff !important; color: #000000; font: 15px arial,helvetica,sans-serif;}\n";
print "</style>\n";
print "</head><body>";
print '<form name="config" action="config_new_update.php" method="POST">';
if (!empty($_REQUEST['status'])) {
    switch ($_REQUEST['status']) {
        case 'saved':
            print "<B>Configuration saved at ".date('Y-m-d H:i:s')."</B><P>";
            break;
        case 'error':
            print "<B><I>Error parsing configuration array.</I></B><P>";
            break;
    }
}
print '<a href=".">&lt;&lt; Back</a><br>';

$srcs = get_rrd_sources();
foreach ($srcs as $source) {
    print "$source<br>\n";
}

foreach ($collection as $cluster => $config) {

    print "$cluster<br>\n";
    print "<ul>\n";
    foreach ($config as $key => $value) {
        print "<li>$key => ";
        if (is_array($value)) {
            print "<ul>\n";
            foreach ($value as $key1 => $value1) {
                print "<li>$key1\n";
                print "<ul>\n";
                if (is_array($value1)) {
                    foreach ($value1 as $value2) {
                        print "<li>$value2\n";
                    }
                } else {
                    print "<li>$value1\n";
                }
                print "</ul>\n";
            }
            print "</ul>\n";
        } else {
            print "$value\n";
        }
    }
    print "</ul>\n";
/*
        'source' => 'alps\ non-bcs\ cluster',
        'metrics' => array (
            'cpu' => array('cpu_user', 'cpu_system', 'cpu_nice'),
            'mem' => array('-mem_free', '-mem_buffers', '-mem_cached', '/mem_total'),
            'disk' => array('-disk_free', '/disk_total'),
            'rps' => array ('tomcat-8080_rps'),
        ),
        'hosts' => array (
        ),
        'rrd_root' => '/var/lib/ganglia/rrds/',
        'rrd_suffix' => '.rrd',
*/
}

print '<br><input type="submit" name="submit2" value="Apply Changes">';
print '</form>';
print '</body></html>';
exit;


?>

