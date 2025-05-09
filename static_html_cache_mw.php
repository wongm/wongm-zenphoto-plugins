<?php

/**
 *
 * Used to cache Theme pages (i.e. those pages launched by the Zenphoto index.php script.)
 *
 * Exceptions to this are the <var>password.php</var> and <var>404.php</var> pages, any page listed in the
 * <i>Excluded pages</i> option, and any page whose script makes a call on the
 * <var>static_cache_html::disable()</var> function. <b>NOTE:</b> this function only prevents the
 * creation of a cache image of the page being viewed. If there is already an existing
 * cached page and none of the other exclusions are in effect, the cached page will be
 * shown.
 *
 * Caching is also aborted when the page being rendered is not static. For instance
 * pages containing a link to the image processor will not be cached so that the
 * image may be cached and the link changed to the cache folder. Similarly, pages
 * which contain comment forms are not cached because then the comment would never show.
 *
 * In addition, caching does not occur for pages viewed by Zenphoto users if the user has
 * <var>ADMIN</var> privileges or if he is the manager of an album being viewed or whose images are
 * being viewed. Likewise, Zenpage News and Pages are not cached when viewed by the author.
 *
 * @author Malte Müller (acrylian), Stephen Billard (sbillard)
 * @package zpcore\plugins\statichtmlcache
 */
$plugin_is_filter = 400 | CLASS_PLUGIN;
$plugin_description = gettext("Adds static HTML cache functionality to Zenphoto.");
$plugin_author = "Malte Müller (acrylian), Stephen Billard (sbillard)";
$plugin_category = gettext('Admin');
$option_interface = 'staticHTMLCacheMWOptions';

zp_register_filter('admin_utilities_buttons', 'static_html_cache_mw::overviewbutton');
zp_register_filter('show_change', 'static_html_cache_mw::clearOnPublish');
zp_register_filter('admin_head', 'static_html_cache_mw::hideButton');

$cache_path = SERVERPATH . '/' . STATIC_CACHE_FOLDER . "/";
if (!file_exists($cache_path)) {
	if (!mkdir($cache_path, FOLDER_MOD)) {
		die(gettext("Static HTML Cache folder could not be created. Please try to create it manually via FTP with chmod 0777."));
	}
}
$cachesubfolders = array('index', 'albums', 'images', 'pages', 'news');
foreach ($cachesubfolders as $cachesubfolder) {
	$cache_folder = $cache_path . $cachesubfolder . '/';
	if (!file_exists($cache_folder)) {
		if (!mkdir($cache_folder, FOLDER_MOD)) {
			die(gettext("Static HTML Cache folder could not be created. Please try to create it manually via FTP with chmod 0777."));
		}
	}
}

if (OFFSET_PATH == 2) { //	clear the cache upon upgrade
	static_html_cache_mw::clearHTMLCache();
}

$_zp_html_cache = new static_html_cache_mw();
zp_register_filter('image_processor_uri', 'static_html_cache_mw::_disable');

class staticHTMLCacheMWOptions {

	function __construct() {
		setOptionDefault('static_cache_expire', 86400);
		setOptionDefault('static_cache_excludedpages', 'search.php/,contact.php/,register.php/,favorites.php/');
		setOptionDefault('static_cache_albums', 0);
		setOptionDefault('static_cache_images', 0);
		setOptionDefault('static_cache_news', 0);
		setOptionDefault('static_cache_pages', 0);
	}

