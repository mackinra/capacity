<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007
 */

require_once('capacity.inc');

$source = $_GET['c'];
$metric = $_GET['m'];
$period = isset($_GET['p']) ? max($_GET['p'],1) : 365;

$source_row = db_query("SELECT description FROM clusters WHERE cluster_name=\"$source\"");
$source_desc = (isset($source_row['description'])) ? $source_row['description'] : $source;

$start_time = time()-3600*24*$period;
$data = db_query("SELECT h.*,b.max_rps FROM history h LEFT JOIN benchmarks b ON (b.cluster_name=h.cluster_name) WHERE h.cluster_name='$source' AND h.post_date>=FROM_UNIXTIME($start_time) ORDER BY h.post_date", false);
$ymax = 0;

if (count($data) && !in_array($metric, $data[0])) {
    foreach ($data as $index => $day) {
        extend_metrics($day);
        $data[$index] = $day;
        $ymax = max($ymax, $day[$metric]);
    }
    $xmin = $data[0]['post_date'];
    $xmax = $data[count($data)-1]['post_date'];
}

$title = $source_desc.' ('.strtoupper(trim(strtr($metric,'_',' '))).')';
$yformat = $ymax <= 10 ? '%.1f' : "%.0f";

if (count($data) < 60) {
    $style = 'linespoints';
    $point_type = 'pointtype 7';
} else {
    $style = 'lines';
    $point_type = '';
}

$plot = <<<EOT
set fontpath '{$GLOBALS['cfg']['font_path']}'
unset key
set style data $style
set pointsize 1.2
set grid xtics ytics linetype 0, linetype 0
set rmargin 5
set border 1+2

set timefmt "%Y-%m-%d"
set xdata time
set xrange ['$xmin':'$xmax']
set xtics #'$xmin', 86400, '$xmax'
set mxtics 2
set format x '%Y-%m-%d'

set yrange [0:*]
set mytics
set format y '$yformat'

set terminal png size 1100,{$GLOBALS['cfg']['graph_height']} font arial 10

set title '$title' offset 0,-0.5 font 'arial,14'

plot '-' using 1:2 linetype rgb 'red' linewidth 2 $point_type #smooth csplines 
EOT;

foreach ($data as $point) {
    $plot .= ("\n".$point['post_date'].' '.$point[$metric]);
}
$plot .= "\ne";
//file_put_contents('/tmp/graph2.plot', $plot);

$descriptorspec = array(
   0 => array('pipe', 'r'),
   1 => array('pipe', 'w'),
   2 => array('pipe', 'w')
);

$process = proc_open($GLOBALS['cfg']['gnuplot'], $descriptorspec, $pipes);

if (is_resource($process)) {
    fwrite($pipes[0], $plot);
    fclose($pipes[0]);

    header ("Content-type: image/png");
    echo stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $retval = proc_close($process);
}

?>
