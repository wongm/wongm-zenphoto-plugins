<?php
/**
 * Provides automatic hitcounter counting for Zenphoto objects
 * @author Stephen Billard (sbillard)
 * @package plugins
 */
/** Reset hitcounters ********************************************************** */
/* * ***************************************************************************** */
if (!defined('OFFSET_PATH')) {
	define('OFFSET_PATH', 3);
}

$plugin_is_filter = 5 | ADMIN_PLUGIN | FEATURE_PLUGIN;
$plugin_description = gettext('Automatically increments hitcounters on Zenphoto objects viewed by a <em>visitor</em>. Adapted by Marcus Wong to keep track of image hitcount statistics on a weekly, monthly and all time basis.');
$plugin_author = "Stephen Billard (sbillard)";

$option_interface = 'hitcounter_mw';

zp_register_filter('load_theme_script', 'hitcounter_mw::load_script');

/**
 * Plugin option handling class
 *
 */
class hitcounter_mw {

	var $defaultbots = 'Teoma, alexa, froogle, Gigabot,inktomi, looksmart, URL_Spider_SQL, Firefly, NationalDirectory, Ask Jeeves, TECNOSEEK, InfoSeek, WebFindBot, girafabot, crawler, www.galaxy.com, Googlebot, Scooter, Slurp, msnbot, appie, FAST, WebBug, Spade, ZyBorg, rabaz ,Baiduspider, Feedfetcher-Google, TechnoratiSnoop, Rankivabot, Mediapartners-Google, Sogou web spider, WebAlta Crawler';

	function __construct() {
		setOptionDefault('hitcounter_ignoreIPList_enable', 0);
		setOptionDefault('hitcounter_ignoreSearchCrawlers_enable', 0);
		setOptionDefault('hitcounter_ignoreIPList', '');
		setOptionDefault('hitcounter_searchCrawlerList', $this->defaultbots);
	}

	function getOptionsSupported() {
		return array(gettext('IP Address list')		 => array(
										'order'	 => 1,
										'key'		 => 'hitcounter_ignoreIPList',
										'type'	 => OPTION_TYPE_CUSTOM,
										'desc'	 => gettext('Comma-separated list of IP addresses to ignore.'),
						),
						gettext('Filter')							 => array(
										'order'			 => 0,
										'key'				 => 'hitcounter_ignore',
										'type'			 => OPTION_TYPE_CHECKBOX_ARRAY,
										'checkboxes' => array(gettext('IP addresses') => 'hitcounter_ignoreIPList_enable', gettext('Search Crawlers') => 'hitcounter_ignoreSearchCrawlers_enable'),
										'desc'			 => gettext('Check to enable. If a filter is enabled, viewers from in its associated list will not count hits.'),
						),
						gettext('Search Crawler list') => array(
										'order'				 => 2,
										'key'					 => 'hitcounter_searchCrawlerList',
										'type'				 => OPTION_TYPE_TEXTAREA,
										'multilingual' => false,
										'desc'				 => gettext('Comma-separated list of search bot user agent names.'),
						),
						' '														 => array(
										'order'	 => 3,
										'key'		 => 'hitcounter_set_defaults',
										'type'	 => OPTION_TYPE_CUSTOM,
										'desc'	 => gettext('Reset options to their default settings.')
						)
		);
	}

	function handleOption($option, $currentValue) {
		switch ($option) {
			case 'hitcounter_set_defaults':
				?>
				<script type="text/javascript">
					// <!-- <![CDATA[
					var reset = "<?php echo $this->defaultbots; ?>";
					function hitcounter_defaults() {
						$('#hitcounter_ignoreIPList').val('');
						$('#hitcounter_ip_button').removeAttr('disabled');
						$('#hitcounter_ignoreIPList_enable').prop('checked', false);
						$('#hitcounter_ignoreSearchCrawlers_enable').prop('checked', false);

						$('#hitcounter_searchCrawlerList').val(reset);



					}
					// ]]> -->
				</script>
				<label><input id="hitcounter_reset_button" type="button" value="<?php echo gettext('Defaults'); ?>" onclick="hitcounter_defaults();" /></label>
				<?php
				break;
			case 'hitcounter_ignoreIPList':
				?>
				<input type="hidden" name="<?php echo CUSTOM_OPTION_PREFIX; ?>'text-hitcounter_ignoreIPList" value="0" />
				<input type="text" size="30" id="hitcounter_ignoreIPList" name="hitcounter_ignoreIPList" value="<?php echo html_encode($currentValue); ?>" />
				<script type="text/javascript">
					// <!-- <![CDATA[
					function hitcounter_insertIP() {
						if ($('#hitcounter_ignoreIPList').val() == '') {
							$('#hitcounter_ignoreIPList').val('<?php echo getUserIP(); ?>');
						} else {
							$('#hitcounter_ignoreIPList').val($('#hitcounter_ignoreIPList').val() + ',<?php echo getUserIP(); ?>');
						}
						$('#hitcounter_ip_button').attr('disabled', 'disabled');
					}
					jQuery(window).load(function() {
						var current = $('#hitcounter_ignoreIPList').val();
						if (current.indexOf('<?php echo getUserIP(); ?>') < 0) {
							$('#hitcounter_ip_button').removeAttr('disabled');
						}
					});
					// ]]> -->
				</script>
				<label><input id="hitcounter_ip_button" type="button" value="<?php echo gettext('Insert my IP'); ?>" onclick="hitcounter_insertIP();" disabled="disabled" /></label>
				<?php
				break;
		}
	}