	function getOptionsSupported() {
		$list = array(
				'<em>' . gettext('Albums') . '</em>' => 'static_cache_albums',
				'<em>' . gettext('Images') . '</em>' => 'static_cache_images');
		if (extensionEnabled('zenpage')) {
			$list['<em>' . gettext('News') . '</em>'] = 'static_cache_news';
			$list['<em>' . gettext('Pages') . '</em>'] = 'static_cache_pages';
		} else {
			setOption('static_cache_news', 0);
			setOption('static_cache_pages', 0);
		}
		return array(
				gettext('Static HTML cache expire') => array(
						'key' => 'static_cache_expire',
						'type' => OPTION_TYPE_TEXTBOX,
						'desc' => gettext("When the cache should expire in seconds. Default is 86400 seconds (1 day  = 24 hrs * 60 min * 60 sec).")),
				gettext('Excluded pages') => array(
						'key' => 'static_cache_excludedpages',
						'type' => OPTION_TYPE_TEXTBOX,
						'desc' => gettext("The list of pages to be excluded from cache generation. Pages that can be excluded are custom theme pages including Zenpage pages (these optionally more specific by titlelink) and the standard theme files image.php (optionally by image file name), album.php (optionally by album folder name) or index.php.<br /> If you want to exclude a page completely enter <em>page-filename.php/</em>. <br />If you want to exclude a page by a specific title, image filename, or album folder name enter <em>pagefilename.php/titlelink or image filename or album folder</em>. Separate several entries by comma.<br />") .
						'<div class="notebox">' . gettext("<strong>NOTE:</strong> In order to work correctly, the following theme pages must be excluded from cache generation: <em>search.php, contact.php, register.php</em> and <em>favorites.php</em>") . '</div>'),
				gettext('Purge cache files') => array(
				'key' => 'static_cache_items',
				'type' => OPTION_TYPE_CHECKBOX_ARRAY,
				'order' => 0,
				'checkboxes' => $list,
				'desc' => gettext('If a <em>type</em> is checked, the HTML caches for the item will be purged when the published state of an item of <em>type</em> changes.') .
				'<div class="notebox">' . gettext('<strong>NOTE:</strong> The entire cache is cleared since there is no way to ascertain if a gallery page contains dependencies on the item.') . '</div>' )
		);
	}

	function handleOption($option, $currentValue) {
		
	}

}

class static_html_cache_mw {

	public $enabled = true; // manual disable caching a page
	private $pageCachePath = NULL;
	private $dirty;

	/**
	 * Checks if the current page should be excluded from caching.
	 * Pages that can be excluded are custom pages included Zenpage pages (these optionally more specific by titlelink)
	 * and the standard theme pages image.php (optionally by image file name), album.php (optionally by album folder name)
	 * or index.php
	 *
	 * @return bool
	 *
	 */
	function checkIfAllowedPage() {
		global $_zp_gallery_page, $_zp_current_image, $_zp_current_album, $_zp_current_zenpage_page,
		$_zp_current_zenpage_news, $_zp_current_admin_obj, $_zp_current_category, $_zp_authority;
		if (zp_loggedin(ADMIN_RIGHTS)) { // don't cache for admin
			return false;
		}
		switch ($_zp_gallery_page) {
			case "image.php": // does it really makes sense to exclude images and albums?
				$obj = $_zp_current_album;
				$title = $_zp_current_image->filename;
				break;
			case "album.php":
				$obj = $_zp_current_album;
				$title = $_zp_current_album->name;
				break;
			case 'pages.php':
				$obj = $_zp_current_zenpage_page;
				$title = $_zp_current_zenpage_page->getName();
				break;
			case 'news.php':
				if (in_context(ZP_ZENPAGE_NEWS_ARTICLE)) {
					$obj = $_zp_current_zenpage_news;
					$title = $obj->getName();
				} else {
					if (in_context(ZP_ZENPAGE_NEWS_CATEGORY)) {
						$obj = $_zp_current_category;
						$title = $obj->getName();
					} else {
						$obj = NULL;
						$title = NULL;
					}
				}
				break;
			default:
				$obj = NULL;
				if (isset($_GET['title'])) {
					$title = sanitize($_GET['title']);
				} else {
					$title = "";
				}
				break;
		}
		if ($obj && $obj->isMyItem($obj->manage_some_rights)) { // user is admin to this object--don't cache!
			return false;
		}
		$accessType = checkAccess();
		if ($accessType) {
			if (is_numeric($accessType)) {
				$accessType = 'zpcms_auth_user';
			} else if ($accessType == 'zp_public_access' && count($_zp_authority->getAuthCookies()) > 0) {
				$accessType .= '1'; // logged in some sense
			}
		} else {
			return false; // visitor is going to get a password request--don't cache or that won't happen
		}

		$excludeList = array_merge(explode(",", getOption('static_cache_excludedpages')), array('404.php/', 'password.php/'));
		foreach ($excludeList as $item) {
			$page_to_exclude = explode("/", $item);
			if ($_zp_gallery_page == trim($page_to_exclude[0])) {
				$exclude = trim($page_to_exclude[1]);
				if (empty($exclude) || $title == $exclude) {
					return false;
				}
			}
		}
		return $accessType;
	}
	
