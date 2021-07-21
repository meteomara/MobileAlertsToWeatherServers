<?php

#######################################################
# Weather Servers Account Information
#######################################################

# Mobile Alerts account
$phoneid = "<your Mobile Alerts phone ID>";
$sensorid = "<sensor ID for outdoor temperature and humidity>";
$sensoridw = "<sensor ID for wind>"; 
$sensoridr = "<sensor ID for rain>"; 
$sensoridp = "<sensor ID for pressure>"; 

# Timezone of Mobile Alerts System
$timezone = "Europe/Berlin";

# Your Weather Cloud account
# leave empty if none exists
$wc_id = "<your WeatherCloud ID>";
$wc_key = "<your WeatherCloud key>";

# Your CWOP account
# leave empty if none exists
$cwopid = "<your CWOP ID>"; # set to empty string, if no account exists
# Station Co-ordinates
# Example: 52°31.9'N 13°25.6'E
$cwopco = "5231.90N/01325.60E";

# Your Weather Underground account
# leave empty if none exists
$wu_id = "<your Wunderground station ID>"; # set to empty string, if no account exists
$wu_pw = "<your Wunderground key>";

$rr_factor = 1.0;
$ff_factor = 1.0;

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
# round to full 10 min
$now = round($now/600)*600;
$last1h = $now-60*60;
if ($dryrun==1) { echo "<br>Time now: ".gmdate('Y-m-d H:i:s', $now)." ($now)"; }

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

$dataFile = fopen( $file, "r", false, $context[0]); 
$mode = 0;

$dd="";
$ff_wu=-9999;
$ff_wa=-9999;
$ffg_wa=-9999;
$ffg_wu=-9999;
$tt=-9999;
$pp=0;
$rr=-9999;
$rrd=-9999;

# IMPORTANT!
# Mobile Alerts shows the precipitation values as a total sum.
# This means, we need the value of the previous measurement to derive the amount
# since the last measurement.
# You should either store this value in a database or in a file and read it here
# into this variable. You should not keep this line as it is!
$rrd_old = 0;

