#!/usr/bin/php
<?php

/* 
 * Capacity Planning Tool 
 * by Ross MacKinnon <ross@mackinnon.com> 
 * Copyright 2007
 */

require_once('../conf/grab_rrd_data.inc');

ini_set('memory_limit','128M');

$OPTIONS = get_arguments();
$DEBUG = 0;

foreach ($GLOBALS['collection'] as $cluster => $cluster_params) {

    if (isset($cluster_params['disabled']) && $cluster_params['disabled'] === true) continue;

    $params = merge_params($GLOBALS['defaults'], $cluster_params);
    $num = 0;
    $max_rps = null;
    $max_cpu = null;
    $max_mem = null;
    $max_disk = null;

    echo "\n[".date('Y-m-d H:i:s')."]\n";
  
    if ($OPTIONS['do_rps_alt'] != 2) {

        $max_cpu = min(100,round(get_max_value($cluster, 'cpu', $params, $OPTIONS['start'], $OPTIONS['end'], $num),1));
        echo "$cluster cpu max = $max_cpu%\n";
  
        if (isset($params['metrics']['rps_alt'])) {
            if ($OPTIONS['do_rps_alt'] > 0) {
                $max_rps = round(get_max_value($cluster, 'rps', $params['metrics']['rps_alt'], $OPTIONS['start'], $OPTIONS['end'], $num),1);
            }
        } else {
            $max_rps = round(get_max_value($cluster, 'rps', $params, $OPTIONS['start'], $OPTIONS['end'], $num),1);
        }
        echo "$cluster rps max = $max_rps\n";

        $max_mem = get_max_value($cluster, 'mem', $params, $OPTIONS['start'], $OPTIONS['end'], $num);
        if ($params['metrics']['mem'][0]{0} == '-') {
            // we actually have the min free mem -- calculate inverse for mem used
            $max_mem = min(100,($max_mem !== -1 ? (100 - round($max_mem*100,1)) : 0));
        } else {
            $max_mem = round($max_mem*100,1);
        }
        echo "$cluster mem max = $max_mem%\n";

        if (isset($params['metrics']['disk_mysql'])) {
            $max_disk = round(get_db_disk_usage($params['hosts']),1);
        } else {
            $free_disk = get_max_value($cluster, 'disk', $params, $OPTIONS['start'], $OPTIONS['end'], $num);
            $max_disk = $free_disk !== -1 ? (100 - round($free_disk*100,1)) : 0;
        }
    } else {
        if (isset($params['metrics']['rps_alt'])) {
            $max_rps = round(get_max_value($cluster, 'rps', $params['metrics']['rps_alt'], $OPTIONS['start'], $OPTIONS['end'], $num),1);
            echo "$cluster rps max = $max_rps\n";
        } else {
            continue;
        }
    }
    
    $max_disk = min(100,$max_disk);
    echo "$cluster disk max = $max_disk%\n";

    $num = round($num,0);
    if ($num) echo "$cluster hosts = $num\n";
    echo "\n";

//    if ($max_rps > 1000000) $max_rps = null; // prevent bogus rps numbers getting into history

    if (!$DEBUG) store_in_db ($cluster, $max_cpu, $max_rps, $max_mem, $max_disk, $num);
}


function store_in_db ($cluster, $max_cpu, $max_rps, $max_mem, $max_disk, $num)
{
    global $OPTIONS, $DEBUG;
    $host = $OPTIONS['host'];
    $user = $OPTIONS['user'];
    $password = $OPTIONS['password'];
    $database = $OPTIONS['database'];
    $table = 'history';

    if ($DEBUG > 0) echo ("Connecting to $host:$user:$password:$database for table $table...\n");

    $link = mysql_connect( $host, $user, $password, TRUE );
    if (!$link) die ("ERROR: Unable to connect to db");

    $q = null;
    if ($OPTIONS['do_rps_alt'] == 1) {
        $q = "REPLACE INTO $database.$table (post_date,cluster_name,hosts,rps,cpu,memory,disk) VALUES (CURRENT_DATE(),".quote($cluster).",$num,$max_rps,$max_cpu,$max_mem,$max_disk)";
    } elseif ($OPTIONS['do_rps_alt'] == 2) {
        if (!is_null($max_rps)) {
            if (mysql_query("UPDATE $database.$table SET rps=$max_rps WHERE post_date=CURRENT_DATE() AND cluster_name=".quote($cluster), $link) === FALSE) {
                die ("ERROR: Unable to update data in db");
            }
            if (mysql_affected_rows($link) === 0) {
                $q = "INSERT INTO $database.$table (post_date,cluster_name,rps) VALUES (CURRENT_DATE(),".quote($cluster).",$max_rps)";
            }
        }
    } else {
        if (mysql_query("UPDATE $database.$table SET hosts=$num,".(!is_null($max_rps)?"rps=$max_rps,":"")."cpu=$max_cpu,memory=$max_mem,disk=$max_disk WHERE post_date=CURRENT_DATE() AND cluster_name=".quote($cluster), $link) === FALSE) {
            die ("ERROR: Unable to update data in db");
        }
        if (mysql_affected_rows($link) === 0) {
            $q = "INSERT INTO $database.$table (post_date,cluster_name,hosts,".(!is_null($max_rps)?"rps,":"")."cpu,memory,disk) VALUES (CURRENT_DATE(),".quote($cluster).",$num,".(!is_null($max_rps)?"$max_rps,":"")."$max_cpu,$max_mem,$max_disk)";
        }
    }

    if ($q && mysql_query($q, $link) === FALSE) {
        //die ("ERROR: Unable to insert data into db: $q");
    }

    mysql_close($link);
}


