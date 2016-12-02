<!DOCTYPE HTML>
<?php
//including FusionCharts PHP Wrapper
include("fusioncharts/fusioncharts.php"); 
$hostdb   = "localhost"; // MySQl host
$userdb   = "root"; // MySQL username
$passdb   = "root"; // MySQL password
$namedb   = "breaker1"; // MySQL database name

//Establish connection with the database
$dbhandle = new mysqli($hostdb, $userdb, $passdb, $namedb);

//Validating DB Connection
if ($dbhandle->connect_error) {
    exit("There was an error with your connection: " . $dbhandle->connect_error);
}
?>

<html>
   <head>
      <title>Power Consumption Charts</title>
      <!-- FusionCharts Core Package File -->
      <script src="fusioncharts/js/fusioncharts.js"></script> 
      <script type="text/javascript" src="fusioncharts/js/elegant.js"></script>
      <link href='https://fonts.googleapis.com/css?family=Open+Sans:300' rel='stylesheet' type='text/css'>
      
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>Live electricity power meter</title>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
		<script type="text/javascript">
		
$(function () {

	// Update period in seconds
	var updatePeriod=5;

	// Time interval shown in the graph in minutes
	var timeWindow=10;

	//------------------------------------------------------------------
	var xmlhttp;
	if (window.XMLHttpRequest)	  {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp=new XMLHttpRequest();
	}else{
		// code for IE6, IE5
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
   }

	function formatUnits(watts) 
	{
		var units=['W', 'KW', 'MW', 'GW'];
		var unit = 0;
	
		while (Math.floor(watts) > 1000){
			unit++;
			watts /= 1000.0;
		}
		
		var str=""+watts+" "+units[unit];
		return str;
	}
  
	var chart;
	var firstSampleTime;

	function requestData() 
	{
		$.ajax({
			url: 'power.php?now',
			cache: false,

			success: function(point) {
				var series = chart.series[0];

				// Start shifting (removing old samples) the graph to show only 
				// samples for last 'timeWindow' minutes
				var shift = (point[0]-firstSampleTime)/1000.0 > timeWindow*60; 
	
				// Add new point to the chart
				if (series.data.length > 1){
					var str = "Usage now: "+formatUnits(point[1]);
					document.getElementById("myDiv").innerHTML="<h2>"+str+"</h2>";
					if (point[1] != 0){
			            chart.series[0].addPoint(point, true, shift);
			        }
				}

				// Schedule another update
            setTimeout(requestData, updatePeriod*1000);    
			},

			error: function(result) {
					var str = "You are offline. Trying to re-connect...";
					document.getElementById("myDiv").innerHTML="<h2>"+str+"</h2>";			  
	            setTimeout(requestData, updatePeriod*1000);    
			}
		});
	}  
	
	
	function prefetchData() 
	{
		document.getElementById("myDiv").innerHTML="<h2>Prefetching data...</h2>";

		$.ajax({
			url: 'power.php?last='+timeWindow,
			cache: false,

			success: function(points) {
				// Assign the received data to the chart
				// The data is in format [unix_timestamp_ms, power_watts]
            chart.series[0].setData(points, true);
				var str = "Usage now: "+formatUnits(points[points.length-1][1]);
				document.getElementById("myDiv").innerHTML="<h2>"+str+"</h2>";
				
				// Store timestamp of the first received sample
				firstSampleTime=points[0][0];
			},
	
			error: function(result) {
				var str = "ERROR: "+String(result);
				document.getElementById("myDiv").innerHTML="<h2>"+str+"</h2>";			  
            setTimeout(prefetchData, updatePeriod*1000);    
			}

		});
		return;
	}  
  

	function chartNow()
	{
   	chart = new Highcharts.Chart({
     		chart: {
				renderTo: 'container',
				type: 'spline',
				marginRight: 10,
				events: {
				  load: requestData
				}
			},
			title: {
				 text: 'Live electricity power meter (last '+timeWindow+' minutes)'
			},
			xAxis: {
				 type: 'datetime',
				 // Resolution on the x axis.
				 tickPixelInterval: 100
			},
			yAxis: {
				 title: {
				     text: 'Power (Watts)'
				 },
				 plotLines: [{
				     value: 0,
				     width: 1,
				     color: '#808080'
				 }]
			},
			tooltip: {
				// This will appear when you hover above the graph in the chart
				 formatter: function() {
				         return 'Time: '+Highcharts.dateFormat('%Y-%m-%d %H:%M:%S', this.x) +'<br>'+
				         'Power: '+Highcharts.numberFormat(this.y, 2)+' Watts';
				 }
			},
			legend: {
				 enabled: false
			},
			exporting: {
				 enabled: false
			},
			series: [{
				 marker: {
				     radius: 2
				 },
				 name: 'Power (Watts)',
			}]
		
		});
	}

	$(document).ready(function() {
		Highcharts.setOptions({
 		   global: {
      		useUTC: false
         }
      });

		chartNow();
   	prefetchData();
   });
    
});
</script>
</head>
<?php
//SQL Query for the Parent chart.
$strQuery = "SELECT Year, Watts FROM yearly_kWh";

