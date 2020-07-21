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
	 * @param   string  $link  Link to download the fonts
	 *
	 * @return  boolean|string  False if no font info is set in the query else the local path to the css file
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function getNewFileContent($link)
	{
		$file = JPATH_ROOT . '/' . $link;

		if (!file_exists($file))
		{
			return false;
		}

		$content = file_get_contents($file);
		$content = JtaldefHelper::cleanContent($content);

		if (empty($content))
		{
			return false;
		}

		$matches = $this->getFontImports($content);

		if (empty($matches) || $matches['onlyInternal'])
		{
			return false;
		}

		unset($matches['onlyInternal']);

		foreach ($matches as $fontUrl => $imports)
		{
			$downloadHanler = JtaldefHelper::getDownloadHandler($fontUrl);

			if ($downloadHanler)
			{
				$localFontUrl = JtaldefHelper::getNewFileContent($fontUrl, $downloadHanler);
			}

			if (!$downloadHanler)
			{
				$localFontUrl = JtaldefHelper::replaceRelativeToAbsolutePath($fontUrl, $file);
			}

			$replace[] = "@import '" . $localFontUrl . "';";

			if (count($imports) > 1)
			{
				$replace = array_merge($replace, array_fill(1, count($imports) - 1, ''));
			}

			$content = str_replace($imports, $replace, $content);
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
				if (empty($paths))
				{
					continue;
				}

				$regex   = array('"', "'");
				$path[1] = trim(str_replace($regex, '', $path[1]));

				if (JtaldefHelper::isExternalUrl($path[1]))
				{
					continue;
				}

				$newPath = JtaldefHelper::replaceRelativeToAbsolutePath($path[1], $file);

				$replacements['search'][]  = $path[1];
				$replacements['replace'][] = $newPath;
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
		if (preg_match_all('#@import\s+(.*);$#Umix', $content, $imports, PREG_SET_ORDER))
		{
			foreach ($imports as $match)
			{
				$regex              = array('"', "'", 'url(', ')');
				$fontUrl            = trim(str_replace($regex, '', $match[1]));
				$return[$fontUrl][] = $match[0];

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