function quote ($data) {
    return "'".mysql_escape_string($data)."'";
}


function get_max_value ($cluster, $metric, $params, $start, $end, &$num)
{
    global $DEBUG;

    $max_total = 0;
    $find_min = false;
    $end_int = time();
    $start_int = $end_int - $start;
    $any_sum_found = false;

    foreach ($params['hosts'] as $host) {
        $host_total = 0;
        $any_lines_found = false;
        if (isset($values)) unset($values);
        $values = &get_rrd_values ($cluster, $host, $metric, $params, $start_int, $end_int, $num);
        $host_sum_found = false;
        foreach ($values as $time => $time_values) {
            $time_total = 0;
            $sum_found = false;
            foreach ($params['metrics'][$metric] as $metric_rrd) {
                $sum = 0;
                $metric_max = 0;
                $is_divisor = false;
                if ($metric_rrd{0} == '/') {
                    $metric_rrd = substr($metric_rrd,1);
                    $is_divisor = true;
                } elseif ($metric_rrd{0} == '-') {
                    $metric_rrd = substr($metric_rrd,1);
                    $find_min = true;
                    $metric_max = -1;
                    if ($host_total == 0) $host_total = -1;
                    if ($max_total == 0) $max_total = -1;
                }
                if ($find_min && !isset($time_values[$metric_rrd])) {
                    break; // skip this time which is missing data for this metric (should only happen at time boundaries)
                }
                
                $sum = $time_values[$metric_rrd];
                $sum_found = true;

                if ($DEBUG > 1) echo "$cluster/".date('r',$time)."/$metric_rrd/sum=$sum\n";
                if ($is_divisor) {
//                    if ($DEBUG > 0) echo "numerator=$sum\n";
                    if ($sum == 0) {
                        $sum_found = false; 
                        break;
                    }
                    $time_total /= $sum;
                } else {
                    $time_total += $sum;
                }
            }
            if ($sum_found) {
                $host_sum_found = true;
                //if ($metric == 'rps' && $time_total > 1000000) $time_total = 0; // prevent outlier (bogus) rps numbers getting into history
                if ($DEBUG > 0) echo "$cluster/$host/$metric/".date('r',$time)."/time_total=$time_total\n";
                if ($host_total == -1) $host_total = $time_total;
                $host_total = $find_min ? min($host_total, $time_total) : max($host_total, $time_total);
            }
        }
        if ($host_sum_found) {
            $any_sum_found = true;
            if ($DEBUG > 0) echo "$cluster/$host/$metric/host_total=$host_total\n";
            if ($max_total == -1) $max_total = $host_total;
            $max_total = $find_min ? min($max_total, $host_total) : max($max_total, $host_total);
        }
    }
    if ($num == 0 && !isset($params['hosts_random'])) $num = count($params['hosts']);
    if ($DEBUG > 0) echo "$cluster/$metric/total=$max_total\n";
    return ($any_sum_found ? $max_total : ($params['metrics'][$metric][0]{0}=='-' ? 1 : 0));
}


function &get_rrd_values ($cluster, $host, $metric, $params, $start_int, $end_int, &$num)
{
    global $OPTIONS, $DEBUG;

    $max_total = 0;
    $values = array();
    $host_total = 0;
    $any_lines_found = false;

    foreach ($params['metrics'][$metric] as $metric_rrd) {
        if ($metric_rrd{0} == '/') {
            $metric_rrd = substr($metric_rrd,1);
        } elseif ($metric_rrd{0} == '-') {
            $metric_rrd = substr($metric_rrd,1);
        }
        if (isset($params['embedded_metrics'])) {
            $source = $params['source'].($host ? "$host.rrd" : '');
        } else {
            $source = $params['rrd_root'].$params['source']."/$host/$metric_rrd".$params['rrd_suffix'];
        }
        $lines_found = false;
        $cmd = $GLOBALS['cfg']['rrdtool']." fetch $source AVERAGE -s $start_int -e $end_int 2>/dev/null";
        //echo "$cmd\n";
        $last_time = 0;
        $interval_total = 0;
        $interval_count = 0;
        $handle = popen( $cmd, 'r' );
        if ($DEBUG > 1) echo "Reading metrics from: $cluster/$host/$metric_rrd\n";
        $first_line = fgets($handle);
        if ($first_line == FALSE || !preg_match('/\s*(\w+)/', $first_line, $number_types)) {
            continue;
        }
        for ($i=1; $i<count($number_types); $i++) {
            $types[$number_types[$i]] = $i;
        }
        while (($line = fgets($handle)) !== FALSE) {
            if ($DEBUG > 3) echo "$cluster/$host/$metric_rrd: $line";
            $sum_match = array();
            $delim = isset ($params['embedded_metrics']) && $params['embedded_metrics'] ? $metric_rrd : 'sum';
            if (preg_match_all('/([\d\.e\+\-]+)/', $line, $numbers)) {
                $lines_found = true;
                $any_lines_found = true;
                $time_int = intval($numbers[0][0]);
                if (isset($numbers[0][$types[$delim]])) {  
                    $sum = floatval($numbers[0][$types[$delim]]);
                    if (!is_numeric($sum)) continue;  // should already be numeric, but to be safe
                    if (isset($types['num'])) {
                        $num = intval($numbers[0][$types['num']]);
                        $sum /= $num;
                    }
                    if ($last_time != 0 && $interval_count>0 && ($time_int - $last_time) >= $OPTIONS['rrd_resolution_mins']*60) {
                        $values[$time_int][$metric_rrd]=$interval_total/$interval_count;
                        $last_time = $time_int;
                        $interval_total = 0;
                        $interval_count = 0;
                        if ($DEBUG > 1) echo "$cluster/$host/$metric_rrd: $sum\n";
                    }
                    $interval_total += $sum;
                    $interval_count++;
                    if ($last_time == 0) $last_time = $time_int;
                }
            }
        }
        pclose($handle);
    }

    if ($num == 0 && !isset($params['hosts_random'])) $num = count($params['hosts']);

    return $values;
}