	/**
	 *
	 * Counts the hitcounter for the page/object
	 * @param string $script
	 * @param bool $valid will be false if the object is not found (e.g. there will be a 404 error);
	 * @return string
	 */
	static function load_script($script, $valid) {
		if ($script && $valid) {
			if (getOption('hitcounter_ignoreIPList_enable')) {
				$ignoreIPAddressList = explode(',', str_replace(' ', '', getOption('hitcounter_ignoreIPList')));
				$skip = in_array(getUserIP(), $ignoreIPAddressList);
			} else {
				$skip = false;
			}
			if (getOption('hitcounter_ignoreSearchCrawlers_enable') && !$skip && array_key_exists('HTTP_USER_AGENT', $_SERVER) && ($agent = $_SERVER['HTTP_USER_AGENT'])) {
				$botList = explode(',', getOption('hitcounter_searchCrawlerList'));
				foreach ($botList as $bot) {
					if (stripos($agent, trim($bot))) {
						$skip = true;
						break;
					}
				}
			}

			if (!$skip) {
				global $_zp_gallery_page, $_zp_current_album, $_zp_current_image, $_zp_current_zenpage_news, $_zp_current_zenpage_page, $_zp_current_category;
				if (checkAccess()) {
					// count only if permitted to access
					switch ($_zp_gallery_page) {
						case 'album.php':
							if (!$_zp_current_album->isMyItem(ALBUM_RIGHTS) && getCurrentPage() == 1) {
		                        $_zp_current_album->set('hitcounter_week', $_zp_current_album->get('hitcounter_week') + 1);
		                        $_zp_current_album->set('hitcounter_month', $_zp_current_album->get('hitcounter_month') + 1);
								$_zp_current_album->countHit();
							}
							break;
						case 'image.php':
							if (!$_zp_current_album->isMyItem(ALBUM_RIGHTS)) {
								//update hit counter
		                        $_zp_current_image->set('hitcounter_week', $_zp_current_image->get('hitcounter_week') + 1);
		                        $_zp_current_image->set('hitcounter_month', $_zp_current_image->get('hitcounter_month') + 1);
								$_zp_current_image->countHit();
							}
							break;
						case 'pages.php':
							if (!zp_loggedin(ZENPAGE_PAGES_RIGHTS)) {
								$_zp_current_zenpage_page->countHit();
							}
							break;
						case 'news.php':
							if (!zp_loggedin(ZENPAGE_NEWS_RIGHTS)) {
								if (is_NewsArticle()) {
									$_zp_current_zenpage_news->countHit();
								} else if (is_NewsCategory()) {
									$_zp_current_category->countHit();
								}
							}
							break;
						default:
							if (!zp_loggedin()) {
								$page = stripSuffix($_zp_gallery_page);
								setOption('Page-Hitcounter-' . $page, getOption('Page-Hitcounter-' . $page) + 1);
							}
							break;
					}
				}
			}
		}
		return $script;
	}
}

/**
 * returns the hitcounter for the current page or for the object passed
 *
 * @param object $obj the album or page object for which the hitcount is desired
 * @return string
 */
function getHitcounter($obj = NULL) {
	global $_zp_current_album, $_zp_current_image, $_zp_gallery_page, $_zp_current_zenpage_news, $_zp_current_zenpage_page, $_zp_current_category;
	if (is_null($obj)) {
		switch ($_zp_gallery_page) {
			case 'album.php':
				$obj = $_zp_current_album;
				break;
			case 'image.php':
				$obj = $_zp_current_image;
				break;
			case 'pages.php':
				$obj = $_zp_current_zenpage_page;
				break;
			case 'news.php':
				if (in_context(ZP_ZENPAGE_NEWS_CATEGORY)) {
					$obj = $_zp_current_category;
				} else {
					$obj = $_zp_current_zenpage_news;
					if (is_null($obj))
						return 0;
				}
				break;
			case 'search.php':
				return NULL;
			default:
				$page = stripSuffix($_zp_gallery_page);
				return getOption('Page-Hitcounter-' . $page);
		}
	}
	return $obj->getHitcounter();
}

// print string with hitcounter for the current image page
function printRollingHitCounter($obj, $break=false)
{
	$text = getRollingHitcounter($obj);
	
	if ($break and strlen($text) > 0)
	{
		echo "<br/>$text";
	}
	else
	{
		echo $text;
	}
}

// return a string with the required hitcounter text for the current page (this week / this month / all time / etc)
function getRollingHitcounter($obj, $galleryType="", $splitLines=true)
{
	$alltime = $obj->get('hitcounter');
	$month = $obj->get('hitcounter_month');
	$week = $obj->get('hitcounter_week');
	
	// return just a single row, for the overall gallery listing pages
	if ($galleryType == 'this-month') {
		$toreturn = $month;
		$extraText = " this month";
	} else if ($galleryType == 'this-week') {
		$toreturn = $week;
		$extraText = " this week";
	} else if ($galleryType == 'all-time') {
		$toreturn = $alltime;
	}
	
	// for overall gallery listing pages
	if ($toreturn > 0) {
		return "Viewed ".pluralNumberWord($toreturn, 'time').$extraText;
	}
	
	// otherwise build up a massive string
	if ($alltime > 0) {
		$toreturn = "Viewed ".pluralNumberWord($alltime, 'time');
	} else {
		return "";
	}
	
	if ($week > 0) {
		$toreturn .= "<br/>(".pluralNumberWord($week, 'time')." this week";
		
		if ($month > 0) {
			$toreturn .= ", ".pluralNumberWord($month, 'time')." this month)";
		} else {
			$toreturn .= ")";
		}
	} else if ($month > 0) {
		$toreturn .= "<br/>(".pluralNumberWord($month, 'time')." this month)";
	}
	
	// formattting fix for album page, when not in EXIF box
	if (!$splitLines) {
		$toreturn = str_replace('<br/>',' ',$toreturn);
	}
	return $toreturn;
}
?>