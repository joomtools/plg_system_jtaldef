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
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Download and save Google Fonts
 *
 * @since  1.0.0
 */
class GoogleFonts
{
	/**
	 * All the Google Fonts data
	 *
	 * @var    array
	 * @since  1.0.0
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

		$googleFontsJson = $this->getGoogleFontsJson();

		foreach ($fonts['families'] as $font)
		{
			$this->name     = $font['name'];
			$this->fontData = $googleFontsJson['fonts'][$this->name];

			// Generate the CSS for this family
			$css = array_merge(
				$css,
				$this->generateCss($font['variants'])
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

					continue;
				}

				$value = explode('|', $value);

				$parsed[$key] = array_map('trim', $value);

				continue;
			}

			$parsed[$key] = $value;
		}

		$families = $parsed['family'];
		$subsets  = array();

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
				$variants = array(400);
			}

			if (empty($variants))
			{
				$variants = explode(',', $fontQuery[1]);

				if (false !== strpos($fontQuery[1], '@'))
				{
					list($styleTypes, $variants) = explode('@', $fontQuery[1]);

					$styleTypes = explode(',', $styleTypes);
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
	 * Load and return the Google Fonts data
	 *
	 * @return  array
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function getGoogleFontsJson()
	{
		if (empty(self::$googleFontsJson))
		{
			$jsonFile = dirname(__FILE__) . '/data/google-fonts-src.json';

			if (false === file_exists($jsonFile))
			{
				throw new \Exception(sprintf('File not found: %s', $jsonFile));
			}

			$json = json_decode(
				file_get_contents($jsonFile),
				true
			);

			self::$googleFontsJson = $json;
		}

		return self::$googleFontsJson;
	}

	/**
	 * Generate CSS based on variants and subsets
	 *
	 * @param   array  $variants  The variants to load for the Font
	 *
	 * @return  array
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function generateCss($variants)
	{
		$css = array();

		foreach ($this->fontsSubsets as $subset)
		{
			foreach ($variants as $variant)
			{
				$italic = false;

				// Normalize variant identifier
				$variant = $this->normalizeVariantId($variant);

				if (false !== strpos($variant, 'i'))
				{
					$italic = true;
				}

				// Variant doesn't exist?
				if (empty($this->fontData[$subset][$variant]))
				{
					continue;
				}

				// Font data (from JSON)
				$data = $this->fontData[$subset][$variant];

				$data['fontFile']     = $this->downloadFile($data['fontFile']);
				$data['fontFileWoff'] = $this->downloadFile($data['fontFileWoff']);

				// Return an error message if the fonts could not be downloaded
				if (!$data['fontFile'] || !$data['fontFileWoff'])
				{
					if (JtaldefHelper::$debug)
					{
						Factory::getApplication()
							->enqueueMessage(
								Text::sprintf('PLG_SYSTEM_JTALDEF_ERROR_DOWNLOAD_GFONT', $this->name),
								'error'
							);
					}
				}

				// Common CSS rules to create
				$rules = array(
					'font-family: "' . $this->name . '"',
					'font-weight: ' . intval($variant),
					'font-style: ' . ($italic ? 'italic' : 'normal'),
				);

				/**
				 * Build src array with localNames first and woff/woff2 next
				 */
				$src = array();

				// Add local names.
				foreach ((array) $data['localNames'] as $local)
				{
					$src[] = "local('{$local}')";
				}

				// Have a font-display setting (come soon)?
				if (!empty($this->fontsDisplay))
				{
					$rules[] = 'font-display: ' . $this->fontsDisplay;
				}

				$src[] = 'url(' . $data['fontFile'] . ") format('woff2')";
				$src[] = 'url(' . $data['fontFileWoff'] . ") format('woff')";

				// Add to rules array
				$rules[] = 'src: ' . implode(', ', $src);

				if (($range = $this->getUnicodeRange($subset)))
				{
					$rules[] = 'unicode-range: ' . $range;
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
		$name = File::makeSafe(basename($url));
		$file = JtaldefHelper::JTLSGF_UPLOAD . '/fonts/' . $name;

		if (!file_exists(JPATH_ROOT . '/' . $file))
		{
			$options = array(
				'userAgent' => 'JT-Easylink Joomla Plugin!',
				'sslverify' => false,
			);

			$options = new Registry($options);
			$http    = HttpFactory::getHttp($options);
			$data    = $http->get($url);

			if ($data->code < 200 || $data->code >= 400)
			{
				return false;
			}

			JtaldefHelper::saveFile($file, $data->body);
		}

		return Uri::base(true) . '/' . $file;
	}

	/**
	 * Get unicode range for this font
	 *
	 * @param   string  $subset  The subset name for the ranges value to return.
	 *
	 * @return  string|boolean
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function getUnicodeRange($subset)
	{
		$ranges = $this->getGoogleFontsJson()['ranges'];

		if (isset($ranges[$subset]))
		{
			return $ranges[$subset];
		}

		return false;
	}
}