	static function hideButton()
	{
?>
<style>form[name="purge_image_cache"] { display: none; }</style>
<?
	}

	/**
	 * Starts the caching: Gets either an already cached file if existing or starts the output buffering.
	 *
	 */
	function startHTMLCache() {
		global $_zp_gallery_page, $_zp_script_timer;
		if ($this->enabled && $accessType = $this->checkIfAllowedPage()) {
			$_zp_script_timer['static cache start'] = microtime();
			$cachefilepath = $this->createCacheFilepath($accessType);
			if (!empty($cachefilepath)) {
				$cachefilepath = SERVERPATH . '/' . STATIC_CACHE_FOLDER . "/" . $cachefilepath;
				if (file_exists($cachefilepath)) {
					$lastmodified = filemtime($cachefilepath);
					// don't use cache if comment is posted or cache has expired
					if (time() - $lastmodified < getOption("static_cache_expire")) {

						//send the headers!
						header('Content-Type: text/html; charset=' . LOCAL_CHARSET);
						header("HTTP/1.0 200 OK");
						header("Status: 200 OK");
						header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');

						echo file_get_contents($cachefilepath);

						// cache statistics
						list($usec, $sec) = explode(' ', $_zp_script_timer['start']);
						$start = (float) $usec + (float) $sec;
						list($usec, $sec) = explode(' ', $_zp_script_timer['static cache start']);
						$start_cache = (float) $usec + (float) $sec;
						list($usec, $sec) = explode(' ', microtime());
						$end = (float) $usec + (float) $sec;
						echo "<!-- " . sprintf(gettext('Cached content of %3$s served by static_html_cache in %1$.4f seconds plus %2$.4f seconds unavoidable Zenphoto overhead.'), $end - $start_cache, $start_cache - $start, date('D, d M Y H:i:s', filemtime($cachefilepath))) . " -->\n";
						exitZP();
					}
				}
				$this->deletestatic_html_cache_mwFile($cachefilepath);
				if (ob_start()) {
					$this->pageCachePath = $cachefilepath;
				}
			}
			unset($_zp_script_timer['static cache start']); // leave it out of the summary page
		}
	}

	/**
	 * Ends the caching: Ends the output buffering  and writes the html cache file from the buffer
	 *
	 * Place this function on zenphoto's root index.php file in the absolute last line
	 *
	 */
	function endHTMLCache() {
		global $_zp_script_timer;
		$cachefilepath = $this->pageCachePath;
		if (!empty($cachefilepath)) {
			$pagecontent = ob_get_contents();
			ob_end_clean();
			if ($this->enabled && $fh = fopen($cachefilepath, "w")) {
				fputs($fh, $pagecontent);
				fclose($fh);
				clearstatcache();
			}
			$this->pageCachePath = NULL;
			echo $pagecontent;
		}
	}

	/**
	 *
	 * Aborts HTML caching
	 * Used for instance, when there is a 404 error or such
	 *
	 */
	function abortHTMLCache() {
		if (!empty($this->pageCachePath)) {
			$this->pageCachePath = NULL;
			ob_end_flush();
		}
	}

