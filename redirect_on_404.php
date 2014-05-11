<?php
/**
 * Redirect on 404
 *
 * Redirects the browser to the correct image or album if a matching item is found in the database
 *
 * @author Marcus Wong (wongm)
 * @package plugins
 */

$plugin_description = gettext("Redirects the browser to the correct image or album if a matching item is found in the database. Requires all gallery items to have unique album folder names and unique image filenames.");
$plugin_author = "Marcus Wong (wongm)";
$plugin_version = '1.0.0'; 
$plugin_URL = "http://code.google.com/p/wongm-zenphoto-plugins/";

function redirectOn404() {
	redirectToCachedImageOn404();
	redirectToImagePageOn404();
	redirectToAlbumOn404();
}

/*
 * trying to access cached image but it doesn't exist
 * eg: /cache/buses/E100_4823_500.jpg
 * suffering from an empty cache file?
 * so redirect to i.php to regenerate it
 */
function redirectToCachedImageOn404() {
	// load global variables from Zenphoto's index.php
	global $album, $image;
	
	if (strtolower(substr($album, 0, 5)) == 'cache') {
		// check for '_thumb' at the end of the URL - take note, then remove
		$isThumb = (strtolower(substr($image, (strlen(stripSuffix($image)) - 6), 6)) == '_thumb');
		$filenameToCheck = str_replace("_thumb.", ".", $image);
		
		// clean up album URL
		$album = str_replace("cache/", "", $album);
			
		// try to find the image size
		$imagesize = null;
		$imageparams = explode("_", $filenameToCheck);
		
		// separate the image size from file extension
		$imageparams = explode(".", $imageparams[sizeof($imageparams) - 1]);
		if (in_array(getSuffix($image), array('jpg','jpeg','gif','png'))) {
			$imagesize = $imageparams[0];
			// cleanup filename to get base image
			$filenameToCheck = str_replace("_" . $imagesize, "", $filenameToCheck);
		}
		
		// we have an image size
		if (is_numeric($imagesize) && $imagesize > 0) {
			if ($isThumb) {
				$imagesize = 'thumb';
			}
				
			if (strlen($album) > 0 && strlen($filenameToCheck) > 0) {
				$location = sprintf(FULLWEBPATH . "/zp-core/i.php?a=%s&i=%s&s=%s", $album, $filenameToCheck, $imagesize);
				status_header(302);
				header("Location: $location");
				die();
			}
		}
	}	
}

function redirectToImagePageOn404() {
	// load global variables from Zenphoto's index.php
	global $image;
	
	// make sure we are accessing an image
	if ($image != '') {
		// query the DB to find any images with the EXACT same filename as requested
		$searchSql = "SELECT folder, filename FROM " . prefix('images') . " i INNER JOIN " . 
				prefix('albums') . " a ON i.albumid = a.id WHERE i.filename = " . 
				db_quote($image) . "";
		
		$searchResult = query_full_array($searchSql);
		
		// if single result is returned, then we have a match!
		if (sizeof($searchResult) == 1) {
			// fix for some "query_full_array()" differences (on some Zenphoto versions?)
			if (sizeof($searchResult[0]) > 1) {
				$searchResult = $searchResult[0];
			}
			
			// build up the URL to redirect to
			$location = rewrite_path("/" . $searchResult["folder"] . "/" . $searchResult["filename"] . IM_SUFFIX,
										"/index.php?album=" . $searchResult["folder"] . "&image=" . $searchResult["filename"]);
			
			// redirect the browser
			status_header(301);
			header("Location: $location");
			die();
		}
	}
}

function redirectToAlbumOn404()
{
	// default Zenphoto behaviour does not return bottom level folder
	if (getOption('mod_rewrite') AND isset($_GET['album'])) {
		$album = urldecode(sanitize($_GET['album'], 0));
		//strip trailing slashes
		if (substr($album, -1, 1) == '/') {
			$album = substr($album, 0, strlen($album)-1);
		}
	// load global variables from Zenphoto's index.php
	} else {
		global $album;
	}
	
	$albumbits = explode('/', $album);
	
	// get the bottom folder in the hierarchy
	if (sizeof($albumbits) > 0) {
		$albumFolderToSearchFor = $albumbits[sizeof($albumbits) - 1];
		
		// query the DB to find any albums with same folder name
		// NOTE: albums contain the entire folder path 
		// we only want to look at the bottom level of the hierarchy
		$searchSql = "SELECT folder FROM " . prefix('albums') . " a WHERE a.folder LIKE " . 
				db_quote('%'.$albumFolderToSearchFor) . " OR a.folder = " . 
				db_quote($albumFolderToSearchFor) . "";
		
		$searchResult = query_full_array($searchSql);
		
		// if single result is returned, then we have a match!
		if (sizeof($searchResult) == 1)  {
			// fix for some "query_full_array()" differences (on some Zenphoto versions?)
			if (sizeof($searchResult[0]) > 0) {
				$searchResult = $searchResult[0];
			}
			
			// build up the URL to redirect to
			$location = rewrite_path("/" . $searchResult["folder"] . "/",
										"/index.php?album=" . $searchResult["folder"]);
			
			// redirect the browser
			status_header(301);
			header("Location: $location");
			die();
		}		
	}
}

function status_header( $header ) {
	if ( 200 == $header )
		$text = 'OK';
	elseif ( 301 == $header )
		$text = 'Moved Permanently';
	elseif ( 302 == $header )
		$text = 'Moved Temporarily';
	elseif ( 304 == $header )
		$text = 'Not Modified';
	elseif ( 404 == $header )
		$text = 'Not Found';
	elseif ( 410 == $header )
		$text = 'Gone';

	@header("HTTP/1.1 $header $text");
	@header("Status: $header $text");
}
?>