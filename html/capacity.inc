<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007
 */

require_once('../conf/grab_rrd_data.inc');

function db_query ($query, $optimized=true)
{
    $host = $GLOBALS['db']['host'];
    $user = $GLOBALS['db']['user'];
    $password = $GLOBALS['db']['password'];
    $database = $GLOBALS['db']['database'];

    $link = mysql_connect( $host, $user, $password, TRUE );
    if (!$link) die ("ERROR: Unable to connect to db");

    if (!mysql_select_db($database, $link)) die ("ERROR: Unable to select db");
    
    $result = mysql_query($query, $link);
    //if (!$result) die ("ERROR: no results from history table\n\n$query");

    $rows = array();
    if (is_resource($result)) {
        while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
            $rows[] = $row;
        }
        mysql_free_result($result);
    }

    mysql_close($link);

    return $optimized && count($rows) == 1 ? $rows[0] : (is_bool($result) ? $result : $rows);
}

function quote ($data) {
    return "'".mysql_escape_string($data)."'";
}

function extend_metrics (&$cluster)
{
    $overall_used = 0;
    $rps_max = '';
    if (!empty($cluster['max_rps'])) {
        $rps_max = $cluster['max_rps'];
    } elseif ($cluster['cpu'] > 0) {
        $rps_max = $cluster['rps']*(100/$cluster['cpu']);
    }
    $rps_used = $rps_max != 0 ? $cluster['rps']/$rps_max*100 : 0;

    if (!$cluster['ex_rps'] || $rps_read > $cluster['ex_rps']) {
        $overall_used = $rps_used;
        $overall_used_cause = 'RPS';
    }
    if ($cluster['memory'] > $overall_used) {
        if (!$cluster['ex_memory'] || $cluster['memory'] > $cluster['ex_memory']) {
            $overall_used = $cluster['memory'];
            $overall_used_cause = 'Memory';
        }
    }
    if ($cluster['disk'] > $overall_used) {
        if (!$cluster['ex_disk'] || $cluster['disk'] > $cluster['ex_disk']) {
            $overall_used = $cluster['disk'];
            $overall_used_cause = 'Disk';
        }
    }

    $cluster['_rps_max'] = round($rps_max,1);
    $cluster['_rps_used'] = round($rps_used,1);
    $cluster['_overall_used'] = round($overall_used,1);
    $cluster['_overall_used_cause'] = $overall_used_cause;
    $cluster['_rps_cluster'] = round($cluster['rps']*$cluster['hosts'],1);
    $cluster['_rps_max_cluster'] = round($cluster['_rps_max']*$cluster['hosts'],1);
}

?>