	/**
	 * Creates the path and filename of the page to be cached.
	 *
	 * @return string
	 */
	function createCacheFilepath($accessType) {
		global $_zp_current_image, $_zp_current_album, $_zp_gallery_page, $_zp_authority,
		$_zp_current_zenpage_news, $_zp_zenpage, $_zp_current_category, $_zp_current_zenpage_page, $_zp_gallery, $_zp_page, $_zp_current_search;
		// just make sure these are really empty
		$cachefilepath = $_zp_gallery->getCurrentTheme() . '_' . str_replace('zp_', '', $accessType) . '_';
		$album = "";
		$image = "";
		$searchfields = "";
		$words = "";
		$date = "";
		$title = ""; // zenpage support
		$category = ""; // zenpage support
		if (isset($_REQUEST['locale'])) {
			$locale = "_" . sanitize($_REQUEST['locale']);
		} else {
			$locale = "_" . getOption("locale");
		}
		
		$melbourneTimezone = new DateTimeZone('Australia/Melbourne');
        $currentDateTime = new DateTime();
        $currentDateTime->setTimestamp(time());
        $currentDateTime->setTimezone($melbourneTimezone);
		
		switch ($_zp_gallery_page) {
			case 'index.php':
				$cachesubfolder = "index";
				$cachefilepath .= "index";
				$cachefilepath .= '_sortype-' . $_zp_gallery->getSortType();
				$cachefilepath .= '_sortdir-' . $_zp_gallery->getSortDirection();
				$cachefilepath .= $currentDateTime->format('Y-m-d');
				break;
			case 'album.php':
			case 'image.php':
				$cachesubfolder = "albums";
				$album = $_zp_current_album->name;
				if (isset($_zp_current_image)) {
					$cachesubfolder = "images";
					$image = "-" . $_zp_current_image->filename;
				}
				$cachefilepath .= $album . $image;
				if($_zp_gallery_page == 'album.php') {
					$cachefilepath .= '_albsortype-' . $_zp_current_album->getSortType('album');
					$cachefilepath .= '_albsortdir-' . $_zp_current_album->getSortDirection('album');
					$cachefilepath .= '_imgsortype-' . $_zp_current_album->getSortType('image');
					$cachefilepath .= '_imgsortdir-' . $_zp_current_album->getSortDirection('image');
				}
				if (in_context(ZP_SEARCH_LINKED)) {
					$cachefilepath .= '_search_' . stripcslashes($_zp_current_search->codifySearchString());
				} 
				break;
			case 'pages.php':
				$cachesubfolder = "pages";
				$cachefilepath .= 'page-' . $_zp_current_zenpage_page->getName();
				break;
			case 'news.php':
				$cachesubfolder = "news";
				$cachefilepath .= 'news';
				if (is_object($_zp_current_zenpage_news)) {
					$title = "-" . $_zp_current_zenpage_news->getName();
				} 
				if (!is_object($_zp_current_category) && !is_object($_zp_current_zenpage_news)) {
					$cachefilepath .= '_sortype-' . $_zp_zenpage->getSortType();
					$cachefilepath .= '_sortdir-' . $_zp_zenpage->getSortDirection();
				}
				if (is_object($_zp_current_category)) {
					$category = "_cat-" . $_zp_current_category->getName();
					$category .= '_catsortype-' . $_zp_current_category->getSortType();
					$category .= '_catsortdir-' . $_zp_current_category->getSortDirection();
				} 
				$cachefilepath .= $category . $title;
				break;
			case 'archive.php':
				$cachesubfolder = "pages";
				$page = 1;
				if (isset($_GET['page'])) {
    				$page = $_GET['page'];
				}
				$cachefilepath .= 'archive-' . $page;
				break;
			case 'on-this-day.php':
				$cachesubfolder = "pages";
				if (isset($_GET['date'])) {
					$date = sanitize($_GET['date']);
				} else {
    				$date = $currentDateTime->format('Y-m-d');
				}
				$cachefilepath .= stripSuffix($_zp_gallery_page) . '-' . $date;
				break;
			case 'rss-updates.php':
				$cachesubfolder = "rss";
				$page = '-';
				if (isset($_GET['page'])) {
    				$page .= $_GET['page'];
				}
				$cachefilepath .= 'rss-updates' . $page;
				break;
			case 'rss-on-this-day.php':
				$cachesubfolder = "rss";
				$currentHour = $currentDateTime->format('H');
                if ($currentHour < (int)getOption('wongm_rss_hour_threshold'))
                {
                    $oneDay = new DateInterval('P1D');
                    $currentDateTime->sub($oneDay);
                }
				$cachefilepath .= 'rss-on-this-day-' . $currentDateTime->format('Y-m-d');
				break;
			default:
				// custom pages
				$cachesubfolder = "pages";
				$cachefilepath .= 'custom-' . stripSuffix($_zp_gallery_page);
				break;
		}
		$cachefilepath .= "_" . (int) $_zp_page;

		if (getOption('obfuscate_cache')) {
			$cachefilepath = sha1($locale . HASH_SEED . $cachefilepath);
		} else {
			// strip characters that cannot be in file names
			$cachefilepath = str_replace(array('<', '>', ':', '"', '/', '\\', '|', '?', '*'), '_', $cachefilepath) . $locale;
		}
		return $cachesubfolder . "/" . $cachefilepath . '.html';
	}

