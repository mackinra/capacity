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

$ref = $_REQUEST['referer'];
if (!$ref) $ref = $_SERVER['HTTP_REFERER'];
$footer_height = 40;

if (!isset($_POST['collection'])) {
    $collection = getFormattedArray(var_export($GLOBALS['collection'], true));

    print "<html><head><title>Site Capacity Tool Config</title>\n";
    print "<style type=\"text/css\">\n";
    print "body {background-color: #ffffff !important; color: #000000; font: 15px arial,helvetica,sans-serif;}\n";
    print ".heading {font: 20px arial,helvetica,sans-serif; font-weight: bold;}\n";
    print ".field {font: 14px arial,helvetica,sans-serif; font-weight: normal;}\n";
    print "input {font: 12px arial,helvetica,sans-serif; font-weight: normal;}\n";
    print "textarea {font-size: 14px; font-family: courier, 'courier new', monospace;}\n";
    print "</style>\n";
    print "</head><body>";
    print '<form name="config" action="edit_config.php" method="POST">';
    print "<div style=\"position: fixed; width: auto; height: auto; top: 10px; right: 10px; bottom: {$footer_height}px; left: 10px; overflow: auto;\">";
    print "<input type=\"hidden\" name=\"referer\" value=\"$ref\">";
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
    print "<a href=\"$ref\">&lt;&lt; Back</a><p>\n";
    print "<p class=\"heading\">General Settings</p>\n";
    print "<table border=\"0\" cellpadding=\"4\" cellspacing=\"0\">\n";
    foreach ($GLOBALS['cfg'] as $field => $value) {
        print "<tr><td class=\"field\">".ucwords(strtr($field,'_',' ')).":</td><td><input type=\"text\" name=\"cfg_$field\" value=\"".addslashes($value)."\" size=\"".round(strlen($value)*1.5)."\"></td></tr>\n";
    }
    print "</table>\n";
    print "<hr>\n";

    print "<p class=\"heading\">Database Settings</p>\n";
    print "<table border=\"0\" cellpadding=\"4\" cellspacing=\"0\">\n";
    foreach ($GLOBALS['db'] as $field => $value) {
        print "<tr><td class=\"field\">".ucwords(strtr($field,'_',' ')).":</td><td><input type=\"text\" name=\"db_$field\" value=\"".addslashes($value)."\" size=\"".round(strlen($value)*1.5)."\"></td></tr>\n";
    }
    print "</table>\n";
    print "<hr>\n";

    print "<p class=\"heading\">Cluster Defaults</p>\n";
    print "<table border=\"0\" cellpadding=\"4\" cellspacing=\"0\">\n";
    foreach ($GLOBALS['defaults'] as $field => $value) {
        if (is_array($value)) {
            $fmt_value = getFormattedArray(var_export($value,true));
            print '<p class="field">'.ucwords(strtr($field,'_',' ')).':</p>';
            print "<textarea name=\"defaults_$field\" rows=\"".count(explode("\n",$fmt_value))."\" cols=\"80\">";
            print $fmt_value;
            print '</textarea>';
        } else {    
            print "<tr><td class=\"field\">".ucwords(strtr($field,'_',' ')).":</td><td><input type=\"text\" name=\"defaults_$field\" value=\"".addslashes($value)."\" size=\"".round(strlen($value)*1.5)."\"></td></tr>\n";
        }
    }
    print "</table>\n";
    print "<hr>\n";

    print "<p class=\"heading\">Clusters</p>\n";
    print '<textarea name="collection" rows="'.count(explode("\n",$collection)).'" cols="100">';
    print $collection;
    print '</textarea>';
    print '</div>';
    print "<div style=\"position: fixed; float: left; width: 100%; height: {$footer_height}px; top: auto; right: 0; bottom: 0; left: 0; border-top-style: solid; border-top-width: 1px; border-top-color: #d0d0d0;\">";
    print '&nbsp;<input type="submit" name="submit2" value="Apply Changes">';
    print '</div>';
    print '</form>';
    print '</body></html>';

} else {

    $cluster_config = '../conf/cluster.inc';
    $backups_to_keep = $GLOBALS['cfg']['config_backups_to_keep'];

    $collection = $_POST['collection'];
    $collection_eval = eval("return $collection;");

    if ($collection_eval === FALSE) {
        exit;
    }

    $collection = str_replace("\r","",$collection);

    $contents = "<?php\n\n".'$GLOBALS[\'collection\'] = '.$collection.";\n\n?>\n";

    for ($i = $backups_to_keep; $i>0; $i--) {
        if (file_exists($cluster_config.".$i")) {
            rename ($cluster_config.".$i", $cluster_config.'.'.($i+1));
        }
    }
    if (file_exists($cluster_config.'.'.($backups_to_keep+1)))
        unlink ($cluster_config.'.'.($backups_to_keep+1));

    rename ($cluster_config, $cluster_config.'.1');

    file_put_contents($cluster_config, $contents);

    header('Location: edit_config.php?status=saved&referer='.urlencode($ref));

}

function getFormattedArray($input)
{
    $arr = preg_replace('/(\n\s*array \(\n)/', "array (\n", $input);
    $arr = preg_replace('/(\n\s*)(\d+ => )/', '$1', $arr);
    $arr = preg_replace('/(\n\s\s\),\n)/', "$1\n", $arr);
    $arr = preg_replace('/(\n\s{8}\')/', "'", $arr);
    $arr = preg_replace('/(,\n\s{6}\),)/', "),", $arr);
//    $arr = preg_replace('/\n(\s+)/', "\n$1$1", $arr);
    return $arr;
}

?>