function get_db_disk_usage($hosts)
{
    $db_max_used = 0;
    foreach ($hosts as $hostname) {
        $db_disk_total = 0;
        $db_disk_used = 0;
        $handle = popen( "ssh -o \"ConnectTimeout 30\" -o \"StrictHostKeyChecking no\" -o \"ConnectionAttempts 1\" ross@$hostname \"df -k /data/mysql0 2>/dev/null || df -k /var/lib/mysql 2>/dev/null\"", 'r' );
        while (($line = fgets($handle)) !== FALSE) {
            if ($DEBUG > 1) echo "$hostname df output: $line";
            $match = array();
            if (preg_match('/^.*\s+(\d+)\s+(\d+)\s+\d+\s+\d+%\s\/.*$/', $line, $match)) {
                $db_disk_total = $match[1];
                $db_disk_used = $match[2];
            }
        }
        pclose($handle);

        $db_mysql_free = 0;
        $handle = popen( "mysql -A -h $hostname -u mon -p\\!mon\\! -e \"create database if not exists test; use test; create table if not exists rossspacetest (bar int) type=innodb; show table status like 'rossspacetest'\G drop table if exists rossspacetest;\" | grep 'InnoDB free' | tail -1", 'r' );
        while (($line = fgets($handle)) !== FALSE) {
            if ($DEBUG > 1) echo "$hostname mysql space: $line";
            $match = array();
            if (preg_match('/^.*\s+(\d+)\skB.*$/', $line, $match)) {
                $db_mysql_free = $match[1];
            }
        }
        pclose($handle);
        $db_max_used = max($db_max_used, ($db_disk_total ? ($db_disk_used - $db_mysql_free)*100 / $db_disk_total : 0));
    }
    
    return $db_max_used;
}


function get_arguments()
{
    $opts = getopt('s:H:u:p:d:h:n:r:');

    $USAGE = "Invalid command line\n" .
             "grab_rrd_data.php [-s seconds_ago_to_start_from] \n".
             "                  [-H host_name]\n".
             "                  [-u user_name]\n".
             "                  [-p password]\n".
             "                  [-d database_name]\n".
             "                  [-h help_text]\n".
             "                  [-n calc_do_rps_alt, 0=no, 1=yes, 2=only]\n".
             "                  [-r rrd_resolution_mins]\n";

    if (isset($opts['h'])) die ($USAGE);

    $OPTIONS = array();
    $OPTIONS['start'] = isset($opts['s']) ? $opts['s'] : 604800;
    $OPTIONS['end'] = '';
    $OPTIONS['host'] = isset($opts['H']) ? $opts['H'] : $GLOBALS['db']['host'];
    $OPTIONS['user'] = isset($opts['u']) ? $opts['u'] : $GLOBALS['db']['user'];
    $OPTIONS['password'] = isset($opts['p']) ? $opts['p'] : $GLOBALS['db']['password'];
    $OPTIONS['database'] = isset($opts['d']) ? $opts['d'] : $GLOBALS['db']['database'];
    $OPTIONS['do_rps_alt'] = isset($opts['n']) ? $opts['n'] : '1';
    $OPTIONS['rrd_resolution_mins'] = isset($opts['r']) ? $opts['r'] : $GLOBALS['cfg']['rrd_resolution_mins'];
    return $OPTIONS;
}

function merge_params() 
{
    $cluster = func_get_args();
    $params = array_shift($cluster);
    foreach ($cluster as $array) {
        foreach ($array as $key => $value) {
            if (is_array($value) && !isset($value[0])) {
                $params[$key] = merge_params($params[$key], $array[$key]);
            }
            else {
                $params[$key] = $value;
            }
        }
    }
    return $params;
}

?>

