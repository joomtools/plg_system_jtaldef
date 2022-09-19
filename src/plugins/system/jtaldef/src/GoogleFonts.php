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
 * Download and save Google Fonts
 *
 * @since  1.0.0
 */
class GoogleFonts
{
	/**
	 * URL to fonts API
	 *
	 * @var    string
	 * @since  1.0.7
	 */
	const GF_DATA_API = 'https://google-webfonts-helper.herokuapp.com/api/fonts';

	/**
	 * Namespaces to remove if not parsed.
	 *
	 * @var    string
	 * @since  1.0.4
	 */
	const REMOVE_NOT_PARSED_FROM_HEAD_NS = "//head//*[contains(@href,'fonts.gstatic.com')]|//head//*[contains(@href,'fonts.googleapis.com')]";

	/**
	 * All the Google Fonts data for the font
	 *
	 * @var    array
	 * @since  1.0.7
	 */
	private static $googleFontsJson = array();

	/**
	 * Font name of the Google Font
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $name;

	/**
	 * Subsets of the Google Font
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $fontsSubsets;

	/**
	 * Value of font-display for the Google Font
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $fontsDisplay;

	/**
	 * Font data collected from API - via JSON for this font
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $fontData;

	/**
	 * Description
	 *
	 * @param   string  $link  Link to download the fonts
	 *
	 * @return  boolean|string  False if no font info is set in the query else the local path to the css file
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function getNewFileContent($link)
	{
		$css   = array();
		$link = trim(InputFilter::getInstance()->clean($link));
		$fonts = $this->getFontInfoByQuery($link);

		if (!$fonts)
		{
			return false;
		}

		$this->fontsSubsets = $fonts['subsets'];
		$this->fontsDisplay = !empty($fonts['display']) ? $fonts['display'] : null;

		foreach ($fonts['families'] as $font)
		{
			$this->name      = $font['name'];
			$this->variants  = $font['variants'];
			$this->fontData = $this->getGoogleFontsJson();

			// Generate the CSS for this family
			$css = array_merge(
				$css,
				$this->generateCss()
			);
		}

		return implode(PHP_EOL, $css);
	}

	/**
	 * Get fonts credentials
	 *
	 * @param   string  $url  Url to parse
	 *
	 * @return  array|boolean  Return false if no query is set
	 *
	 * @since   1.0.0
	 */
	private function getFontInfoByQuery($url)
	{
		// Decode html entities like &amp; and encoded URL
		$url = urldecode($url);

		// Protocol relative fails with parse_url
		if (substr($url, 0, 2) == '//')
		{
			$url = 'https:' . $url;
		}

		// Parse URL to determine families and subsets
		$query = parse_url($url, PHP_URL_QUERY);

		return !empty($query) ? $this->parseFontsQuery($query) : false;
	}

