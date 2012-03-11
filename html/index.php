<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007-2011
 */

error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', 'On');

require_once('capacity.inc');

$date = $_GET['date'];
if (!$date) {
    $max_date = db_query("SELECT MAX(post_date) AS date FROM history");
    $date = $max_date['date'];
}
$history = get_history($date);
$title = $GLOBALS['cfg']['env_name'].' Site Capacity';

//print_r($history);
foreach ($history as $index => $cluster) {
    if (!$cluster['description']) $cluster['description'] = $cluster['cluster_name'];
    
    // extend metric calculations
    extend_metrics($cluster);

    if (!empty($cluster['max_rps'])) {
        $rps_max_style = "style=\"font-weight: bold\" title=\"Benchmark max (".round($cluster['max_rps']).") set on {$cluster['max_rps_updated']} from {$cluster['bench_ip']}\"";
    } elseif ($cluster['cpu'] > 0) {
        $rps_max_style = "style=\"font-style: italic; color: #B0B0B0\" title=\"Projected based on CPU usage\"";
    }
    if ($cluster['_overall_used'] >= 90) $overall_style="style=\"color: white; background-color: red; font-weight: bold;\"";
    elseif ($cluster['_overall_used'] >= 75) $overall_style="style=\"color: black; background-color: #FFFC00; font-weight: bold;\"";
    else $overall_style="style=\"color: #1E90FF; font-weight: bold;\"";
    
    $left_col_style = '';
    if (!empty($GLOBALS['cfg']['highlighted_cluster_pattern']) && preg_match($GLOBALS['cfg']['highlighted_cluster_pattern'],$cluster['cluster_name'])) $left_col_style = "style=\"background-color: {$GLOBALS['cfg']['highlighted_cluster_color']};\"";

    $cluster['_rps_max_style'] = $rps_max_style;
    $cluster['_overall_style'] = $overall_style;
    $cluster['_left_col_style'] = $left_col_style;
    $history[$index] = $cluster;
}

if ($_GET['s']) sort_history($history, $sort);

$bg_from = 'ffffff';
$bg_to   = 'ffffff';

