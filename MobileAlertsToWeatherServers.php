<?php

#######################################################
# Weather Servers Account Information
#######################################################

# Mobile Alerts account
$phoneid = "<your phone ID>";
$sensorid = "<sensor ID for outdoor temperature and humidity>";

# Timezone of Mobile Alerts System
$timezone = "Europe/Berlin";

# Your Weather Cloud account
# leave empty if none exists
$wc_id = "<your WeatherCloud ID>";
$wc_key = "<your WeatherCloud secret key>";

# Your CWOP account
# leave empty if none exists
$cwopid = "<your CWOP ID>";
# Station Co-ordinates
# Example: 52°31.9'N 13°25.6'E
$cwopco = "5231.90N/01325.60E";

# Your Weather Underground account
# leave empty if none exists
$wu_id = "<your Wunderground station ID>";
$wu_pw = "<your Wunderground password>";


#######################################################

# Test mode
# 0 = live
# 1 = test
$dryrun = 0;

#######################################################

#error_reporting(E_ALL); 
set_time_limit(300);
ignore_user_abort(true);

date_default_timezone_set($timezone);
$now = gmdate('U');
$min = gmdate('i');
if ($dryrun==1) { echo "Time now: ".gmdate('Y-m-d H:i:s', $now)." ($now)"; }

# Set timeout for all URL queries to 20 sec
$context = stream_context_create( array(
  'http'=>array(
    'timeout' => 20.0
  )
));

##################################################
# read data from Mobile Alerts URL
##################################################


$file = "https://measurements.mobile-alerts.eu/Home/SensorsOverview?phoneid=$phoneid";
if ($dryrun==1) { echo "<br>Checking $file ..."; }

$dataFile = fopen( $file, "r", false, $context); 

# data mode 
# 0 = no data, skip
# 1 = checking for date and time
# 2 = checking for temp data
# 3 = checking for humidity data
$mode = 0;

$dd="";
$ff_wu="";
$ff_wa="";
$ffg_wa="";

