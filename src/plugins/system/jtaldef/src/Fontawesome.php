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

namespace Jtaldef;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

/**
 * Download and save Fontawsome
 *
 * @since  __DEPLOY_VERSION__
 */
class Fontawesome
{
	/**
	 * Base URL to download fonts data
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	const FA_BASE_URL = 'https://use.fontawesome.com/releases';

	/**
	 * Namespaces to remove if not parsed.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	const REMOVE_NOT_PARSED_FROM_HEAD_NS = "//head//*[contains(@href,'fontawesome')]|//head//*[contains(@href,'font-awesome')]";

	/**
	 * Version of the Font
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private $fontVersion;

	/**
	 * The downloaded CSS for the font
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private $fontCss;

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
	 * @return  bool|string  False if no font info is set in the query else the local path to the css file
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNewFileContent($link)
	{
		$css   = array();
		$link = trim(InputFilter::getInstance()->clean($link));

		// Parse the URL
		$parsedUrl = parse_url($link, PHP_URL_PATH);
		$path      = explode('/', trim($parsedUrl, '\\/'));

		$this->fontVersion = $path[1];

		if (!$this->fontCss = $this->downloadCss($path[3]))
		{
			return false;
		}

		$this->parseCssForFontNames();

		if (!$this->downloadFonts())
		{
			return false;
		}

		return $this->fontCss;
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
		$css       = $this->fontCss;
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
	 * @param   string  $cssName  The CSS name for download the CSS.
	 *
	 * @return  string|bool
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function downloadCss($cssName)
	{
		// Define the URL to download the CSS file
		$url = self::FA_BASE_URL . '/' . $this->fontVersion . '/css/' . $cssName;

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
		$urlBase = self::FA_BASE_URL . '/' . $this->fontVersion . '/webfonts';

		foreach ($this->fontNames as $fontName)
		{
			$filePath = JtaldefHelper::JTALDEF_UPLOAD
				. '/fonts/fontawesome-'
				. $this->fontVersion
				. '-' . $fontName;

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

				JtaldefHelper::saveFile($filePath, $content);

				$search[] = '../webfonts/' .$fontName;
				$replace[] = Uri::base(true) . '/' . $filePath;
			}
		}

		$this->fontCss = str_replace($search, $replace, $this->fontCss);

		return true;
	}
}