	/**
	 * Parses a Google Fonts query string and returns an array
	 * of families and subsets used.
	 *
	 * NOTE: Data must NOT be urlencoded.
	 *
	 * @param   string  $query  The query string to scan
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function parseFontsQuery($query)
	{
		$return = array();
		$parsed = array();

		$_parsed = explode('&', $query);

		foreach ($_parsed as $var)
		{
			list($key, $value) = explode('=', $var);

			$key = trim($key);
			$value = trim($value);

			if ($key == 'family')
			{
				if (false === strpos($value, '|'))
				{
					$parsed[$key][] = $value;
					$parsed[$key]   = array_filter($parsed[$key]);

					continue;
				}

				$value = explode('|', $value);

				$parsed[$key] = array_filter(array_map('trim', $value));

				continue;
			}

			$parsed[$key] = $value;
		}

		$families = $parsed['family'];

		// Define 'latin' and 'latin-ext' as the default subsets, if there is not set by URL
		$subsets = array('latin', 'latin-ext');

		if (!empty($parsed['subset']))
		{
			$subsets = explode(',', $parsed['subset']);
		}

		if (!empty($parsed['display']))
		{
			$return['display'] = $parsed['display'];
		}

		// Parse variants/weights and font names
		foreach ($families as $k => $font)
		{
			$variants  = array();
			$fontQuery = explode(':', $font);
			$fontName  = trim($fontQuery[0]);

			if (empty($fontQuery[1]))
			{
				$variants = array(
					'100', '100i',
					'200', '200i',
					'300', '300i',
					'400', '400i',
					'500', '500i',
					'600', '600i',
					'700', '700i',
					'800', '800i',
					'900', '900i',
				);
			}

			if (empty($variants))
			{
				$variants = explode(',', $fontQuery[1]);

				if (false !== strpos($fontQuery[1], '@'))
				{
					list($styleTypes, $variants) = explode('@', $fontQuery[1]);

					$styleTypes = array_reverse(explode(',', $styleTypes));
					$_variants  = explode(';', $variants);

					$variants = array();

					foreach ($_variants as $variant)
					{
						if (false !== strpos($variant, ','))
						{
							list($key, $value) = explode(',', $variant);

							$type = '';

							if ($styleTypes[$key] == 'ital')
							{
								$type = 'i';
							}

							$variants[] = $value . $type;

							continue;
						}

						$variants[] = $variant;
					}

				}
			}

			$families[$k] = array(
				'name'     => $fontName,
				'variants' => array_map('strtolower', $variants),
			);

			// Third chunk - probably a subset here
			if (!empty($fontQuery[2]))
			{
				// Split and trim
				$fontSubs = array_map('trim', explode(',', $fontQuery[2]));

				// Add it to the subsets array
				$subsets = array_merge($subsets, $fontSubs);
			}
		}

		// Remove duplicates
		$subsets = array_unique($subsets);

		// At least one subset is required
		if (empty($subsets))
		{
			$subsets = array('latin');
		}

		$return['families'] = $families;
		$return['subsets']  = $subsets;

		return $return;
	}

	/**
	 * Load and return the Google Fonts data from google-webfonts-helper.herokuapp.com
	 *
	 * @param   array $font
	 *
	 * @return  array
	 *
	 * @since   1.0.7
	 */
	private function getGoogleFontsJson()
	{
		$fontId     = strtolower(str_replace(' ', '-', $this->name));
		$storeId    = $fontId . '_' . implode('_', $this->fontsSubsets);
		$subsetsUrl = implode(',', $this->fontsSubsets);

		if (empty(self::$googleFontsJson[$storeId]))
		{
			$cacheFile = JtaldefHelper::JTALDEF_UPLOAD . '/json/' . $storeId . '.json';

			if (file_exists(JPATH_ROOT . '/' . $cacheFile))
			{
				$content = file_get_contents($cacheFile);
			}
			else
			{
				$fontApiUrl = self::GF_DATA_API . '/' . $fontId . '?subsets=' . $subsetsUrl;
				$response   = JtaldefHelper::getHttpResponseData($fontApiUrl);
				$statusCode = $response->code;
				$content    = $response->body;

				if ($statusCode != 200 || empty($content))
				{
					if (JtaldefHelper::$debug)
					{
						Factory::getApplication()
							->enqueueMessage(
								Text::sprintf(
									'PLG_SYSTEM_JTALDEF_ERROR_FONT_NOT_FOUND',
									$this->name . '(' . $subsetsUrl . ')',
									$fontApiUrl,
									$content
								),
								'error'
							);
					}

					return array();
				}

				JtaldefHelper::saveFile($cacheFile, $content);
			}

			$result = json_decode($content, true);
			$result = ArrayHelper::pivot($result['variants'], 'id');

			if (isset($result['regular']))
			{
				$newKey = $result['regular']['fontWeight'];
				$result[$newKey] = $result['regular'];
			}

			if (isset($result['italic']))
			{
				$newKey = $result['italic']['fontWeight'] . $result['italic']['fontStyle'];
				$result[$newKey] = $result['italic'];
			}

			self::$googleFontsJson[$storeId] = $result;
		}

		return self::$googleFontsJson[$storeId];
	}

