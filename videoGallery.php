<?php
/**
 * @version		3.0.1
 * @package		Simple Video Gallery (plugin)
 * @author    	Avalon Hosting Services Ltd. - https://avalonhosting.services
 * @copyright	Copyright (c) 2017 - Avalon Hosting Services Ltd. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');


/**
* 
*/
class plgContentVideoGallery extends JPlugin
{
	
	// SVG reference parameters
	var $plg_name					= "videoGallery";
	var $plg_tag					= "videoGallery";

	function plgContentVideoGallery( &$subject, $params ){
		parent::__construct( $subject, $params );

		// Define the DS constant under Joomla! 3.0+
		if (!defined('DS')){
			define('DS', DIRECTORY_SEPARATOR);
		}
	}


	// Joomla! 2.5+
	function onContentPrepare($context, &$row, &$params, $page = 0){
		$this->renderVideoGallery($row, $params, $page = 0);
	}

	function renderVideoGallery($row, $params, $page = 0)
	{
		// API
		jimport('joomla.filesystem.file');
		$mainframe = JFactory::getApplication();
		$document  = JFactory::getDocument();

		// Assign paths
		$sitePath = JPATH_SITE;
		$siteUrl  = JURI::root(true);

		// Check if plugin is enabled
		if (JPluginHelper::isEnabled('content', $this->plg_name) == false) return;

		// Bail out if the page format is not what we want
		$allowedFormats = array('', 'html', 'feed', 'json');
		if (!in_array(JRequest::getCmd('format'), $allowedFormats)) return;

		// Simple performance check to determine whether plugin should process further
		if (JString::strpos($row->text, $this->plg_tag) === false) return;

		// expression to search for
		$regex = "#{".$this->plg_tag."}(.*?){/".$this->plg_tag."}#is";

		// Find all instances of the plugin and put them in $matches
		preg_match_all($regex, $row->text, $matches);

		// Number of plugins
		$count = count($matches[0]);

		// Plugin only processes if there are any instances of the plugin in the text
		if (!$count) return;

		// Load the plugin language file the proper way
		JPlugin::loadLanguage('plg_content_'.$this->plg_name, JPATH_ADMINISTRATOR);

		// Check for basic requirements
		if (!extension_loaded('gd') && !function_exists('gd_info')){
			JError::raiseNotice('', JText::_('JW_PLG_SVG_NOTICE_01'));
			return;
		}

		if (!is_writable($sitePath.DS.'cache')){
			JError::raiseNotice('', JText::_('JW_PLG_SVG_NOTICE_02'));
			return;
		}

		

		// ----------------------------------- Get plugin parameters -----------------------------------

		// Get plugin info
		$plugin = JPluginHelper::getPlugin('content', $this->plg_name);

		// ----------------------------------- Prepare the output -----------------------------------

		// Process plugin tags
		if (preg_match_all($regex, $row->text, $matches, PREG_PATTERN_ORDER) > 0){

			// start the replace loop
			foreach ($matches[0] as $key => $match){

				$tagcontent = preg_replace("/{.+?}/", "", $match);
				

				if(strpos($tagcontent,':')!==false){
					$tagparams 			= explode(':',$tagcontent);
					$galleryFolder 	= $tagparams[0];
				} else {
					$galleryFolder 	= $tagcontent;
				}

				// HTML & CSS assignments
				$srcVideoFolder = $galleries_rootfolder.'/'.$galleryFolder;
				$gal_id = substr(md5($key.$srcVideoFolder), 1, 10);

				
				// API
				jimport('joomla.filesystem.folder');

				// Path assignment
				$sitePath = JPATH_SITE.'/';
				if(JRequest::getCmd('format')=='feed')
				{
					$siteUrl = JURI::root(true).'';
				}
				else
				{
					$siteUrl = JURI::root(true).'/';
				}

				// Internal parameters
				$prefix = "videoGallery_cache_";

				// Set the cache folder
				$cacheFolderPath = JPATH_SITE.DS.'cache'.DS.'videoGallery';
				if (file_exists($cacheFolderPath) && is_dir($cacheFolderPath))
				{
					// all OK
				}
				else
				{
					mkdir($cacheFolderPath);
				}

				// Check if the source folder exists and read it
				$srcFolder = JFolder::files($sitePath.$srcVideoFolder);

				// Proceed if the folder is OK or fail silently
				if (!$srcFolder)
					return;

				// Loop through the source folder for video
				$fileTypes = array('flv', 'swf', 'mp4', 'wmv', 'mp3', '3gp');
				$imgFileTypes = array('jpg', 'jpeg', 'gif', 'png');

				// Create an array of file types
				$found = array();
				$foundImg = array();

				// Create an array for matching files
				foreach ($srcFolder as $srcVideo)
				{
					$fileInfo = pathinfo($srcVideo);

					

					if (array_key_exists('extension', $fileInfo) && in_array(strtolower($fileInfo['extension']), $fileTypes))
					{
						$found[] = $srcVideo;
					}

					if (array_key_exists('extension', $fileInfo) && in_array(strtolower($fileInfo['extension']), $imgFileTypes))
					{
						$foundImg[] = $srcVideo;
					}

				}

				// Bail out if there are no video found
				if (count($found) == 0)
					return;
				if (count($foundImg) == 0)
					return;

				// Sort array
				sort($found);
				sort($foundImg);

				// Initiate array to hold gallery
				$gallery = array();
				$imgGallery = array();

				
				$plg_html = '<div class="html5gallery" data-skin="light" data-width="480" data-height="270" style="display: none;">';

				// Loop through the image file list
				
				foreach (array_combine($found, $foundImg) as $founds => $foundImgs)
				{
				
					// Object to hold each image elements
					$gallery[$foundImgs] = new JObject;
					$imgGallery[$founds] = new JObject;

					// Assign source image and path to a variable
					$original = $sitePath.str_replace('/', DS, $srcVideoFolder).DS.$filename;

					// Assemble the image elements
					$gallery[$foundImgs]->filename = $founds;
					$imgGallery[$founds]->filename = $foundImgs;

					$gallery[$foundImgs]->sourceVideoFilePath = $siteUrl.$srcVideoFolder.'/'.self::replaceWhiteSpace($founds);

					$imgGallery[$founds]->sourceVideoFilePath = $siteUrl.$srcVideoFolder.'/'.self::replaceWhiteSpace($foundImgs);
					
					
					// Output

					$plg_html .= '<a href="'. JURI::root(true).str_replace('//','/',$gallery[$foundImgs]->sourceVideoFilePath).'"><img src="'. JURI::root(true).str_replace('//','/',$imgGallery[$founds]->sourceVideoFilePath).'" alt="Big Buck Bunny, Copyright Blender Foundation"></a>';

				}// foreach loop

				// OUTPUT render gallery end
				
				if (!$gallery){
					JError::raiseNotice('', JText::_('JW_PLG_SIG_NOTICE_03').' '.$srcVideoFolder);
					continue;
				}

				// CSS & JS includes: Append head includes, but not when we're outputing raw content (like in K2)
				if (JRequest::getCmd('format') == '' || JRequest::getCmd('format') == 'html'){

					// Initiate variables
					$relName = '';
					$extraClass = '';
					$extraWrapperClass = '';
					$legacyHeadIncludes = '';
					$customLinkAttributes = '';

					JHtml::_('jquery.framework');
					
					$document->addScript($siteUrl.'plugins/content/videoGallery/html5gallery.js');
					

					if ($extraClass)
						$extraClass = ' '.$extraClass;

					if ($extraWrapperClass)
						$extraWrapperClass = ' '.$extraWrapperClass;

					if ($customLinkAttributes)
						$customLinkAttributes = ' '.$customLinkAttributes;
				
				} else {
					$itemPrintURL = false;
				}

				// Fetch the template	
					
				$plg_html .= '</div>';
				

				// Do the replace
				$row->text = preg_replace("#{".$this->plg_tag."}".$tagcontent."{/".$this->plg_tag."}#s", $plg_html, $row->text);

			}// end foreach

			
		} // end if
		
	} // close renderVideoGallery main function

	// Replace white space
	public static function replaceWhiteSpace($text_to_parse)
	{
		$source_html = array(" ");
		$replacement_html = array("%20");
		return str_replace($source_html, $replacement_html, $text_to_parse);
	}

} //close class