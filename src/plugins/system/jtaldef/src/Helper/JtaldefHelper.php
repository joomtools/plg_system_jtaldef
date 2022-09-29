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

namespace Jtaldef\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use Joomla\Uri\UriHelper;


/**
 * Helper class
 *
 * @since  1.0.0
 */
class JtaldefHelper
{
	/**
	 * Base path to safe downloaded files
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	const JTALDEF_UPLOAD = 'media/plg_system_jtaldef/index';

	/**
	 * Namespace to services
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	const NS_TO_SERVICE = 'Jtaldef\\Service\\';

	/**
	 * List of service names
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	public static $serviceToParse = array();

	/**
	 * List of trigger for the aktiv services
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	public static $serviceTriggerList = array();

	/**
	 * State if debug mode is on
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public static $debug;

	/**
	 * Test if the URL passed is an internal or an external URL.
	 *
	 * @param   string  $url  URL to test
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isExternalUrl($url)
	{
		$siteUrl  = Uri::getInstance();
		$selfHost = $siteUrl->toString(array('scheme', 'host', 'port'));

		// If it is the own URL, we can handle it as relative.
		if (substr($url, 0, strlen($selfHost)) == $selfHost)
		{
			return false;
		}

		$urlParts = UriHelper::parse_url($url);

		if ($urlParts === false || !array_key_exists('scheme', $urlParts))
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
	 * Set the list of trigger for the aktiv services.
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function setServiceTriggerList()
	{
		$ns          = self::NS_TO_SERVICE;
		$triggerList = self::$serviceTriggerList;
		$serviceList = self::$serviceToParse;

		if (!empty($serviceList))
		{
			foreach ($serviceList as $service)
			{
				$service = $ns . $service;
				$serviceTriggerList = $service::URLS_TO_TRIGGER;

				$triggerList = array_merge($triggerList, $serviceTriggerList);
			}
		}

		self::$serviceTriggerList = array_filter(array_map('trim', $triggerList));
	}

	/**
	 * Normalize URL's by setting scheme and revert url encodes.
	 *
	 * @param   string  $url  The URL to normalize
	 *
	 * @return  string  Returns the normalized URL
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function normalizeUrl($url)
	{
		// Set scheme if protocol of URL is relative
		if (substr($url, 0, 2) == '//')
		{
			$url = 'https:' . $url;
		}

		$url = htmlspecialchars_decode($url);

		// We're not working with encoded URLs
		if (false !== strpos($url, '%'))
		{
			$url = urldecode($url);
		}

		return $url;
	}

	/**
	 * Validate if the scheme of the URL is allowed.
	 *
	 * @param   string  $url  The URL to validate
	 *
	 * @return  boolean  Returns false if the scheme is not allowed
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function isUrlSchemeAllowed($url)
	{
		$scheme = substr($url, 0, 5);

		if (in_array($scheme, array('http:', 'https'), true))
		{
			return true;
		}

		return false;
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
			$value = ltrim(substr_replace($value, '', 0, strlen($basePath)), '\\/');
		}

		return $value;
	}

	/**
	 * Get CSS path of localized files.
	 *
	 * @param   string  $link         The link to be parsed.
	 * @param   string  $serviceName  Name of the service to call for execute the download:
	 *
	 * @return  array|boolean  Return false if no fonts where set
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public static function getNewFileContentLink($link, $serviceName = null)
	{
		if (empty($link) || !is_string($link))
		{
			return false;
		}

		if (is_null($serviceName) || !is_string($serviceName))
		{
			$serviceName = self::getDownloadService($link);
		}

		if ($serviceName === false)
		{
			if (JtaldefHelper::$debug)
			{
				Factory::getApplication()->enqueueMessage(
					'No Service found for the Url: ' . $link,
					'warning'
				);
			}

			return false;
		}

		$ns        = self::NS_TO_SERVICE;
		$isPath    = true;
		$serviceNs = $ns . $serviceName;

		if ($serviceName == 'ParseStyle')
		{
			$isPath    = false;
			$serviceNs = $ns . 'ParseCss';
		}

		if (!class_exists($serviceNs))
		{
			throw new \Exception(sprintf("The service '%s' to call for execute the download could not be found.", $serviceNs));
		}

		$fileExt = pathinfo($link, PATHINFO_EXTENSION);

		if (strpos( $fileExt, '?') !== false)
		{
			$fileExt = strstr($fileExt, '?', true);
		}

		if ($fileExt == 'js' && JtaldefHelper::existsServiceToParseScripts($serviceName) === false)
		{
			return false;
		}

		$service = new $serviceNs;

		$newFileContentLink = $service->getNewFileContentLink($link, $isPath);

		if (empty($newFileContentLink))
		{
			return $newFileContentLink;
		}

		switch (strtolower($serviceName))
		{
			case 'parsestyle':
				return $newFileContentLink;

			case 'parsecss':
				$file = str_replace(array('\\', '/'), '-', $link);
				$path = self::saveFile($file, $newFileContentLink);
				break;

			default:
				$file = md5($newFileContentLink);
				$path = $newFileContentLink;
				break;
		}

		// TODO If we want to minify the content, lets do it here

		return Uri::base(true) . '/' . $path . '?' . md5($file);
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

		if (false === $newPath)
		{
			return false;
		}

		$regex      = array(
			JPATH_ROOT => '',
			"\\"       => '/',
		);
		$newPath    = ltrim(str_replace(array_keys($regex), $regex, $newPath), '\\/');
		$path       = str_replace($parsedPath, Uri::base(true) . '/' . $newPath, $path);

		return $path;
	}

	/**
	 * Get the download service by URL
	 *
	 * @param   string  $link  The link to be scanned
	 *
	 * @return  string|boolean  False if no match is found
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function getDownloadService($link)
	{
		$ns             = self::NS_TO_SERVICE;
		$parsedUrl      = UriHelper::parse_url($link);
		$host           = (string) $parsedUrl['host'];

		foreach (self::$serviceToParse as $service)
		{
			$serviceNs     = $ns . $service;
			$urlsToTrigger = $serviceNs::URLS_TO_TRIGGER;

			if (in_array($host, $urlsToTrigger) !== false)
			{
				return $service;
			}
		}

		return false;
	}

	/**
	 * Check if there is a service which operates with <script/>
	 *
	 * @param   string  $service  The service to check
	 *
	 * @return  boolean
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function existsServiceToParseScripts($service = null)
	{
		$ns = self::NS_TO_SERVICE;

		if (!empty($service))
		{
			$serviceNs = $ns . $service;

			if (class_exists($serviceNs))
			{
				return $serviceNs::PARSE_SCRIPTS;
			}
		}

		foreach (self::$serviceToParse as $service)
		{
			$serviceNs = $ns . $service;

			if (class_exists($serviceNs) && $serviceNs::PARSE_SCRIPTS)
			{
				return true;
			}
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
			"`^([\t\s]+)`ism"                             => '',
			"`^\/\*(.+?)\*\/`ism"                         => "",
			"`(\A|[\n;]+)/\*[^*]*\*+(?:[^/*][^*]*\*+)*/`" => "$1",
			"`(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+`ism"       => "\n"
		);

		$content = preg_replace(array_keys($regex), $regex, $content);
		$content = preg_replace('/\s+/', ' ', $content);

		return $content;
	}

	/**
	 * Get the response data from URL
	 *
	 * @param   string              $url      URL to the content to download
	 * @param   array|\ArrayAccess  $options  Client options array.
	 *
	 * @return  object
	 *
	 * @since   1.0.7
	 */
	public static function getHttpResponseData($url, $options = array())
	{
		$response = new \stdClass();

		if (empty($options))
		{
			$options = array(
				'sslverify' => false,
			);
		}

		$options  = new Registry($options);
		$http     = HttpFactory::getHttp($options);

		try {
			$response = $http->get($url);
		}
		catch (\RuntimeException $e)
		{
			$response->code = 500;
			$response->body = 'Jtaldefhelper::getHttpContent()<br />' . $e->getMessage();
		}

		return $response;
	}

	/**
	 * Save file
	 *
	 * @param   string  $filename  The file name to save the buffer.
	 * @param   string  $buffer    The content to save.
	 *
	 * @return  string      The relative path to the file saved.
	 * @throws  \Exception  If the file couldn't be saved.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function saveFile($filename, $buffer)
	{
		$cacheFilePath = self::getCacheFilePath($filename);

		if (!file_exists(JPATH_ROOT . '/' . $cacheFilePath))
		{
			if (false === File::write(JPATH_ROOT . '/' . $cacheFilePath, $buffer))
			{
				throw new \Exception(sprintf('Could not write the file: %s', JPATH_ROOT . '/' . $cacheFilePath));
			}
		}

		return $cacheFilePath;
	}

	/**
	 * Get file path from the cache
	 *
	 * @param   string  $filename  The file name to return the path in the cache.
	 *
	 * @return  string  The relative path to the file.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getCacheFilePath($filename)
	{
		$extension = File::getExt($filename);
		$fontsExt  = array(
			'ttf',
			'woff',
			'woff2',
			'eot',
		);

		if (in_array($extension, $fontsExt, true))
		{
			$extension = 'fonts';
		}

		return self::JTALDEF_UPLOAD . '/' . $extension . '/' . $filename;
	}

	public static function getNotParsedNsFromServices()
	{
		$ns = self::NS_TO_SERVICE;
		$services = self::$serviceToParse;
		$nsToRemove = array();

		foreach ($services as $service)
		{
			$serviceNs = $ns . $service;

			$nsToRemove = array_merge(
				$nsToRemove,
				$serviceNs::NS_TO_REMOVE_NOT_PARSED_ITEMS_FROM_DOM,
			);
		}

		return $nsToRemove;
	}
}