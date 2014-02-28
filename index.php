<!DOCTYPE html>
<html>

<head> 
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>

<title>DomotiCharts</title>

<?php
include 'config.php';

$con = mysql_connect($host,$user,$password);
if (!$con) {
	die('Could not connect to DomotiGa SQL database: ' . mysql_error());
}
mysql_select_db($database, $con);


if ($_GET["device_id"] AND $_GET["valuenum"]){
	$ParseData = "DeviceValues";
	$device_id = $_GET["device_id"];
	$valuenum = $_GET["valuenum"];
	echo '<script type="text/javascript">'."\n";
  if ($_GET["counter"]=='true'){
    echo 'var DataURL = "mysql.php?device_id='.$device_id.'&valuenum='.$valuenum.'&counter=true";'."\n";
  }else{
    echo 'var DataURL = "mysql.php?device_id='.$device_id.'&valuenum='.$valuenum.'";'."\n";
  }
 	echo "</script>"."\n";
}
?>

<script type="text/javascript" src="js/jquery-1.11.0.js" ></script>
<script type="text/javascript" src="js/highstock.js" ></script>

</head>
<body>
<form action=""> Select chart to view: <select name="Devices" onchange="ChangeChart(this.value)">
<?php

  mysql_query ('SET NAMES utf8');

	$query = 'SELECT devices.id, devices.name, device_values.value, device_values.valuerrddsname, device_values.log, device_values.valuenum, device_values.units, device_values.valuerrdtype FROM devices INNER JOIN device_values ON devices.id = device_values.deviceid WHERE devices.hide IS FALSE AND devices.enabled IS TRUE AND device_values.log IS TRUE ORDER BY devices.name ASC';
 	$result = mysql_query($query);
	while($row = mysql_fetch_array($result)) {
    $valuerrddsname = $row['valuerrddsname'];
    if (empty($valuerrddsname)){
      $valuerrddsname = '--RDD label missing--';
    }
    if ($row['valuerrdtype'] == 'COUNTER'){
      $Counter = '&counter=true';
    }else{
      $Counter = '';
    }
    if (($device_id == $row['id']) AND ($valuenum == $row['valuenum'])){
      echo '<option value="index.php?device_id='.$row['id'].'&valuenum='.$row['valuenum'].$Counter.'" selected>'.$row['name'].' - '.$valuerrddsname.'</option>';
      $Graph_Title = $row['name'];
      $Graph_Y_Label = $valuerrddsname;
      $Graph_Y_Units = $row['units'];
      if ($Counter ==''){
        $Graph_Type = 'spline';
      }else{
        $Graph_Type = 'column';
      }
    }else{
      echo '<option value="index.php?device_id='.$row['id'].'&valuenum='.$row['valuenum'].$Counter.'">'.$row['name'].' - '.$valuerrddsname.'</option>';
    }
	}
?>
</select>
</form>
<?php
	echo '<script type="text/javascript">'."\n";
	echo 'var Graph_Title = "'.$Graph_Title.'";'."\n";
  echo 'var Graph_Y_Label = "'.$Graph_Y_Label.'";'."\n";
  echo 'var Graph_Y_Units = "'.$Graph_Y_Units.'";'."\n";
  echo 'var Graph_Type = "'.$Graph_Type.'";'."\n";
	echo "</script>"."\n";
?>

<div id="chart" style="width: 100%; height: 70%; margin: 0 auto"></div>

<script type="text/javascript">

$(function() {

	$.getJSON(DataURL + '&callback=?', function(data) {

		// Create a timer
		var start = + new Date();

		// Create the chart
		$('#chart').highcharts('StockChart', {
      chart: {
        events: {
          load: function(chart) {
            this.setTitle(null, {
              text: 'Built chart at '+ (new Date() - start) +'ms'
            });
          }
        },
        zoomType: 'x'
      },

      rangeSelector: {
          buttons: [{
              type: 'day',
              count: 3,
              text: '3d'
          }, {
              type: 'week',
              count: 1,
              text: '1w'
          }, {
              type: 'month',
              count: 1,
              text: '1m'
          }, {
              type: 'month',
              count: 6,
              text: '6m'
          }, {
              type: 'year',
              count: 1,
              text: '1y'
          }, {
              type: 'all',
              text: 'All'
          }],
          selected: 3
      },

			navigator : {
				adaptToUpdatedData: false,
				series : {
					data : data
				}
			},
			
      scrollbar: {
				liveRedraw: false
			},
      
			xAxis : {
        ordinal: false,
        type: 'datetime',
				events : {
					afterSetExtremes : afterSetExtremes
				}
//				minRange: 3600 * 1000 // one hour
        
			},

			yAxis: {
				title: {
					text: Graph_Y_Units
				}
			},

		  title: {
				text: Graph_Title
			},

			subtitle: {
				text: 'Built chart at...' // dummy text to reserve space for dynamic subtitle
			},

			series: [{
        type: Graph_Type,
        name : Graph_Title,
        data : data,
        tooltip: {
          valueDecimals: 2,
          valueSuffix: Graph_Y_Units
        }
      }]

		});
	});
});

/**
 * Load new data depending on the selected min and max
 */
function afterSetExtremes(e) {

	var currentExtremes = this.getExtremes(),
		range = e.max - e.min,
		chart = $('#chart').highcharts();
		
    chart.showLoading('Loading data from server...');
    $.getJSON(DataURL + '&start='+ Math.round(e.min) + '&end='+ Math.round(e.max) +'&callback=?', function(data) {
		
		chart.series[0].setData(data);
		chart.hideLoading();
	});
	
}


</script>
<script type="text/javascript">
 function ChangeChart(sel)
 {
  document.location.href = sel
 };
</script>


</body>
</html>

