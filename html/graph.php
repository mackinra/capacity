<?php

/*
 * Capacity Planning Tool
 * by Ross MacKinnon <ross@mackinnon.com>
 * Copyright 2007
 */

require_once('capacity.inc');
require_once('/usr/share/jpgraph/jpgraph.php');
require_once('/usr/share/jpgraph/jpgraph_line.php');

function custom_number_format($v) {
    if ($v == 0) return $v;
    elseif ($v < 1) return number_format($v,2);
    else return number_format($v);
}

$source = $_GET['c'];
$metric = $_GET['m'];
$period = isset($_GET['p']) ? max($_GET['p'],1) : 365;

$source_row = db_query("SELECT description FROM clusters WHERE cluster_name=\"$source\"");
$source_desc = (isset($source_row['description'])) ? $source_row['description'] : $source;

$start_time = time()-3600*24*$period;
$data = db_query("SELECT h.*,b.max_rps FROM history h LEFT JOIN benchmarks b ON (b.cluster_name=h.cluster_name) WHERE h.cluster_name='$source' AND h.post_date>=FROM_UNIXTIME($start_time) ORDER BY h.post_date", false);

if (count($data) && !in_array($metric, $data[0])) {
    foreach ($data as $index => $day) {   
        extend_metrics($day);
        $data[$index] = $day;
    }
}

$xdata = array();
$ydata = array();
foreach ($data as $point) {
    $xdata[] = $point['post_date'];
    $ydata[] = $point[$metric];
}

// Create the graph
$graph = new Graph(1100, $GLOBALS['cfg']['graph_height'], 'auto');    
$graph->SetScale('textlin');
//$graph->SetShadow();
$graph->img->SetMargin(95,35,20,65);
$graph->title->Set($source_desc.' ('.strtoupper(trim(strtr($metric,'_',' '))).')');
$graph->title->SetFont(FF_ARIAL,FS_NORMAL,14);
//$graph->subtitle->Set(strtoupper(trim(strtr($metric,'_',' '))));
//$graph->subtitle->SetFont(FF_ARIAL,FS_NORMAL,13);
$graph->SetMarginColor('white');
$graph->SetFrame(false);
$graph->SetGridDepth(DEPTH_BACK);

$graph->xaxis->SetTickLabels($xdata);
//$graph->xaxis->title->Set('Date');
//$graph->xaxis->SetLabelAngle(45);
$graph->xaxis->SetLabelAlign('center','top');
$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,10);
$graph->xaxis->SetTextLabelInterval((count($xdata)<=6 ? 1 : ceil(count($xdata)/6)));
//$graph->yaxis->title->Set($metric);
//$graph->yaxis->title->SetFont(FF_ARIAL,FS_BOLD);
$graph->yaxis->SetFont(FF_ARIAL,FS_NORMAL,10);
$graph->yaxis->SetLabelFormatCallback("custom_number_format");

$graph->ygrid->SetFill(true,'#FFFFFF@0.5','#D6E6FF@0.5');
$graph->ygrid->Show(true, false);
$graph->xgrid->Show(true, false);

$graph->yaxis->scale->SetAutoMin(0);

//$graph->SetBackgroundGradient('#D6E6FF','white',GRAD_HOR,BGRAD_PLOT);

$line_color = 'red';

// Create the linear plot
$lineplot = new LinePlot($ydata);
$lineplot->SetColor($line_color);
//$lineplot->SetFillColor('blue');
$lineplot->SetWeight(3);
$lineplot->mark->SetType(MARK_FILLEDCIRCLE);
$lineplot->mark->SetColor($line_color);
$lineplot->mark->SetFillColor($line_color);
//Add the plot to the graph
$graph->Add( $lineplot );

// Display the graph
$graph->Stroke(); 

?>
