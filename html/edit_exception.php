<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007
 */

error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', 'On');

require_once('capacity.inc');

$action = $_GET['action'];
$cluster = $_GET['cluster'];
$value = $_GET['value'];
$cause = $_GET['cause'];
$except_col = trim(strtolower($cause));
$ref = $_SERVER['HTTP_REFERER'];
$remote_ip = $_SERVER['REMOTE_ADDR'];
$cols = array('rps','memory','disk');

$exception = db_query('SELECT * FROM exceptions WHERE cluster_name='.quote($cluster));

if ($action == 'add') {
    if (empty($exception)) {
        $query = "INSERT INTO exceptions (cluster_name, $except_col, last_updated, ip_addr) VALUES (".quote($cluster).",".quote($value).",NOW(),INET_ATON('$remote_ip'))";
    } else {
        $update = false;
        foreach ($cols as $col) {
            if ($col != $except_col && is_null($exception[$col])) {
                $update = true;
                break;
            }
        }
        if ($update) $query = "UPDATE exceptions SET $except_col=".quote($value).",last_updated=NOW(),ip_addr=INET_ATON('$remote_ip') WHERE cluster_name=".quote($cluster);
    }
    if ($query) {
        db_query($query);
    }
}
elseif ($action == 'delete' && !empty($exception)) {
    $query = "UPDATE exceptions SET $except_col = NULL, last_updated = NOW(), ip_addr=INET_ATON('$remote_ip') WHERE cluster_name=".quote($cluster);
    db_query($query);
}

header ("Location: $ref");

?>