	/**
	 * Generate CSS based on variants for the subsets
	 *
	 * @return  array
	 * @throws  \Exception
	 *
	 * @since   1.0.7
	 */
	private function generateCss()
	{
		$css   = array();

		foreach ($this->variants as $variant)
		{
			// Normalize variant identifier
			$variant = $this->normalizeVariantId($variant);

			// Variant doesn't exist?
			if (empty($this->fontData[$variant]))
			{
				continue;
			}

			// Font data (from JSON)
			$data = $this->fontData[$variant];

			$data['woff2'] = $this->downloadFile($data['woff2']);
			$data['woff']  = $this->downloadFile($data['woff']);

			// Return an error message if the fonts could not be downloaded
			if (!$data['woff2'] || !$data['woff'])
			{
				if (JtaldefHelper::$debug)
				{
					Factory::getApplication()
						->enqueueMessage(
							Text::sprintf(
								'PLG_SYSTEM_JTALDEF_ERROR_WHILE_DOWNLOADING_FONT',
								$this->name,
								$variant
							),
							'error'
						);
				}

				continue;
			}

			// Common CSS rules to create
			$rules = array(
				'font-family: ' . $data['fontFamily'],
				'font-weight: ' . (int) $data['fontWeight'],
				'font-style: ' . $data['fontStyle'],
			);

			// Build src array
			$src = array();

			$src[] = "url(" . $data['woff2'] . ") format('woff2')";
			$src[] = "url(" . $data['woff'] . ") format('woff')";

			// Add to rules array
			$rules[] = 'src: ' . implode(', ', $src);

			// Have a font-display setting (come soon)?
			if (!empty($this->fontsDisplay))
			{
				$rules[] = 'font-display: ' . $this->fontsDisplay;
			}

			// Add some formatting
			$rules = array_map(
				function ($rule) {
					return "\t" . $rule . ";";
				}, $rules
			);

			// Add to final CSS
			$css[] = "@font-face {\n" . implode("\n", $rules) . "\n}";
		}

		if (!empty($css))
		{
			array_unshift($css,'/* ' . $this->name . ' (' . implode(',', $this->fontsSubsets) . ') */');
		}

		return $css;
	}

	/**
	 * Normalize variant identifier
	 *
	 * @param   string  $variant  The variant identifier to normalize
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function normalizeVariantId($variant)
	{
		$variant = trim($variant);

		// Google API supports bold and b as variants too
		if (false !== stripos($variant, 'b'))
		{
			$variant = str_replace(array('bold', 'b'), '700', $variant);
		}

		// Normalize regular
		$variant = str_replace('regular', '400', $variant);

		// Remove italics in variant
		if (false !== strpos($variant, 'i'))
		{
			// Normalize italic variant
			$variant = preg_replace('/(italics|i)$/i', 'italic', $variant);

			// Italic alone isn't recognized
			if ($variant == 'italic')
			{
				$variant = '400italic';
			}
		}

		// Fallback to 400
		if (!$variant || (!strstr($variant, 'italic') && !is_numeric($variant)))
		{
			$variant = '400';
		}

		return $variant;
	}

	/**
	 * Download google fonts to local filesystem
	 *
	 * @param   string  $url  Url for download the font
	 *
	 * @return  string|boolean
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function downloadFile($url)
	{
		// Setup the file name
		$safeFileName = File::makeSafe(basename($url));
		$filePath     = JtaldefHelper::JTALDEF_UPLOAD . '/fonts/' . $safeFileName;

		if (!file_exists(JPATH_ROOT . '/' . $filePath))
		{
			$response = JtaldefHelper::getHttpResponseData($url);
			$statusCode = $response->code;
			$content    = $response->body;


			if ($statusCode < 200 || $statusCode >= 400 || empty($content))
			{
				return false;
			}

			JtaldefHelper::saveFile($filePath, $content);
		}

		return Uri::base(true) . '/' . $filePath;
	}
}