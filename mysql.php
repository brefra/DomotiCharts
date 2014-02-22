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


// Select correct 
// two days range loads minute data
if ($range < 2 * 24 * 3600 * 1000) {
        $table = 'minute';
        
// one month range loads hourly data
} elseif ($range < 31 * 24 * 3600 * 1000) {
        $table = 'hour';
        
// one year range loads daily data
} elseif ($range < 15 * 31 * 24 * 3600 * 1000) {
        $table = 'day';


// greater range loads monthly data
} else {
        $table = 'month';
} 



// connect to MySQL
$con = mysql_connect($host,$user,$password, true, 65536);

if (!$con) {
	die('Could not connect: ' . mysql_error());
}
mysql_select_db($database, $con);


// set UTC time & variables
mysql_query("SET time_zone = '+00:00'");


// set some utility variables
$range = $end - $start;
$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

$ParseData = '';

if ($_GET['devicelist']){
	$query = 'SELECT id, name FROM devices WHERE enabled IS TRUE AND hide IS FALSE ORDER BY name';
	$ParseData = 'DeviceList';
}

if ($_GET["device_id"] AND $_GET["valuenum"]){
	$device_id = $_GET["device_id"];
	$valuenum = $_GET["valuenum"];
	$query = "SELECT unix_timestamp(lastchanged) * 1000 as lastchanged, value FROM device_values_log WHERE device_id='$device_id' and valuenum='$valuenum' and lastchanged between '$startTime' and '$endTime' order by lastchanged";
	$ParseData = "LogValues";
}
$skipfirst = False;
if ($_GET["diff"]){
	$ParseData = "LogValues_diff";
  $query = "SELECT unix_timestamp(CONCAT(date(lastchanged), ' ', maketime(HOUR(lastchanged),0,0))) * 1000 as lastchanged, SUM(calc_value) as 'value' from ( ";  
  $query .= "SELECT lastchanged, value, round(value-@tempvalue, 3) as calc_value, @tempvalue:=value ";
  $query .= "FROM device_values_log, (select @tempvalue:=0) as dummytable ";
  $query .= "where device_id='$device_id' and valuenum='$valuenum' and lastchanged between '$startTime' and '$endTime'";
  $query .= "order by lastchanged ";
  $query .= ") AS TempTable ";
  switch ($table) {
    case 'minute':
      $query .= "GROUP BY EXTRACT(DAY FROM lastchanged), EXTRACT(HOUR FROM lastchanged), EXTRACT(MINUTE FROM lastchanged)";
      break;
    case 'hour':
      $query .= "GROUP BY EXTRACT(DAY FROM lastchanged), EXTRACT(HOUR FROM lastchanged)";
      break;
    case 'day':
      $query .= "GROUP BY EXTRACT(DAY FROM lastchanged)";
      break;
    case 'month':
      $query .= "GROUP BY EXTRACT(MONTH FROM lastchanged)";
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

//echo "/* console.log(' start = $start, end = $end, startTime = $startTime, endTime = $endTime '); */\n";
//echo "/* console.log(' query = $query'); */\n";
echo $callback ."([\n" . join(",\n", $rows) ."\n]);";

?>

