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

use Jtaldef\Helper\JtaldefHelper;

/**
 * Download and save external fonts like Google Fonts
 *
 * @since  1.0.0
 */
class ParseCss
{
	/**
	 * Description
	 *
	 * @param   string   $value   Link to content or the content itself to parse.
	 * @param   boolean  $isPath  True if it is a path to a local file or false for content like <style/>.
	 *
	 * @return  boolean|string  False if no font info is set in the query else the local path to the css file.
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function getNewFileContent($value, $isPath = true)
	{
		$file = null;
		$content = $value;

		if ($isPath)
		{
			$file = JPATH_ROOT . '/' . $value;

			if (!file_exists($file))
			{
				return false;
			}

			$content = file_get_contents($file);
		}

		if (empty($content) || !is_string($content))
		{
			return null;
		}

		$content = JtaldefHelper::cleanContent($content);
		$matches = $this->getFontImports($content);

		if (empty($matches) || $matches['onlyInternal'])
		{
			return null;
		}

		unset($matches['onlyInternal']);

		foreach ($matches as $imports)
		{
			$service = JtaldefHelper::getServiceByLink($imports['fontUrl']);

			if ($service)
			{
				$localFontUrl = JtaldefHelper::getNewFileContentLink($imports['fontUrl'], $service);
			}

			if (!$service)
			{
				$localFontUrl = JtaldefHelper::replaceRelativeToAbsolutePath($imports['fontUrl'], $file);
			}

			if ($localFontUrl !== false)
			{
				$newImport = "@import '" . $localFontUrl . "';";

				$imports['search'] = array_unique($imports['search'], SORT_REGULAR);

				$content = $newImport . PHP_EOL . str_replace($imports['search'], '', $content);
			}
		}

		$content = $this->replaceRelativePath($content, $file);

		return $content;
	}

	/**
	 * Search for font-face blocks to parse local relative paths
	 *
	 * @param   string  $content  The content to be scanned
	 * @param   string  $file     Absolute path to the actual file
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function replaceRelativePath($content, $file)
	{
		$replacements = array();

		// Check for Google Font imports - benchmarked regex
		if (preg_match_all('#url\((.*)\)#Us', $content, $paths, PREG_SET_ORDER))
		{
			foreach ($paths as $path)
			{
				$regex   = array('"', "'");
				$path[1] = trim(str_replace($regex, '', $path[1]));

				if (empty($path[1]) || JtaldefHelper::isExternalUrl($path[1]))
				{
					continue;
				}

				$newPath = JtaldefHelper::replaceRelativeToAbsolutePath($path[1], $file);

				if (false === $newPath)
				{
					continue;
				}

				$replacements['search'][md5($path[1])]  = $path[1];
				$replacements['replace'][md5($path[1])] = $newPath;
			}

			if (!empty($replacements))
			{
				$content = str_replace($replacements['search'], $replacements['replace'], $content);
			}
		}

		return $content;
	}

	/**
	 * Search for fonts imports
	 *
	 * @param   string  $content  The content to be scanned
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getFontImports($content)
	{
		$return = array();
		$onlyInternal = true;

		// Check for Google Font imports - benchmarked regex
		if (preg_match_all('#(@import\s*(url\(|"|\')(?<url>.*)[^\d];)#Um', $content, $imports, PREG_SET_ORDER))
		{
			foreach ($imports as $match)
			{
				$fontUrl = str_replace(array('url(', ')', '"', "'"), '', $match['url']);
				$fontUrl   = trim($fontUrl);
				$fontUrlId = md5($fontUrl);

				$fontUrl = JtaldefHelper::normalizeUrl($fontUrl);

				$return[$fontUrlId]['fontUrl'] = $fontUrl;
				$return[$fontUrlId]['search'][] = $match[0];

				if (JtaldefHelper::isExternalUrl($fontUrl))
				{
					$onlyInternal = false;
				}
			}

			$return['onlyInternal'] = $onlyInternal;
		}

		return $return;
	}
}