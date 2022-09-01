<?php
/**
 * Automatic local download external files
 *
 * @package     Joomla.Plugin
 * @subpackage  System.Jtaldef
 *
 * @author      Guido De Gobbis <support@joomtools.de>
 * @copyright   (c) 2020 JoomTools.de - All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Jtaldef\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Jtaldef\Jtaldef;
use Jtaldef\Helper\JtaldefHelper;

/**
 * Download and save Fontawsome
 *
 * @since  __DEPLOY_VERSION__
 */
class FontAwesome extends Jtaldef
{
	/**
	 * Name of the Service
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	const NAME = 'Font Awesome';

	/**
	 * Trigger to parse <script/> tags
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	const PARSE_SCRIPTS = true;

	/**
	 * List of URL's to trigger the service
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	const URLS_TO_TRIGGER = array(
		'pro.fontawesome.com',
		'use.fontawesome.com',
	);

	/**
	 * Namespaces to remove item from DOM if not parsed.
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	const NS_TO_REMOVE_NOT_PARSED_ITEMS_FROM_DOM = array(
		"//*[contains(@href,'fontawesome.com') or contains(@src,'fontawesome.com')]",
	);

	/**
	 * Base URL to download fonts data
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private $downloadBaseUrl;

	/**
	 * Version of the Font
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private $fontVersion;

	/**
	 * The downloaded content for the font file (css or js)
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private $fontContent;

	/**
	 * List of font names to download
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	private $fontNames = array();

	/**
	 * Description
	 *
	 * @param   string  $link  Link to download the fonts
	 *
	 * @return  string|boolean  False if no font info is set in the query else the local path to the css file
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNewFileContentLink($link)
	{
		$link = trim(InputFilter::getInstance()->clean($link));

		// Parse the URL
		$parsedUrl             = parse_url($link);
		$path                  = explode('/', trim($parsedUrl['path'], '\\/'));
		$this->fontVersion     = $path[1];
		$this->downloadBaseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/' . $path[0];

		if (!$this->fontContent = $this->downloadContent($path[3]))
		{
			return false;
		}

		$this->parseCssForFontNames();

		if (!$this->downloadFonts())
		{
			return false;
		}

		$filename = 'fontawesome-' . $this->fontVersion . '-' . $path[3];

		return JtaldefHelper::saveFile($filename, $this->fontContent);
	}

	/**
	 * Parse CSS to get the font names to download.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function parseCssForFontNames()
	{
		$fontNames = array();
		$css       = $this->fontContent;
		$pattern1  = '%@font\-face\s?{(.*)}%Uu';
		$pattern2  = '%url\((.*)\)\s|;?%Uu';

		if (preg_match_all($pattern1, $css, $matches))
		{
			foreach ($matches[1] as $match)
			{
				$array = explode(';', $match);

				foreach ($array as $value)
				{
					if (strpos($value, 'src') === false)
					{
						continue;
					}

					if (preg_match_all($pattern2, $value, $fontPaths))
					{
						$fontPaths = array_filter($fontPaths[1]);

						foreach ($fontPaths as $fontPath)
						{
							$fontPath    = explode('/', $fontPath);
							$fontNames[] = array_pop($fontPath);
						}
					}
				}
			}

			$this->fontNames = ArrayHelper::arrayUnique($fontNames);
		}
	}

	/**
	 * Download the Fontawesome CSS to local filesystem
	 *
	 * @param   string  $filename  The CSS name for download the CSS.
	 *
	 * @return  string|bool
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function downloadContent($filename)
	{
		$fileExt = pathinfo($filename, PATHINFO_EXTENSION);

		// Define the URL to download the CSS file
		$url = $this->downloadBaseUrl . '/' . $this->fontVersion . '/' . $fileExt . '/' . $filename;

		// Download the CSS file
		$response   = JtaldefHelper::getHttpResponseData($url);
		$statusCode = $response->code;
		$content    = $response->body;

		if ($statusCode < 200 || $statusCode >= 400 || empty($content))
		{
			if (JtaldefHelper::$debug)
			{
				Factory::getApplication()
					->enqueueMessage(
						Text::sprintf(
							'PLG_SYSTEM_JTALDEF_ERROR_FONT_NOT_FOUND',
							'Fontawesome',
							$url,
							$content
						),
						'error'
					);
			}

			return false;
		}

		return $content;
	}

	/**
	 * Download Fontawesome to local filesystem
	 *
	 * @return  string|bool
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function downloadFonts()
	{
		$search  = array();
		$replace = array();
		$urlBase = $this->downloadBaseUrl . '/' . $this->fontVersion . '/webfonts';

		foreach ($this->fontNames as $fontName)
		{
			$filename = 'fontawesome-' . $this->fontVersion . '-' . $fontName;
			$filePath = JtaldefHelper::getCacheFilePath($filename);

			if (!file_exists(JPATH_ROOT . '/' . $filePath))
			{
				$response = JtaldefHelper::getHttpResponseData($urlBase . '/' . $fontName);
				$statusCode = $response->code;
				$content    = $response->body;


				if ($statusCode < 200 || $statusCode >= 400 || empty($content))
				{
					if (JtaldefHelper::$debug)
					{
						Factory::getApplication()
							->enqueueMessage(
								Text::sprintf(
									'PLG_SYSTEM_JTALDEF_ERROR_WHILE_DOWNLOADING_FONT',
									'Fontawesome',
									$urlBase . '/' . $fontName
								),
								'error'
							);
					}

					return false;
				}

				$filePath = JtaldefHelper::saveFile($filename, $content);

				$search[] = '../webfonts/' .$fontName;
				$replace[] = Uri::base(true) . '/' . $filePath;
			}
		}

		$this->fontContent = str_replace($search, $replace, $this->fontContent);

		return true;
	}
}