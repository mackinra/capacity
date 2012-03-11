<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007
 */

error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', 'On');

require_once('capacity.inc');

$ref = $_REQUEST['referer'];
if (!$ref) $ref = $_SERVER['HTTP_REFERER'];

if (!isset($_POST['value'])) {
    $benchmark = db_query('SELECT max_rps FROM benchmarks WHERE cluster_name='.quote($_REQUEST['cluster'])); 
    print "<html><head><title>Site Capacity Tool - Edit Benchmark Value</title>\n";
    print "<style type=\"text/css\">\n";
    print "body {background-color: #ffffff !important; color: #000000; font: 15px arial,helvetica,sans-serif;}\n";
    print "</style>\n";
    print "</head><body>";
    print '<form name="config" action="edit_benchmark.php" method="POST">';
    print "<input type=\"hidden\" name=\"cluster\" value=\"{$_REQUEST['cluster']}\">";
    print "<input type=\"hidden\" name=\"referer\" value=\"$ref\">";
    if (!empty($_REQUEST['status'])) {
        switch ($_REQUEST['status']) {
            case 'error':
                print "<B><I>Unable to store specified value.</I></B><P>";
                break;
        }
    }
    print "<a href=\"$ref\">&lt;&lt; Back</a><br>";
    print '<p>Enter benchmarked max req/sec for <b><u>'.$_GET['cluster'].'</u></b> cluster: <p>';
    print '<input type="text" name="value" value="'.(!empty($benchmark['max_rps']) ? floatval($benchmark['max_rps']) : '').'">';
    print '<input type="submit" name="submit" value="Save">';
    print '  <i>(leave blank to delete)</i>';
    print '</form>';
    print '<script type="text/javascript">document.config.value.focus();</script>';
    print '</body></html>';
} else {
    if (is_numeric($_POST['value']) || empty($_POST['value'])) {
        $query = 'REPLACE INTO benchmarks (cluster_name, max_rps, last_updated, ip_addr) VALUES ('.quote($_POST['cluster']).', '.($_POST['value']==0?'NULL':$_POST['value']).", NOW(), INET_ATON('{$_SERVER['REMOTE_ADDR']}'))";
error_log("query=$query");
        $retval = db_query($query, false);
        if ($retval) {
            header ("Location: $ref");
            exit;
        }
    }
    header ('Location: edit_benchmark.php?cluster='.$_POST['cluster'].'&status=error&referer='.urlencode($ref));
    exit;
}

?>

