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

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;
use Joomla\Uri\UriHelper;

\JLoader::registerAlias('GoogleFonts', 'Jtaldef\\GoogleFonts');
\JLoader::registerAlias('ParseCss', 'Jtaldef\\ParseCss');

/**
 * Helper class
 *
 * @since  1.0.0
 */
class JtaldefHelper
{
	/**
	 * Database table name
	 *
	 * @var   string
	 * @since  1.0.0
	 */
	const JTLSGF_DB_TABLE = '#__jtaldef';

	/**
	 * Path to safe font files
	 *
	 * @var   string
	 * @since  1.0.0
	 */
	const JTLSGF_UPLOAD = 'media/plg_system_jtaldef/index';

	/**
	 * Path to safe font files
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public static $debug;

	/**
	 * Test if value passed is a correctly url
	 *
	 * @param   string  $value  String to test
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isExternalUrl($value)
	{
		$uri            = Uri::getInstance();
		$selfHost       = $uri->toString(array('scheme', 'host', 'port'));
		$allowedSchemes = array('http', 'https');

		// If it is the own URL, we can handle it as relative.
		if (substr($value, 0, strlen($selfHost)) == $selfHost)
		{
			return false;
		}

		$urlParts = UriHelper::parse_url($value);

		if ($urlParts === false || !array_key_exists('scheme', $urlParts))
		{
			return false;
		}

		// Scheme found, check all parts found.
		$urlScheme = (string) $urlParts['scheme'];
		$urlScheme = strtolower($urlScheme);

		if (in_array($urlScheme, $allowedSchemes) == false)
		{
			return false;
		}

		// For the schemes here must be two slashes.
		if (substr($value, strlen($urlScheme), 3) !== '://')
		{
			return false;
		}

		// The best we can do for the rest is make sure that the strings are valid UTF-8
		// and the port is an integer.
		if (array_key_exists('host', $urlParts) && !StringHelper::valid((string) $urlParts['host']))
		{
			return false;
		}

		if (array_key_exists('path', $urlParts) && !StringHelper::valid((string) $urlParts['path']))
		{
			return false;
		}

		if (array_key_exists('query', $urlParts) && !is_int((int) $urlParts['query']))
		{
			return false;
		}

		return true;
	}

	/**
	 * Remove base path
	 *
	 * @param   string  $value  The path to parse
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function removeBasePath($value)
	{
		$value = parse_url($value, PHP_URL_PATH);

		// Remove the base path and the slash at the beginning, for later processing
		$basePath = Uri::base(true);

		if (substr($value, 0, strlen($basePath)) == $basePath)
		{
			$value = ltrim(str_replace($basePath, '', $value), '\\/');
		}

		return $value;
	}

	/**
	 * Get CSS path of localized Google Fonts
	 *
	 * @param   string  $value  The value to be searched for fonts
	 * @param   string  $class  Name of the class to call for handle the download:
	 *                          url   = Google Font URL,
	 *                          path  = internal relative path to css file
	 *                          style = inline stylesheets "<style/>"
	 *
	 * @methode   Jtaldes\GoogleFont  GoogleFont
	 *
	 * @return  array|boolean   Return false if no fonts where set
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public static function getNewFileContent($value, $class)
	{
		$loadClass = $class == 'ParseInline' ? 'ParseCss' : $class;

		if (!class_exists($loadClass))
		{
			throw new \Exception(sprintf("The class '%s' to call for handle the download could not be found.", $class));
		}

		$handler = new $loadClass;

		$newFileContent = $handler->getNewFileContent($value);

		if (!$newFileContent)
		{
			return false;
		}

		switch ($class)
		{
			case 'GoogleFonts':
				$file = self::JTLSGF_UPLOAD . '/css/' . md5($newFileContent) . '.css';
				break;

			case 'ParseCss':
				$file = self::JTLSGF_UPLOAD . '/css/' . str_replace(array('\\', '/'), '-', $value);
				break;

			default:
				return $newFileContent;
				break;
		}

		// TODO If we want to minify the content, lets do it here
		self::saveFile($file, $newFileContent);

		return Uri::base(true) . '/' . $file;
	}

	/**
	 * Search for font-face blocks to parse local relative paths
	 *
	 * @param   string  $path  The path to be scanned
	 * @param   string  $file  Absolute path to the actual file
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function replaceRelativeToAbsolutePath($path, $file)
	{
		$parsedPath = parse_url($path, PHP_URL_PATH);
		$newPath    = realpath(dirname($file) . '/' . trim($parsedPath));
		$regex      = array(
			JPATH_ROOT => '',
			"\\"       => '/',
		);
		$newPath    = ltrim(str_replace(array_keys($regex), $regex, $newPath), '\\/');
		$path       = str_replace($parsedPath, Uri::base(true) . '/' . $newPath, $path);

		return $path;
	}

	/**
	 * Get the download handler
	 *
	 * @param   string  $link  The link to be scanned
	 *
	 * @return  string|boolean  False if no match
	 *
	 * @since   1.0.0
	 */
	public static function getDownloadHandler($link)
	{
		if (strpos($link, 'fonts.googleapis.com') !== false)
		{
			return 'GoogleFonts';
		}

		return false;
	}

	/**
	 * Remove comments, unneeded spaces and empty lines
	 *
	 * @param   string  $content  Content to clean
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function cleanContent($content)
	{
		if (empty($content))
		{
			return '';
		}

		// Regex to remove clean content
		$regex = array(
			"`^([\t\s]+)`ism"                       => '',
			"`^\/\*(.+?)\*\/`ism"                   => "",
			"`([\n\A;]+)\/\*(.+?)\*\/`ism"          => "$1",
			"`([\n\A;\s]+)//(.+?)[\n\r]`ism"        => "$1\n",
			"`(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+`ism" => "\n",
		);

		$content = preg_replace(array_keys($regex), $regex, $content);

		return $content;
	}

	/**
	 * Save file
	 *
	 * @param   string  $file    Relative path of the file to save the buffer.
	 * @param   string  $buffer  The content to save
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public static function saveFile($file, $buffer)
	{
		if (!file_exists(JPATH_ROOT . '/' . $file))
		{
			if (false === File::write(JPATH_ROOT . '/' . $file, $buffer))
			{
				throw new \Exception(sprintf('Could not write the file: %s', JPATH_ROOT . $file));
			}
		}
	}
}