while (!feof($dataFile)) {
	$line = fgets($dataFile);
	if ($mode==0) {
		if (preg_match ( "/>$sensorid</", $line )) {
			$mode = 1;
			if ($dryrun==1) { echo "<br>Found TT/RH sensor $sensorid ...";}
			continue;
		}
		elseif (preg_match ( "/>$sensoridw</", $line )) {
			$mode = 11;
			if ($dryrun==1) { echo "<br>Found wind sensor $sensoridw ...";}
			continue;
		}
		elseif (preg_match ( "/>$sensoridr</", $line )) {
			$mode = 21;
			if ($dryrun==1) { echo "<br>Found rain sensor $sensoridr ...";}
			$rr=0;
			$rrd=0;
			continue;
		}
		elseif (preg_match ( "/>$sensoridp</", $line )) {
			$mode = 31;
			if ($dryrun==1) { echo "<br>Found pressure sensor $sensoridp ...";}
			continue;
		}
	} elseif ($mode==1 || $mode==11 || $mode==21  || $mode==31 ) { 
		if (preg_match ( "/(\d\d)\.(\d\d)\.(\d\d\d\d) (\d\d):(\d\d):(\d\d)/", $line, $treffer ) ||   # DD.MM.YYYY HH:MM:SS
		    preg_match ( "/(\d+)\/(\d+)\/(\d\d\d\d) (\d+):(\d\d):\d\d (.M)/", $line, $treffer )		# M-D-YYYY HH:MM:SS AM/PM
		) {
			# Check for US date format
			if ($treffer[6] == "AM") {
				$dtg = mktime($treffer[4],$treffer[5],0,$treffer[1],$treffer[2],$treffer[3]);
			}
			elseif ($treffer[6] == "PM") {
				$dtg = mktime($treffer[4]+12,$treffer[5],0,$treffer[1],$treffer[2],$treffer[3]);
			} 
			else {
				$dtg = mktime($treffer[4],$treffer[5],0,$treffer[2],$treffer[1],$treffer[3]);
			}
			if ($dryrun==1) { echo "<br>Found dtg=".date('Y-m-d H:i:s', $dtg);}
			$timediff = sprintf("%.1f",($now - $dtg)/60);
			if ($dryrun==1) { echo "<br>Data is $timediff min old.";}

			# Precipitation data may be old in case of no rain!
			if ($mode==21 && $timediff > 80) {
				$rr = 0;
				$mode = 0;
				if ($dryrun==1) { echo "<br>RR=$rr";}
				continue;
			}
			elseif ($timediff > 80) {  
				if ($dryrun==1) { echo "<br>Data too old, skipping...";  }
				$mode=0;
				continue;
			}
			continue;
		}		
		elseif (preg_match ( "/Temperatur/", $line) && ! preg_match ( "/Innen/", $line) && ! preg_match ( "/Kabel/", $line) && $mode != 31 && $mode != 41  ) {
			$mode=2;
			continue;
		}
		elseif (preg_match ( "/Windge/", $line) || preg_match ( "/Windsp/", $line)) {
			$mode=12;
			continue;
		}
		elseif (preg_match ( "/Regen/", $line) || preg_match ( "/Rain/", $line)) {
			$mode=22;
			continue;
		}
		elseif (preg_match ( "/Luftdr/", $line) || preg_match ( "/ressure/", $line)) {
			$mode=32;
			continue;
		}
		elseif (preg_match ( "/Temperatur/", $line)) {
			$mode=42;
			continue;
		}
		elseif (preg_match ( "/sensor-header/", $line )) {
			$mode=0;
			continue;
		}		
	} elseif ($mode==2) {
		if ($tt < -1000 && preg_match ( "/(-?\d+)[,.](\d)/", $line, $treffer )) {
			$tt = $treffer[1].".".$treffer[2];
			if ($dryrun==1) { echo "<br>TT=$tt";}
			continue;
		}
		if (preg_match ( "/Luftfeuchte/", $line) && ! preg_match ( "/Innen/", $line) ) {
			$mode=3;
			continue;
		}
		if (preg_match ( "/Humidity/", $line) && ! preg_match ( "/Inside/", $line) ) {
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
		if (preg_match ( "/(\d+)[,.](\d) km/", $line, $treffer )) {
			$ff_wa = $treffer[1].".".$treffer[2];
			$ff_wa = $ff_wa / 3.6 * $ff_factor; # km/h -> m/s
			if ($dryrun==1) { echo "<br>ff=$ff_wa";}
			continue;
		}
		elseif (preg_match ( "/(\d+)[,.](\d) m/", $line, $treffer )) {
			$ff_wa = $treffer[1].".".$treffer[2];
			$ff_wa = $ff_wa * $ff_factor; 
			if ($dryrun==1) { echo "<br>ff=$ff_wa";}
			continue;
		}
		if (preg_match ( "/B&#246;e/", $line) || preg_match ( "/Gust/", $line)) {
			$mode=13;
			continue;
		}
	} elseif ($mode==13) { # FG
		if (preg_match ( "/(\d+)[,.](\d) km/", $line, $treffer )) {
			$ffg_wa = $treffer[1].".".$treffer[2];
			$ffg_wa = $ffg_wa / 3.6 * $ff_factor; # km/h -> m/s
			if ($dryrun==1) { echo "<br>ffg=$ffg_wa";}
			continue;
		}
		elseif (preg_match ( "/(\d+)[,.](\d) m/", $line, $treffer )) {
			$ffg_wa = $treffer[1].".".$treffer[2];
			$ffg_wa = $ffg_wa * $ff_factor; 
			if ($dryrun==1) { echo "<br>ffg=$ffg_wa";}
			continue;
		}
		if (preg_match ( "/Windrichtung/", $line) || preg_match ( "/Wind Direction/", $line)) {
			$mode=14;
			continue;
		}
	} elseif ($mode==14) { # DD
		if (preg_match ( "/Nordnordost/", $line, $treffer )) {
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
		elseif (preg_match ( "/Nordost/", $line, $treffer) || preg_match ( "/Northeast/", $line, $treffer) ) {
			$dd=45;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+dost/", $line, $treffer) || preg_match ( "/Southeast/", $line, $treffer) ) {
			$dd=135;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+dwest/", $line, $treffer) || preg_match ( "/Southwest/", $line, $treffer) ) {
			$dd=225;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Nordwest/", $line, $treffer) || preg_match ( "/Northwest/", $line, $treffer) ) {
			$dd=315;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/S.+den/", $line, $treffer) || preg_match ( "/South/", $line, $treffer) ) {
			$dd=180;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Westen/", $line, $treffer) || preg_match ( "/West/", $line, $treffer)  ) {
			$dd=270;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Norden/", $line, $treffer) || preg_match ( "/North/", $line, $treffer) ) {
			$dd=360;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		elseif (preg_match ( "/Osten/", $line, $treffer) || preg_match ( "/East/", $line, $treffer) ) {
			$dd=90;
			$mode=0;
			if ($dryrun==1) { echo "<br>dd=$dd";}
		}
		continue;
	} elseif ($mode==22) {
		if (preg_match ( "/(\d+)[,.](\d) mm/", $line, $treffer )) {
			$rrd = $treffer[1].".".$treffer[2];
			$rrd = $rrd * $rr_factor;
			if ($dryrun==1) { echo "<br>RRd=$rrd (Factor applied: $rr_factor)"; }  
			$mode=0;	
			
			# calc diff to current rrd value
			if ($rrd_old <= $rrd) {
				$rr = $rrd - $rrd_old;
			} else {
				$rr = 0;
			}
			if ($dryrun==1) { echo "<br>RR=$rr"; } # this is the rain of the last 10 min

			continue;
		}
	} elseif ($mode==32) {
		if (preg_match ( "/(\d+)[,.](\d)/", $line, $treffer )) {
			$pp = $treffer[1].".".$treffer[2];
			if ($dryrun==1) { echo "<br>PP=$pp";}
			$mode=0;
			continue;
		}
	}
}
fclose($dataFile);


if ($tt<-9000) {
	echo "No current data found, aborting.";
	die();
}

# calc dew point
$ddr = 6.112*exp((17.62*$tt)/(243.12+$tt))*$rh/100;
$td = sprintf("%.1f",235*log($ddr/6.11)/(17.1-log($ddr/6.11)));
if ($dryrun==1) { echo "<br>TD=$td";}


# IMPORTANT!
# Here would be a good time to save the valur of the variable $rrd 
# for the next run either in a database or on a file.

	
###############################################
# send to CWOP server
###############################################

$tt_wu = sprintf("%.1f",32.+(1.8*$tt)); # °F
$td_wu = sprintf("%.1f",32.+(1.8*$td)); # °F

if ($ff_wa >= 0) { 
	$ff_wa = sprintf("%.1f", $ff_wa); # avg. winspeed in m/s 
	$ff_wu = round(22.37*$ff_wa)/10; # m/s -> mph
} 
if ($ffg_wa >= 0) { 
	$ffg_wa = sprintf("%.1f", $ffg_wa); # avg. winspeed in m/s 
	$ffg_wu = round(22.37*$ffg_wa)/10; # m/s -> mph

}
if ($rrd > -1) {
	$rrd_wu = $rrd * 0.0394; # precipitation in inch since midnight
	$rr_wu = $rr * 0.0394; # precipitation rate in inch
	$rr1h_wu = $rr1h * 0.0394; # hourly precipitation in inch
}

#   Other parameters, currently not supported:
#   
#	$rr24_wu = $rr24 * 0.0394; # 24-hourly precipitation in inch
#   Lxxx = luminosity (in watts per square meter) 999 and below
#   lxxx = luminosity (in watts per square meter) >= 1000 (subtract 1000)

if ($cwopid != "") {

	$date_cwop = date('dHi', $now)."z".$cwopco."_";

	$cwop = $cwopid.'>APRS,TCPIP*:@'.$date_cwop;
	if ($ff_wu >= 0) { 
		$cwop .= sprintf("%03d",$dd);
		$cwop .= sprintf("/%03.0f",$ff_wu); # mph
		if ($ffg_wu >= 0) { 
			$cwop .= sprintf("g%03.0f",$ffg_wu); # mph
		}
	}
	$cwop .= sprintf("t%03.0f",$tt_wu); # °F
	if ($rrd > -1) { 
		$cwop .= sprintf("P%03.0f",$rrd_wu*100); # inch (since midnight)
	}
	if ($rr1h > -1) { 
		$cwop .= sprintf("r%03.0f",$rr1h_wu*100); # inch (since midnight)
	}
#	$cwop .= sprintf("p%03.0f",$rr24_wu*100); # inch (last 24h)
	if ($pp > 0) { 
		$cwop .= sprintf("b%05d",$pp*10);
	}
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

$pp_wu = sprintf("%.3f",$pp * 0.02954); # inch

if ($wu_id != "") {

	$date_wu = date('Y-m-d+H:i:00', $now);

	# Format: https://support.weather.com/s/article/PWS-Upload-Protocol?language=en_US

	$wunder = "https://weatherstation.wunderground.com/weatherstation/updateweatherstation.php?ID=$wu_id&PASSWORD=$wu_pw";
	$wunder .= "&dateutc=".$date_wu;
	$wunder .= "&humidity=".$rh."&tempf=".$tt_wu."&dewptf=".$td_wu;
	if ($ff_wu >= 0) {
		$wunder .= "&winddir=".$dd."&windspeedmph=".$ff_wu;
		if ($ffg_wu >= 0) {
			$wunder .= "&windgustmph=".$ffg_wu;
		}
	}
	if ($rrd > -1) {
		$wunder .=  sprintf("&rainin=%.2f",$rr_wu*6);    # rainrate in inch/h = rain in 10 min * 6
		$wunder .= "&dailyrainin=".$rrd_wu;
	}
	if ($pp > 0) {
		$wunder .= "&baromin=".$pp_wu;
	}
	if ($rad > -1) {
		$wunder .= "&solarradiation=".$rad;
	}	
	$wunder = $wunder."&softwaretype=MA2Web1.4&action=updateraw";

	if ($dryrun==1) { echo "<br>Calling Wunderground URL: $wunder ...";}
	else {
		$dataFile = fopen( $wunder, "r", false, $context[0]);
		$line = fgets($dataFile);
		echo "<br>WU: $line";
		fclose($dataFile);
	}
}



###############################################
# send to WeatherCloud server
###############################################

$date_wcl = "&time=".date('Hi', $now)."&date=".date('Ymd', $now);

if ($wc_id != "") {

	$wcloud = "https://api.weathercloud.net/v01/set?wid=$wc_id&key=$wc_key".$date_wcl;
	$wcloud .= sprintf("&temp=%.0f",$tt*10);
	$wcloud .= sprintf("&dew=%.0f",$td*10);
	$wcloud .= sprintf("&hum=%.0f",$rh);

	if ( $rrd>-1 ) {
		$wcloud .= sprintf("&rain=%.0f",$rrd*10); # daily rain in mm
	}
	if ( $rr>-1 ) {
		$wcloud .= sprintf("&rainrate=%.0f",$rr*60);	  # rainrate in mm/h = rain in 10 min * 6
	}

	if ($pp>0) {
		$wcloud .= sprintf("&bar=%.0f",$pp*10);
	}

	if ($ff_wa>0 || $dd>0) {
		$wcloud .= sprintf("&wspdavg=%.0f",$ff_wa*10);
		if ($ffg_wa>=$ff_wa) {
			$wcloud .= sprintf("&wspdhi=%.0f",$ffg_wa*10);
		}
		$wcloud .= sprintf("&wdiravg=%.0f",$dd);
	}
	if ($rad > -1) {
		$wcloud .= sprintf("&solarrad=%.0f",$rad*10);	
	}	

	if ($dryrun==1) { echo "<br>Calling Weather Cloud URL: $wcloud ...";}
	else {

		$dataFile = fopen( $wcloud, "r", false, $context);
		if ($dataFile) {
			$line = fgets($dataFile);
			echo "$line ";
		} else {
			echo "FAILED ";
		}

	}

}
?>