$html_head = <<<EOT
<html><head><title>$title</title>
<style type="text/css">
body {
    color: #000000; 
    font: 14px arial,helvetica,sans-serif;
    background: #{$bg_from}; /* for non-css3 browsers */
    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#{$bg_from}', endColorstr='#{$bg_to}'); /* for IE */
    background: -webkit-gradient(linear, left top, left bottom, from(#{$bg_from}), to(#{$bg_to})); /* for webkit browsers */
    background: -moz-linear-gradient(top, #{$bg_from}, #{$bg_to}); /* for firefox 3.6+ */ 
}
.normal { background-color: #FFFFFF; }
.highlight { background-color: #DDEEFF; }
table {font-size: inherit; font: inherit; text-align: center; border-color: #ffffff; background-color: white}
th {text-align: center; vertical-align: bottom; background-color: #aaaaaa; color: #000000; }
th.maxcol {background-color: #3344ff; color: #ffffff;}
th.maxcol a {color: #ffffff; background: inherit;}
td {text-align: center;}
.tabletitle {font: 16px arial,helvetica,sans-serif; font-weight: bold;}
.leftcol {text-align: left;}
td.leftcol {background-color: #BACAFF;}
td.high {color: red; font-weight: bold;}
td.medium {color: #BBB900; font-weight: bold;}
td.low {color: black;}
a {color: #000000; background: inherit;}
a:link, a:visited {text-decoration: none; color: inherit; background: inherit;}
a:hover {text-decoration: underline; color: inherit; background: inherit;}
#maintable {position: fixed; width: auto; height: auto; top: 10px; right: 10px; bottom: 10px; left: 10px; overflow: auto;} 
#graphpanel {position: fixed; float: left; width: 100%; height: 0; top: auto; right: 0; bottom: 0; left: 0; border-top-style: solid; border-top-width: 1px; border-top-color: #d0d0d0; display: none; background-color: white;}
#graph {float: left; width: 100%; text-align: center;}
#closebutton {position: absolute; width: 16px; height: 16px; top: 10px; right: 10px;}
</style>
<script type="text/javascript">
function getObj(name) {
    if (document.getElementById) {
        return document.getElementById(name);
    } else if (document.all) {
        return document.all[name];
    }
}
function launchWindow(url, width, height) {
    window.open(url,'_blank','width='+width+',height='+height+',location=no,menubar=no,resizable=yes,scrollbars=yes,status=yes,titlebar=no,toolbar=no',false);
}
function launchGraph(cluster, field) {
    if ({$GLOBALS['cfg']['popup_graphs']} == 1) {
        url = 'graph2.php?c='+cluster+'&m='+field+'&p=365';
        launchWindow(url,1115,{$GLOBALS['cfg']['graph_height']});
    } else {
        html = '<img src="graph2.php?c='+cluster+'&m='+field+'&p=365">';
        m = new getObj('maintable');
        m.style.bottom = {$GLOBALS['cfg']['graph_height']};
        gp = new getObj('graphpanel');
        gp.style.height = {$GLOBALS['cfg']['graph_height']};
        gp.style.display = 'block';
        g = new getObj('graph');
        g.innerHTML = html;
    }
}
function hideGraph() {
    m = new getObj('maintable');
    if (m.style.bottom != 10) {
        m.style.bottom = 10;
        gp = new getObj('graphpanel');
        gp.style.height = 0;
        gp.style.display = 'none';
    }
}
function launchRRDFrontend(cluster,host) {
    url = '{$GLOBALS['cfg']['rrd_frontend_url']}'+cluster;
    if (host != '') url += '&{$GLOBALS['cfg']['rrd_frontend_url_host_param']}='+host;
    launchWindow(url,1220,900);
}
document.onkeyup = function(e) 
{
    e = e || window.event;
    var keyCode = e.which || e.keyCode;
    if (keyCode == 27) {
        hideGraph();
    }
}
</script>
</head>
EOT;
print $html_head;

print "<body>";
if (!$GLOBALS['cfg']['popup_graphs']) {
    print '<div id="maintable">';
}
print '<table border="1" width="100%" cellpadding="2" cellspacing="0">';
print '<tr>';
print '  <th colspan="10" class="tabletitle">'.$title.' as of '.$date.'<span style="font-size:12px; display:inline; float:right"><a href="edit_config.php">settings</a>&nbsp;|&nbsp;<a href=".">help</a></span></th>';
print '</tr><tr>';
print '  <th class="leftcol">'._sortlink('Cluster','description').'</th>';
print '  <th>'._sortlink('Hosts','hosts').'</th>';
print '  <th>'._sortlink('Host CPU Used (%)','cpu').'</th>';
print '  <th>'._sortlink('Host RPS Peak','rps').'</th>';
//print '  <th>'._sortlink('Host Mem Used (%)','memory').'</th>';
//print '  <th>'._sortlink('Host Disk Used (%)','disk').'</th>';
//print '  <th>'._sortlink('Cluster RPS','_rps_cluster').'</th>';
print '  <th>'._sortlink('Host RPS Max','_rps_max').'</th>';
print '  <th>'._sortlink('Cluster RPS Max','_rps_max_cluster').'</th>';
print '  <th class="maxcol">'._sortlink('RPS Capacity Used (%)','_rps_used').'</th>';
print '  <th class="maxcol">'._sortlink('Memory Capacity Used (%)','memory').'</th>';
print '  <th class="maxcol">'._sortlink('Disk Capacity Used (%)','disk').'</th>';
print '  <th class="maxcol">'._sortlink('Overall Capacity Used (%)','_overall_used').'</th>';
print '</tr>';

foreach ($history as $cluster) {
    $name = $cluster['cluster_name'];
    if (!isset($GLOBALS['collection'][$name]) || $GLOBALS['collection'][$name]['disabled'] === true) continue;
    print "<tr onMouseOver=\"this.className='highlight'\" onMouseOut=\"this.className='normal'\" >";
    print "  <td class=\"leftcol\" nowrap {$cluster['_left_col_style']}><table border=0 width=\"100%\" cellpadding=0 cellspacing=0><tr><td class=\"leftcol\" nowrap {$cluster['_left_col_style']}>"._rrduilink($cluster['description'],$name)."</td><td class=\"leftcol\" {$cluster['_left_col_style']}>"._wiki_link($name)."</td></tr></table></td>";
    print "  <td>"._graphlink(_fmt($cluster['hosts']),$name,'hosts')."</td>";
    print "  <td>"._graphlink(_fmt($cluster['cpu'],'%'),$name,'cpu')."</td>";
    print "  <td>"._graphlink(_fmt($cluster['rps']),$name,'rps')."</td>";
    //print "  <td>"._graphlink(_fmt($cluster['memory'],'%'),$name,'memory')."</td>";
    //print "  <td>"._graphlink(_fmt($cluster['disk'],'%'),$name,'disk')."</td>";
    //print "  <td>"._graphlink(_fmt($cluster['_rps_cluster']),$name,'_rps_cluster')."</td>";
    print "  <td {$cluster['_rps_max_style']}>"._graphlink(_fmt($cluster['_rps_max']),$name,'_rps_max')._edit_max_link($cluster)."</td>";
    print "  <td {$cluster['_rps_max_style']}>"._graphlink(_fmt($cluster['_rps_max_cluster']),$name,'_rps_max_cluster')."</td>";
    print "  <td "._capclass($cluster['_rps_used']).">"._graphlink(_fmt($cluster['_rps_used'],'%'),$name,'_rps_used')._exception_link('delete',$cluster,$cluster['_rps_used'],'RPS')."</td>";
    print "  <td "._capclass($cluster['memory']).">"._graphlink(_fmt($cluster['memory'],'%'),$name,'memory')._exception_link('delete',$cluster,$cluster['memory'],'Memory')."</td>";
    print "  <td "._capclass($cluster['disk']).">"._graphlink(_fmt($cluster['disk'],'%'),$name,'disk')._exception_link('delete',$cluster,$cluster['disk'],'Disk')."</td>";
    print "  <td {$cluster['_overall_style']}>"._graphlink(_fmt($cluster['_overall_used'],'%'),$name,'_overall_used',$cluster['_overall_used_cause']." Capacity Used ({$cluster['_overall_used']}%)")._exception_link('add',$cluster,$cluster['_overall_used'],$cluster['_overall_used_cause'])."</td>";
    print "</tr>\n";
}

print '</table>';

//print '<p><b>Instructions:</b><br><ul><li>Click column headings to sort<li>Click on numeric values to show graph over time</ul>';
if (!$GLOBALS['cfg']['popup_graphs']) {
    print '</div>';
    print '<div id="graphpanel"><div id="graph"></div><div id="closebutton"><a href="javascript:hideGraph()" title="Close"><img border="0" src="images/close.png"></a></div></div>';
}
print '</body></html>';
exit;


function _sortlink ($title, $field)
{
    $order = ($_GET['s'] === $field && $_GET['o'] === 'd') ? 'a' : 'd';
    if ($field === 'description') $order = (!isset($_GET['s']) || ($_GET['s'] === 'description' && $_GET['o'] === 'a')) ? 'd' : 'a';
    return ($_GET['s'] === $field || (!isset($_GET['s']) && $field === 'description') ? "<center><img border=\"0\" src=\"images/arrow".($order=='a'?'down':'up').".png\" width=\"16\" height=\"16\"><br>" : '')."<a href=\"?s=$field&o=$order\">$title</a>";
}


function _graphlink ($title, $cluster, $field, $tooltip='')
{
    return "<a ".(!empty($tooltip) ? "title=\"$tooltip\" " : '')."href=\"javascript:launchGraph('$cluster','$field')\">$title</a>";
}

function _rrduilink ($title, $cluster)
{
    $hosts = $GLOBALS['collection'][$cluster]['hosts'];
    return "<a href=\"javascript:launchRRDFrontend('".$GLOBALS['collection'][$cluster]['source']."','".(count($hosts)==1 && $hosts[0]!='__SummaryInfo__' ? $hosts[0] : '')."')\">$title</a>";
}

function _wiki_link ($cluster)
{
    return "<a style=\"float:right;}\" href=\"javascript:launchWindow('".$GLOBALS['cfg']['wiki_edit_url']."$cluster',800,800)\" title=\"Add Notes\"><img border=\"0\" src=\"images/notepad.png\" width=\"14\" height=\"14\"></a>";
}

function _edit_max_link ($cluster)
{
    return "<span style=\"float: right; display: inline;\"><a href=\"edit_benchmark.php?cluster={$cluster['cluster_name']}\" title=\"".(!empty($cluster['max_rps'])?'Edit':'Set')." Benchmarked Max RPS\"><img border=\"0\" src=\"images/pencil.png\" width=\"16\" height=\"16\"></a></span>";
}

function _exception_link ($action,$cluster,$value,$cause)
{
    $except_col = trim(strtolower($cause));
    if ($action == 'delete' && empty($cluster["ex_$except_col"])) return '';
    return "<span style=\"float: right; clear: both;\"><a href=\"edit_exception.php?action=$action&cluster={$cluster['cluster_name']}&value=$value&cause=$cause\" title=\"".($action=='delete'?'Remove':'Add')." exception for $cause".($action == 'delete'?' ('.$cluster["ex_$except_col"]."%) set on {$cluster['ex_date']} from {$cluster['ex_ip']}":'')."\"><img border=\"0\" src=\"images/thumb".($action=='delete'?'up':'down').".png\" width=\"16\" height=\"16\"></a></span>";
}

function _fmt ($value, $suffix='')
{
    if ($value != 0) {
        if ($value > 1000000) 
            $v = round($value/1000000,0).'<B>M</B>';
        elseif ($value > 99999) 
            $v = round($value/1000,0).'<B>K</B>';
        elseif ($value < 1) 
            $v = round($value,1);
        else 
            $v = number_format(round($value,0));
        $v .= $suffix;
    } else 
        $v = '&nbsp;';
    return $v;
}

function _capclass ($value)
{
    if ($luster['_overall_used'] >= 90) $overall_style="style=\"color: white; background-color: red; font-weight: bold;\"";
    elseif ($cluster['_overall_used'] >= 75) $overall_style="style=\"color: black; background-color: #FFFC00; font-weight: bold;\"";
    else $overall_style="style=\"color: #1E90FF; font-weight: bold;\"";
    if ($value >= 90) 
        return 'class="high"';
    elseif ($value >= 75) 
        return 'class="medium"';
    else
        return 'class="low"';
}

function get_history ($date)
{                                                                                                                                                         
    $query = " SELECT h.*,c.description,c.sort_order,b.max_rps,b.last_updated max_rps_updated,INET_NTOA(b.ip_addr) bench_ip,e.rps ex_rps,e.memory ex_memory,e.disk ex_disk,INET_NTOA(e.ip_addr) ex_ip,e.last_updated ex_date ".
             " FROM history h ".
             "   LEFT JOIN clusters c ON (c.cluster_name=h.cluster_name) ".
             "   LEFT JOIN benchmarks b ON (b.cluster_name=h.cluster_name) ".
             "   LEFT JOIN exceptions e ON (e.cluster_name=h.cluster_name) ".
             " WHERE h.post_date=".quote($date).
             " ORDER BY IF(c.sort_order,c.sort_order,h.cluster_name)";
    return db_query ($query, false);
}


function sort_history (&$history, $sort)
{
    if (isset($history[0][$_GET['s']])) 
        usort($history, 'cmp_history');
}


function cmp_history ($a, $b)
{
    $sort = $_GET['s'];
    $order = $_GET['o'];
    $toggle = ($order == 'a' ? 1 : -1);
    if ($a[$sort] == $b[$sort]) {
        return ($a['cpu'] < $b['cpu']) ? -1*$toggle : 0;
    }
    return ($a[$sort] < $b[$sort]) ? -1*$toggle : $toggle;
}

?>

