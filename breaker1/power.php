<?php

function connect_db()
{
	// Connects to the database 
	mysql_connect("localhost", "root", "root") or die(mysql_error()); 
	mysql_select_db("ICBP") or die(mysql_error()); 
}


function showUsageTable()
{
   echo "<a href=index.php>Real	 graph</a><br>";
   echo "<center>Electricity consumption</center><br>";
   connect_db();
 
   $result = mysql_query("SELECT * FROM radio1 WHERE Time >= CURRENT_TIMESTAMP - INTERVAL 50000 MINUTE ORDER BY Time DESC") or die (mysql_error());
   
?>
<table border="1" align=center> 
<tr><td  width=10>Sample</td><td  width=200>Date and time</td><td  width=100>Power (W)</td></tr>
<?php
   while ($row = mysql_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>".$row['id']."</td>";
    echo "<td>".$row['Time']."</td>";
    echo "<td>".$row['Watts']."</td>";
    echo "</tr>";
   }
?>
 </table> 
<?php
}


function getRange($from, $to)
{
   connect_db(); 
   $sql="SELECT * FROM radio1  WHERE Time BETWEEN '${from}' AND '${to}' ORDER BY Time ASC";
   
   $result = mysql_query($sql) or die (mysql_error());

	$ar=array();
	while ($row = mysql_fetch_assoc($result)) {
		$pwr=intval($row['Watts']);		
		if ($pwr != 0){
			array_push($ar, array(strtotime($row['Time'])*1000, $pwr));
		}
	}

	return $ar;
}


function getRangeJsn($from, $to)
{
	header("Content-type: text/json");
	$ar = getRange($from, $to);
	echo json_encode($ar);
}

function getLast($minutes)
{
	$tm = time();
	$from = date('Y-m-d H:i:s', $tm - $minutes*1);
	$to = date('Y-m-d H:i:s', $tm);
	return getRangeJsn($from, $to);
}

function getNow()
{
	 header("Content-type: text/json");
    connect_db();
 
   $result = mysql_query("SELECT * FROM radio1 ORDER BY Time DESC LIMIT 1") or die (mysql_error());
   
   $row = mysql_fetch_assoc($result);
	if ($row){
		// Convert time to unix timestamp in milliseconds
		// The unix timestamp is the number of seconds since January 1 1970 00:00:00 UTC
    	$ret = array(strtotime($row['Time'])*1000, intval($row['Watts']));
		echo json_encode($ret);
   }
}

function updatePower($val)
{
   connect_db();
   $result = "INSERT INTO radio1 (Watts) VALUES (".$val.")";
   
   $add_member = mysql_query($result);
}

// ----------------------- ENTRY POINT --------------------------------------
foreach($_GET as $key => $val){
   switch ($key){
	case "last":
		getLast($val);
		return;
	case "now":
		getNow();
		return;
	case "add":
 	   updatePower($val);
	   return;
	break;
   }
}

// Without parameters show simple html table with the power consumption history
showUsageTable();
?>

