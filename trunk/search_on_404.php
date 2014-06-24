<?php
/**
 * Redirect on 404
 *
 *For use on 404 'file not found' pages. Searches for possibly related images or albums based on the current URL
 *
 * @author Marcus Wong (wongm)
 * @package plugins
 */

$plugin_description = gettext("For use on 404 'file not found' pages. Searches for possibly related images or albums based on the current URL.");
$plugin_author = "Marcus Wong (wongm)";
$plugin_version = '1.0.0'; 
$plugin_URL = "http://code.google.com/p/wongm-zenphoto-plugins/";

function searchOn404() {
	global $_zp_current_search, $_zp_current_album;
	
	// load the search in controller.php
	zp_load_search();
	
	// reset the search values with what was entered in the URL
	$_zp_current_search->setSearchParams("&words=" . getSearchTermFrom404());
	
	// reset album 
	// so we look at images in search, not in the (invalid) album
	$_zp_current_album = null;
}

function wasLookingForImage() {
	global $image, $album;
	
	if (isset($image) AND $image != '') 
    {
    	return true;
    }
    
    return false;
}

function getSearchTermFrom404() {
	global $image, $album;
	
	$term = '';
	
	if (basename($album) != $album)
	{
    	$image = basename($album);
	}
	
	// get the image and album values from index.php
	if (isset($image) AND $image != '') 
	{
		$term = $image;
		
		// remove common file extensions
		$extensions = array('.html', '.htm', '.php', '.jpg', '.jpeg', '.gif', '.png');
		foreach ($extensions as $extension)
		{
			$term  = str_replace($extension, '', strtolower($term));
		}
		
		// split the search terms on "." (full stop)
		// just in case an incomplete file extension exists
		// will cause search to look at all terms
		$term  = str_replace(".", ',', strtolower($term));
	}
	else if (isset($album)) 
	{
		$term = $album;
	}
	
	return $term;
}
?>