while (!feof($dataFile)) {
	$line = fgets($dataFile);
	if ($mode==0) {
		if (preg_match ( "/$sensorid/", $line )) {
			$mode = 1;
			if ($dryrun==1) { echo "<br>Found TT/RH sensor $sensorid ...";}
			continue;
		}
		elseif (preg_match ( "/$sensoridw/", $line )) {
			$mode = 11;
			if ($dryrun==1) { echo "<br>Found wind sensor $sensorid ...";}
			continue;
		}
	} elseif ($mode==1 || $mode==11 ) { 
		if (preg_match ( "/(\d\d).(\d\d).(\d\d\d\d) (\d\d):(\d\d):\d\d/", $line, $treffer )) {
			$dtg = mktime($treffer[4],$treffer[5],0,$treffer[2],$treffer[1],$treffer[3]);
			if ($dryrun==1) { echo "<br>Found dtg=".date('Y-m-d H:i:s', $dtg);}
			$timediff = sprintf("%.1f",($now - $dtg)/60);
			if ($dryrun==1) { echo "<br>Data is $timediff min old.";}
			if ($timediff > 30) {  
				echo "<br>Data too old, aborting...";
				die();
			}
			continue;
		}		
		if (preg_match ( "/Temperatur/", $line) && ! preg_match ( "/Innen/", $line) ) {
			$mode=2;
			continue;
		}
		if (preg_match ( "/Windge/", $line)) {
			$mode=12;
			continue;
		}
	} elseif ($mode==2) {
		if (preg_match ( "/(-?\d+),(\d)/", $line, $treffer )) {
			$tt = $treffer[1].".".$treffer[2];
			if ($dryrun==1) { echo "<br>TT=$tt";}
			continue;
		}
		if (preg_match ( "/Luftfeuchte/", $line) && ! preg_match ( "/Innen/", $line) ) {
			$mode=3;
			continue;
		}
	} elseif ($mode==3) {
		if (preg_match ( "/(\d+)%/", $line, $treffer )) {
			$rh = $treffer[1];
			if ($dryrun==1) { echo "<br>RH=$rh";}
			$mode=0;
			continue;
		}
	} elseif ($mode==12) { # FF
		if (preg_match ( "/(\d+),(\d) km/", $line, $treffer )) {
			$ff_wa = $treffer[1].".".$treffer[2];
			$ff_wa = $ff_wa / 3.6; # km/h -> m/s
			if ($dryrun==1) { echo "<br>ff=$ff_wa";}
			continue;
		}
		if (preg_match ( "/B&#246;e/", $line)) {
			$mode=13;
			continue;
		}
	} elseif ($mode==13) { # FG
		if (preg_match ( "/(\d+),(\d) km/", $line, $treffer )) {
			$ffg_wa = $treffer[1].".".$treffer[2];
			$ffg_wa = $ffg_wa / 3.6; # km/h -> m/s
			if ($dryrun==1) { echo "<br>ffg=$ffg_wa";}
			continue;
		}
		if (preg_match ( "/Windrichtung/", $line)) {
			$mode=14;
			continue;
		}
	} elseif ($mode==14) { # DD
		if (preg_match ( "/S.+den/", $line, $treffer )) {
			$dd=180;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Westen/", $line, $treffer )) {
			$dd=270;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Norden/", $line, $treffer )) {
			$dd=360;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Osten/", $line, $treffer )) {
			$dd=90;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Nordost/", $line, $treffer )) {
			$dd=45;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+dost/", $line, $treffer )) {
			$dd=135;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+dwest/", $line, $treffer )) {
			$dd=225;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Nordwest/", $line, $treffer )) {
			$dd=315;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Nordnordost/", $line, $treffer )) {
			$dd=23;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Ostnordost/", $line, $treffer )) {
			$dd=68;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Osts.+dost/", $line, $treffer )) {
			$dd=113;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+ds.+dost/", $line, $treffer )) {
			$dd=158;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+ds.+dwest/", $line, $treffer )) {
			$dd=203;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Wests.+dwest/", $line, $treffer )) {
			$dd=248;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Westnordwest/", $line, $treffer )) {
			$dd=293;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Nordnordwest/", $line, $treffer )) {
			$dd=338;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		continue;
	}
}
fclose($dataFile);

$ddr = 6.112*exp((17.62*$tt)/(243.12+$tt))*$rh/100;
$td = sprintf("%.1f",235*log($ddr/6.11)/(17.1-log($ddr/6.11)));
if ($dryrun==1) { echo "<br>TD=$td";}



###############################################
# send to CWOP server
###############################################

$tt_wu = sprintf("%.1f",32.+(1.8*$tt)); # °F
$td_wu = sprintf("%.1f",32.+(1.8*$td)); # °F

if ($ff_wa != "") { 
	$ff_wa = sprintf("%.1f", $ff_wa); # avg. winspeed in m/s 
	$ff_wu = round(22.37*$ff_wa)/10; # m/s -> mph
} 
if ($ffg_wa != "") { 
	$ffg_wa = sprintf("%.1f", $ffg_wa); # avg. winspeed in m/s 
	$ffg_wu = round(22.37*$ffg_wa)/10; # m/s -> mph

}

#   Other parameters, currently not supported:
#   
#	$rr_wu = $rr1 * 0.0394; # hourly precipitation in inch
#	$rr24_wu = $rr24 * 0.0394; # 24-hourly precipitation in inch

if ($cwopid != "") {

	$date_cwop = date('dHi', $dtg)."z".$cwopco."_";

	$cwop = $cwopid.'>APRS,TCPIP*:@'.$date_cwop;
	if ($ff_wu != "") { 
		$cwop .= sprintf("%03d",$dd);
		$cwop .= sprintf("/%03.0f",$ff_wu); # mph
		if ($ffg_wu != "") { 
			$cwop .= sprintf("g%03.0f",$ffg_wu); # mph
		}
	}
	$cwop .= sprintf("t%03.0f",$tt_wu); # °F
#	$cwop .= sprintf("r%03.0f",$rr_wu*100); # inch (last 1h)
#	$cwop .= sprintf("p%03.0f",$rr24_wu*100); # inch (last 24h)
#	$cwop .= sprintf("b%05d",$pp*10);
	$cwop .= sprintf("h%02d",$rh);

	if ($dryrun==1) { echo "<br>Calling CWOP URL: $cwop ...";}
	else {
		$fp = fsockopen("cwop.aprs.net", 14580, $errno, $errstr, 30);
		if (!$fp) {
			if ($dryrun==1) { echo "$errstr ($errno)\n"; }
		} else {
		   $out = "user $cwopid pass -1 vers meteomara 2.00\r\n";
		   fwrite($fp, $out);
		   sleep(3);
		   $out = "$cwop\r\n";
		   fwrite($fp, $out);
		   sleep(3);
		   fclose($fp);
		}
	}
}


###############################################
# send to Weather Underground
###############################################

# unsupported parameters:
#$pp_wu = sprintf("%.3f",$pp * 0.02954); # inch

if ($wu_id != "") {

	$date_wu = date('Y-m-d+H:i:00', $dtg);

	$wunder = "http://weatherstation.wunderground.com/weatherstation/updateweatherstation.php?ID=$wu_id&PASSWORD=$wu_pw";
	$wunder .= "&dateutc=".$date_wu;
	$wunder .= "&humidity=".$rh."&tempf=".$tt_wu."&dewptf=".$td_wu;
	if ($ff_wu != "") {
		$wunder .= "&winddir=".$dd."&windspeedmph=".$ff_wu;
		if ($ffg_wu != "") {
			$wunder .= "&windgustmph=".$ffg_wu;
		}
	}
#	$wunder .= "&rainin=".$rr_wu."&dailyrainin=".$rr24_wu."&baromin=".$pp_wu;
	$wunder = $wunder."&softwaretype=MA2Web1.0&action=updateraw";

	if ($dryrun==1) { echo "<br>Calling Wunderground URL: $wunder ...";}
	else {
		$dataFile = fopen( $wunder, "r", false, $context);
		while (!feof($dataFile)) {
			$line = fgets($dataFile);
			if ($dryrun==1) { echo "<br>$line";}
		}
		fclose($dataFile);
	}
}



###############################################
# send to WeatherCloud server
###############################################

$date_wcl = "&time=".date('Hi', $dtg)."&date=".date('Ymd', $dtg);

if ($wc_id != "") {

	$wcloud = "http://api.weathercloud.net/v01/set?wid=$wc_id&key=$wc_key".$date_wcl;
	$wcloud .= sprintf("&temp=%.0f",$tt*10);
	$wcloud .= sprintf("&dew=%.0f",$td*10);
	$wcloud .= sprintf("&hum=%.0f",$rh);

#	$wcloud .= sprintf("&rainrate=%.0f",$rrate*10);	
#	$wcloud .= sprintf("&solarrad=%.0f",$solar*10);	
#
#	if ($min>55 || $min<5) {
#		$wcloud .= sprintf("&rain=%.0f",$rr1*10);	
#	}
#	if ($pp>0) {
#		$wcloud .= sprintf("&bar=%.0f",$pp*10);
#	}
	if ($ff_wa>0 || $dd>0) {
		$wcloud .= sprintf("&wspdavg=%.0f",$ff_wa*10);
		if ($ffg_wa>=$ff_wa) {
			$wcloud .= sprintf("&wspdhi=%.0f",$ffg_wa*10);
		}
		$wcloud .= sprintf("&wdiravg=%.0f",$dd);
	}

	if ($dryrun==1) { echo "<br>Calling Weather Cloud URL: $wcloud ...";}

	if ($dryrun==0) {
		header("Location: $wcloud");
		exit;
	}

}
?>
