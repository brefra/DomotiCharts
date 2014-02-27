<?php
/**
 * @param callback {String} The name of the JSONP callback to pad the JSON within
 * @param start {Integer} The starting point in JS time
 * @param end {Integer} The ending point in JS time
 */

include 'config.php';

// get the parameters

$callback = $_GET['callback'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $callback)) {
  die('Invalid callback name');
}

$start = $_GET['start'];
if ($start && !preg_match('/^[0-9]+$/', $start)) {
  die("Invalid start parameter: $start");
}

$end = $_GET['end'];
if ($end && !preg_match('/^[0-9]+$/', $end)) {
  die("Invalid end parameter: $end");
}
if (!$end) $end = mktime() * 1000;

$device_id = $_GET["device_id"];
if ($device_id && !preg_match('/^[0-9]+$/', $end)) {
  die("Invalid device_id parameter: $device_id");
}

$valuenum = $_GET["valuenum"];
if ($valuenum && !preg_match('/^[0-9]+$/', $end)) {
  die("Invalid valuenum parameter: $valuenum");
}

// connect to MySQL
$con = mysql_connect($host,$user,$password, true, 65536);

if (!$con) {
	die('Could not connect: ' . mysql_error());
}
mysql_select_db($database, $con);


// set UTC time & variables
mysql_query("SET time_zone = '+00:00'");


// get date/time of first available record
$queryfirst = "Select unix_timestamp(lastchanged) * 1000 as firstrecord FROM device_values_log where device_id='$device_id' and valuenum='$valuenum' order by lastchanged asc limit 1";
$result = mysql_query($queryfirst) or die(mysql_error());
while ($firstrow = mysql_fetch_assoc($result)){
  extract($firstrow);
}

if ($start<$firstrecord){
  $start = $firstrecord;
}

// set some utility variables
$range = $end - $start;
$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

// Select correct range
// two days range loads minute data
if ($range < 3 * 24 * 3600 * 1000) {
        $TableDetail = 'minute';
        
// one month range loads hourly data
} elseif ($range < 30 * 24 * 3600 * 1000) {
        $TableDetail = 'minute';
        
// one year range loads daily data
} elseif ($range < 365 * 24 * 3600 * 1000) {
        $TableDetail = 'hour';

// greater range loads monthly data
} else {
        $TableDetail = 'day';
}


$ParseData = '';

if ($_GET['devicelist']){
	$query = 'SELECT id, name FROM devices WHERE enabled IS TRUE AND hide IS FALSE ORDER BY name';
	$ParseData = 'DeviceList';
}

if ($_GET["device_id"] AND $_GET["valuenum"]){
	$query = "SELECT unix_timestamp(lastchanged) * 1000 as lastchanged, value FROM device_values_log WHERE device_id='$device_id' and valuenum='$valuenum' and lastchanged between '$startTime' and '$endTime' order by lastchanged";
	$ParseData = "LogValues";
}
$skipfirst = False;
if ($_GET["diff"]){
	$ParseData = "LogValues_diff";
  switch ($TableDetail) {
    case 'minute':
      $query = "SELECT unix_timestamp(CONCAT(date(lastchanged), ' ', maketime(HOUR(lastchanged),MINUTE(lastchanged),0))) * 1000 as lastchanged, SUM(calc_value) as 'value' from ( ";
      break;
    case 'hour':
      $query = "SELECT unix_timestamp(CONCAT(date(lastchanged), ' ', maketime(HOUR(lastchanged),0,0))) * 1000 as lastchanged, SUM(calc_value) as 'value' from ( ";
      break;
    case 'day':
      $query = "SELECT unix_timestamp(date(lastchanged)) * 1000 as lastchanged, SUM(calc_value) as 'value' from ( ";
      break;
    case 'month':
      $query = "SELECT unix_timestamp(CONCAT(YEAR(lastchanged), '-', MONTH(lastchanged), '-', DAY(lastchanged))) * 1000 as lastchanged, SUM(calc_value) as 'value' from ( ";
      break;
  }
  $query .= "SELECT lastchanged, value, round(value-@tempvalue, 3) as calc_value, @tempvalue:=value ";
  $query .= "FROM device_values_log, (select @tempvalue:=0) as dummytable ";
  $query .= "where device_id='$device_id' and valuenum='$valuenum' and lastchanged between '$startTime' and '$endTime' ";
  $query .= "order by lastchanged ";
  $query .= ") AS TempTable ";
  switch ($TableDetail) {
    case 'minute':
      $query .= "GROUP BY EXTRACT(MONTH FROM lastchanged), EXTRACT(DAY FROM lastchanged), EXTRACT(HOUR FROM lastchanged), EXTRACT(MINUTE FROM lastchanged) ";
      break;
    case 'hour':
      $query .= "GROUP BY EXTRACT(MONTH FROM lastchanged), EXTRACT(DAY FROM lastchanged), EXTRACT(HOUR FROM lastchanged) ";
      break;
    case 'day':
      $query .= "GROUP BY EXTRACT(MONTH FROM lastchanged), EXTRACT(DAY FROM lastchanged) ";
      break;
    case 'month':
      $query .= "GROUP BY EXTRACT(MONTH FROM lastchanged) ";
      break;
  }
  $query .= "order by lastchanged";
  $skipfirst = True;
}

$result = mysql_query($query) or die(mysql_error());

$rows = array();
while ($row = mysql_fetch_assoc($result)){
  extract($row);
  if ($_GET["diff"] AND $skipfirst){
    $skipfirst = False;
  }else{
    $rows[] = "[$lastchanged,$value]";
  }
}

// print it
header('Content-Type: text/javascript');

//echo "/* console.log(' range = $range'); */\n";
//echo "/* console.log(' table = $TableDetail'); */\n";
//echo "/* console.log(' start = $start, end = $end, startTime = $startTime, endTime = $endTime '); */\n";
//echo "/* console.log(' query = $query'); */\n";
echo $callback ."([\n" . join(",\n", $rows) ."\n]);";

?>