//Execute the query, or else return the error message.
$result = $dbhandle->query($strQuery) or exit("Error code ({$dbhandle->errno}): {$dbhandle->error}");

//If the query returns a valid response, preparing the JSON array.
if ($result) {
    //`$arrData` holds the Chart Options and Data.
    $arrData = array(
        "chart" => array(
            "caption" => "Breaker 1 Yearly Consumption",
            "xAxisName"=> "Year",
            "yAxisName"=> "KiloWatt Hours",
            "paletteColors"=> "#008ee4",
            "yAxisMaxValue"=> "2*1000",
            "baseFont"=> "Open Sans",
            "theme" => "elegant"


            
        )
    );
    
    //Create an array for Parent Chart.
    $arrData["data"] = array();
    
    // Push data in array.
    while ($row = mysqli_fetch_array($result)) {
        array_push($arrData["data"], array(
            "label" => $row["Year"],
            "value" => $row["Watts"],
			//Create link for yearly drilldown as "2011"
            "link" => "newchart-json-" . $row["Year"]
        ));
        
    }
    



    //Data for Linked Chart will start from here, SQL query from quarterly table 
    $year = 2011;
    $strQuarterly = "SELECT  Quarter, Watts, Year FROM quarterly_kWh WHERE 1";
    $resultQuarterly = $dbhandle->query($strQuarterly) or exit("Error code ({$dbhandle->errno}): {$dbhandle->error}");
    
    //If the query returns a valid response, preparing the JSON array.
        if ($resultQuarterly) {
        $arrData["linkeddata"] = array(); //"linkeddata" is responsible for feeding data and chart options to child charts.
		$arrDataMonth[2011]["linkeddata"] = array();
		$arrDataMonth[2012]["linkeddata"] = array();
		$arrDataMonth[2013]["linkeddata"] = array();
		$arrDataMonth[2014]["linkeddata"] = array();
		$arrDataMonth[2015]["linkeddata"] = array();
		$arrDataMonth[2016]["linkeddata"] = array();
        $i = 0;		
        if ($resultQuarterly) {
            while ($row = mysqli_fetch_array($resultQuarterly)) {
				//Collect the Year for which Quarterly drilldown will be created
                $year = $row['Year'];
				
				//Create the monthly drilldown data				
				$arrMonthHeader[$year][$row["Quarter"]] = array();
				$arrMonthData[$year][$row["Quarter"]] = array();
				
				// Retrieve monthly data based on year and quarter
				$strMonthly = "SELECT  * FROM monthly_kWh WHERE `Year` = '".$year."' and `Quarter` = '".$row["Quarter"]."' Order by FIELD( `Month`, 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' )"  ;						
				$resultMonthly = $dbhandle->query($strMonthly) or exit("Error code ({$dbhandle->errno}): {$dbhandle->error}");
				
				//Loop through monthly results 
				 while ($rowMonthly = mysqli_fetch_assoc($resultMonthly)) {
						//Create the monthly data for each quarter
						if($rowMonthly['Quarter'] == $row["Quarter"])
						{
							array_push($arrMonthData[$year][$row["Quarter"]], array(
									"label" => $rowMonthly["Month"],
									"value" => $rowMonthly["Watts"]
								));
						}
					}
					//Create the data for monthly drilldown
					$arrMonthHeader[$year][$row["Quarter"]] = array(
										//Create the unique link id's provided from quarterly data as "2011Q1"
										"id" => $year . $row['Quarter'],
										//Create the data for the monthly charts for each quarter
										"linkedchart" => array(
											"chart" => array(
												//Create dynamic caption based on the year and quarter
												"caption" => "$year-".$row["Quarter"].":  Breaker 1 Monthly Wattage Consumption ",
												"xAxisName"=> "Month",
												"yAxisName"=> "KiLo Watt Hours",
												"paletteColors"=> "#f5555C",
												"baseFont"=> "Open Sans",
												"theme" => "elegant"
											),
										"data" => $arrMonthData[$year][$row["Quarter"]]	
										)					
									);
					 //Finally push the data created into linkeddata node. Now our linked data for monthly drilldown for each quarter is ready
					 array_push($arrDataMonth[$year]["linkeddata"],$arrMonthHeader[$year][$row["Quarter"]]);
				
				//Create the linkeddata for quarterly drilldown	
				//If the linnkeddata for a quarter of a year is ready and the year matches 
				 if (isset($arrData["linkeddata"][$i-1]) && $arrData["linkeddata"][$i-1]["id"] == $year) {					
					if($row["Quarter"] == 'Q4'){
						array_push($arrData["linkeddata"][$i-1]["linkedchart"]["data"], array(
							"label" => $row["Quarter"],
							"value" => $row["Watts"],
							//Create the link for quarterly drilldown as "newchart-json-2011Q1"
							"link" => "newchart-json-" . $year . $row["Quarter"]
						));	
					//If we've reached the last quarter, append the data created for monthly drilldown
					 $arrData["linkeddata"][$i-1]["linkedchart"] = array_merge($arrData["linkeddata"][$i-1]["linkedchart"],$arrDataMonth[$year]);
					}					
					else{
						array_push($arrData["linkeddata"][$i-1]["linkedchart"]["data"], array(
							"label" => $row["Quarter"],
							"value" => $row["Watts"],
							//Create the link for quarterly drilldown as "newchart-json-2011Q1"
							"link" => "newchart-json-" . $year . $row["Quarter"]
						));
					
					}
                }
				//Inititate the linked data for quarterly drilldown
				else {
					
                    array_push($arrData["linkeddata"], array(
                        "id" => "$year",
                        "linkedchart" => array(
                            "chart" => array(
								//Create dynamic caption based on the year
                                "caption" => "$year:  Breaker 1 Quarterly Wattage Consumption ",
                                "xAxisName"=> "Quarter",
                                "yAxisName"=> "KiloWatt Hours",
                                "paletteColors"=> "#6baa01",
                                "baseFont"=> "Open Sans",
                                "theme" => "elegant"
                            ),
                            "data" => array(
                                array(
                                    "label" => $row["Quarter"],
                                    "value" => $row["Watts"],
									//Create the link for quarterly drilldown as "newchart-json-2011Q1"
									"link" => "newchart-json-" . $year . $row["Quarter"]
                                )
                            )
                        )
						
                    ));

                    $i++;
                }
			
				
				
				
            }
			 
        }
			
       //Convert the array created into JSON as our chart would recieve the dat ain JSON
		$jsonEncodedData = json_encode($arrData);
        
        $columnChart = new FusionCharts("column2d", "myFirstChart" , "300%", "500", "linked-chart", "json", "$jsonEncodedData"); 
        
        $columnChart->render();    //Render Method
             
        $dbhandle->close(); //Closing DB Connection
     
    }
}
?>
<style>
	.button{
	margin: 0 auto;
}
</style>

<body>

<!-- DOM element for Chart -->
     <?php echo "<script type=\"text/javascript\" >
			   FusionCharts.ready(function () {
			FusionCharts('myFirstChart').configureLink({     
			overlayButton: {            
			message: 'Back',
			padding: '13',
			fontSize: '16',
			fontColor: '#F7F3E7',
			bold: '1',
			bgColor: '#22252A',           
			borderColor: '#D5555C'         }     });
			 });
			</script>" 
?>
         <div style="width:300px;" ><center><div id="linked-chart">Awesome Chart on its way!</div></center></div>
         
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/themes/gray.js"></script>
<script src="http://code.highcharts.com/modules/exporting.js"></script>

<tr>
<td>
	<div id="container" style="width: 900px; float: left; height: 400px; margin: 0 auto"></div>
</td>
</tr>
</table>
		<div id="myDiv">Usage now: 0 Watts</div>
	        <a href = "../dashboard.php"><button class = "button">Dashboard</button></a>
</body>
</html>





