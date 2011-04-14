<?php
/**
 * Archive days
 *
 * Enables the Archive page to drill down to days, not just months
 *
 * @author Marcus Wong (wongm)
 * @package plugins
 */

$plugin_description = gettext("Invludes template function to allow the Archive page to drill down to days, not just months.");
$plugin_author = "Marcus Wong (wongm)";
$plugin_version = '1.0.0'; 
$plugin_URL = "http://code.google.com/p/wongm-zenphoto-plugins/";

/**
 * Prints a compendum of dates and links to a search page that will show results of the date
 *
 * @param string $class optional class
 * @param string $yearid optional class for "year"
 * @param string $monthid optional class for "month"
 * @param string $order set to 'desc' for the list to be in descending order
 */
function printAllMonths($class='archive', $yearid='year', $monthid='month', $order='desc') {
	if (!empty($class)){ $class = "class=\"$class\""; }
	if (!empty($yearid)){ $yearid = "class=\"$yearid\""; }
	if (!empty($monthid)){ $monthid = "class=\"$monthid\""; }
	$datecount = getAllDates($order);
	$lastyear = "";
	echo "\n<ul $class>\n";
	$nr = 0;
	while (list($key, $val) = each($datecount)) {
		$nr++;
		if ($key == '0000-00-01') {
			$year = "no date";
			$month = "";
		} else {
			$dt = strftime('%Y-%B', strtotime($key));
			$year = substr($dt, 0, 4);
			$month = substr($dt, 5);
		}

		if ($lastyear != $year) {
			$lastyear = $year;
			if($nr != 1) {  echo "</ul>\n</li>\n";}
			echo "<li $yearid>$year\n<ul $monthid>\n";
		}
		$archiveURL = ARCHIVE_URL_PATH.'/'.substr($key, 0, 7);
		$searchURL = SEARCH_URL_PATH.'/archive/'.substr($key, 0, 7);
		echo "<li><a href=\"".htmlspecialchars($archiveURL)."\" rel=\"nofollow\">$month ($val photos)</a> <a href=\"".htmlspecialchars($searchURL)."\" rel=\"nofollow\">(show all photos)</a></li>\n";
	}
	echo "</ul>\n</li>\n</ul>\n";
}

/**
 * Prints a compendum of dates and links to a search page that will show results of the date
 *
 * @param string $class optional class
 * @param string $yearid optional class for "year"
 * @param string $monthid optional class for "month"
 * @param string $order set to 'desc' for the list to be in descending order
 */
function printAllDays($month) {
	if (!empty($class)){ $class = "class=\"$class\""; }
	if (!empty($yearid)){ $yearid = "class=\"$yearid\""; }
	if (!empty($monthid)){ $monthid = "class=\"$monthid\""; }
	$datecount = getAllDaysInMonth($month, $order);
	$lastyear = "";
	echo "\n<ul $class>\n";
	$nr = 0;
	while (list($key, $val) = each($datecount)) {
		$nr++;
		if ($key == '0000-00-01') {
			$year = "no date";
			$month = "";
		} else {
			$dt = strftime('%Y-%B', strtotime($key));
			$year = substr($dt, 0, 4);
			$month = substr($dt, 5);
			$day = substr($key, 8, 2);
		}

		if ($lastyear != $year) {
			$lastyear = $year;
			if($nr != 1) {  echo "</ul>\n";}
		}
		$searchURL = SEARCH_URL_PATH.'/archive/'.substr($key, 0, 11);
		echo "<li><a href=\"".htmlspecialchars($searchURL)."\" rel=\"nofollow\">$month $day ($val photos)</a></li>\n";
	}
	echo "</ul>\n</li>\n</ul>\n";
}

/**
 * Retrieves a list of all unique years & months from the images in the gallery
 *
 * @param string $order set to 'desc' for the list to be in descending order
 * @return array
 */
function getAllDaysInMonth($month, $order='desc') {
	$alldates = array();
	$cleandates = array();
	$sql = "SELECT `date` FROM ". prefix('images');
	$special = new Album(new Gallery(), '');
	$sql .= "WHERE `albumid`!='".$special->id."' AND `date` <= '$month-31' AND `date` >= '$month-01'";
	if (!zp_loggedin()) { $sql .= " AND `show` = 1"; }
	$result = query_full_array($sql);
	foreach($result as $row){
		$alldates[] = $row['date'];
	}
	foreach ($alldates as $adate) {
		if (!empty($adate)) {
			$cleandates[] = substr($adate, 0, 11);// . "-01";
		}
	}
	
	$datecount = array_count_values($cleandates);
	if ($order == 'desc') {
		krsort($datecount);
	} else {
		ksort($datecount);
	}
	return $datecount;
}

?>