	/**
	 * Deletes a cache file
	 *
	 * @param string $cachefilepath Path to the cache file to be deleted
	 */
	function deletestatic_html_cache_mwFile($cachefilepath) {
		if (file_exists($cachefilepath)) {
			@chmod($cachefilepath, 0777);
			@unlink($cachefilepath);
		}
	}

	/**
	 * Cleans out the cache folder. (Adpated from the zenphoto image cache)
	 *
	 * @param string $cachefolder the sub-folder to clean
	 */
	static function clearHTMLCache($folder = NULL) {
		if (is_null($folder)) {
			$cachesubfolders = array('index', 'albums', 'images', 'pages', 'news');
			foreach ($cachesubfolders as $cachesubfolder) {
				removeDir(SERVERPATH . '/' . STATIC_CACHE_FOLDER . "/" . $cachesubfolder, true);
			}
		} else {
			removeDir(SERVERPATH . '/' . STATIC_CACHE_FOLDER . "/" . $folder);
		}
	}

	/**
	 * call to disable caching a page
	 */
	static function disable() {
		global $_zp_html_cache;
		$_zp_html_cache->enabled = false;
	}

	

	/**
	 * used to disable cashing when the uri is an image processor uri
	 * @param string $uri
	 * @return string
	 */
	static function _disable($uri) {
		global $_zp_html_cache;
		$_zp_html_cache->disable();
		return $uri;
	}

	/**
	 * Adds the utility  button for cache clearing
	 * 
	 * @since 1.6.1 moved from cacheManager
	 * 
	 * @param array $buttons
	 * @return string
	 */
	static function overviewbutton($buttons) {
		$buttons[] = array(
				'category' => gettext('Cache'),
				'enable' => true,
				'button_text' => gettext('Purge HTML cache'),
				'formname' => 'clearcache_button',
				'action' => FULLWEBPATH . '/' . ZENFOLDER . '/admin.php?action=clear_html_cache',
				'icon' => 'images/edit-delete.png',
				'title' => gettext('Clear the static HTML cache. HTML pages will be re-cached as they are viewed.'),
				'alt' => '',
				'hidden' => '<input type="hidden" name="action" value="clear_html_cache">',
				'rights' => ADMIN_RIGHTS,
				'XSRFTag' => 'ClearHTMLCache'
		);
		return $buttons;
	}
	
	/**
	 *
	 * Clears the html cache for items if published and this is enabled on the options
	 * 
	 * @since 1.6.1 former published() method functionality moved from cacheManager
	 * 
	 * @param object $obj
	 */
	static function clearOnPublish($obj) {
		global $_zp_html_cache;
		if (getOption('static_cache_' . $obj->table)) {
			$_zp_html_cache->clearHTMLCache();
		}
		return $obj;
	}
	
}