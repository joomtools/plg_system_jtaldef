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

defined('_JEXEC') or die;

JLoader::registerNamespace('Jtaldef', JPATH_PLUGINS . '/system/jtaldef/src', false, false, 'psr4');

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Jtaldef\JtaldefHelper;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;

/**
 * Class plgSystemJtaldef
 *
 * @since  1.0.0
 */
class plgSystemJtaldef extends CMSPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Global application object
	 *
	 * @var    \Joomla\CMS\Application\CMSApplication
	 * @since  1.0.0
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
	 * @since  1.0.0
	 */
	protected $db;

	/**
	 * List of cached files.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $cachedFiles;

	/**
	 * List of new cached files to add to the cache.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $newCachedFiles = array();

	/**
	 * Listener for the `onAfterRender` event
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onAfterRender()
	{
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		$template           = $this->app->getTemplate();
		$parseHeadLinks     = $this->params->get('parseHeadLinks', true);
		$parseLocalCssFiles = $this->params->get('parseLocalCssFiles', true);
		$warp7Templates     = file_exists(JPATH_ROOT . '/templates/' . $template . '/warp/warp.xml');
		$rsTemplate         = defined('RSTEMPLATE_PATH');
		$yamlTemplate       = class_exists('JYAML');

		if (($warp7Templates || $rsTemplate || $yamlTemplate) && $parseHeadLinks)
		{
			$this->parseHeadLinksAfterRender();
		}

		$parseBodyInlineStyles = $this->params->get('parseBodyInlineStyles', false);

		if ($parseBodyInlineStyles)
		{
			// TODO Parse <body> content
		}

		// Save the cache entrys in database if debug is off
		if (!empty($this->newCachedFiles))
		{
			$this->saveCache();
		}
	}

	/**
	 * Parse head links of special templates
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function parseHeadLinksAfterRender()
	{
		// Get all linked stylesheets from head
		$body = $this->app->getBody();
		preg_match('#(<head[^>]*>)(.*)(</head>)#Usi', $body, $head);

		// Process stylesheets
		preg_match_all('#(<link[^>]*(stylesheet|as=.?style)[^>]*>)#Ui', $head[2], $sheets);

		foreach ($sheets[0] as $sheet)
		{
			if (false !== strpos($sheet, 'jtaldef'))
			{
				continue;
			}

			preg_match('#<link[^>]*href=(["\'])(.*)(\\1)#Ui', $sheet, $url);

			if (!empty($url[2]))
			{
				$newLink = $this->getNewCssFilePath($url[2]);

				$replacements = (false !== strpos($url[0], 'class='))
					? array(
						$url[2]   => !empty($newLink) ? $newLink : $url[2],
						'class="' => 'class="jtaldef ',
						"class='" => "class='jtaldef ",
					)
					: array(
						$url[2] => !empty($newLink) ? $newLink : $url[2],
						'href=' => 'class="jtaldef" href=',
					);

				$newSheet = str_replace(array_keys($replacements), $replacements, $sheet);
				$head[2]  = str_replace($sheet, $newSheet, $head[2]);
			}
		}

		$body = str_replace($head[0], $head[1] . $head[2] . $head[3], $body);
		$this->app->setBody($body);
	}

	/**
	 * Get the nue css file path
	 *
	 * @param   string  $value  Url to parse
	 *
	 * @return  string
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function getNewCssFilePath($value)
	{
		// Set scheme if protocol of URL is relative
		if (substr($value, 0, 2) == '//')
		{
			$value = 'https:' . $value;
		}

		$isExternalUrl = JtaldefHelper::isExternalUrl($value);

		// Remove unneeded query on internal file path
		if (!$isExternalUrl)
		{
			$value = JtaldefHelper::removeBasePath($value);
		}

		$originalId = md5($value);

		// Searching the cache
		$cache    = $this->getCache();
		$isCached = in_array($originalId, array_keys($cache));

		// Is triggered if we have a cached entry
		if ($isCached)
		{
			// Return the cached file path
			return $cache[$originalId]['cache_url'];
		}

		$downloadHandler    = false;
		$parseLocalCssFiles = $this->params->get('parseLocalCssFiles', true);

		if ($parseLocalCssFiles)
		{
			$downloadHandler = 'ParseCss';
		}

		if ($isExternalUrl)
		{
			$downloadHandler = JtaldefHelper::getDownloadHandler($value);
		}

		$newCssFile = false;

		// Is triggered if we have no cached entry but a class to handle it
		if (!$isCached && !empty($downloadHandler))
		{
			$newCssFile = JtaldefHelper::getNewFileContent($value, $downloadHandler);
		}

		// Register new cache entry
		if (!$newCssFile)
		{
			$this->addNewCacheEntry($originalId, $value);

			return $newCssFile;
		}

		$this->addNewCacheEntry($originalId, $newCssFile);

		return $newCssFile;
	}

	/**
	 * Get cached information from database
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getCache()
	{
		if (null === $this->cachedFiles)
		{
			$query = $this->db->getQuery(true);

			$query->select('*')->from(JtaldefHelper::JTLSGF_DB_TABLE);

			$cache = (array) $this->db->setQuery($query)->loadAssocList();

			if (!empty($cache))
			{
				$cache = ArrayHelper::pivot($cache, 'original_url_id');
			}

			$this->cachedFiles = $cache;
		}

		return $this->cachedFiles;
	}

	/**
	 * Add new downloaded file to cache
	 *
	 * @param   string  $originalId     The identifier of the original file
	 * @param   string  $localFilePath  The local path of the downloaded file
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function addNewCacheEntry($originalId, $localFilePath)
	{
		if (empty($originalId) || empty($localFilePath))
		{
			return;
		}

		$this->newCachedFiles = array_merge(
			$this->newCachedFiles,
			array(
				$originalId => array(
					'original_url_id' => $originalId,
					'cache_url'       => $localFilePath,
				),
			)
		);
	}

	/**
	 * Get cached information from database
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function saveCache()
	{
		$debug          = JtaldefHelper::$debug;
		$newCachedFiles = $this->newCachedFiles;

		if (!empty($newCachedFiles) && !$debug)
		{
			$newCachedFiles = array_unique($newCachedFiles, SORT_REGULAR);

			$query = $this->db->getQuery(true);

			$query->insert(JtaldefHelper::JTLSGF_DB_TABLE)
				->columns(array('original_url_id', 'cache_url'));

			foreach ($newCachedFiles as $values)
			{
				$query->values($this->db->q($values['original_url_id']) . ',' . $this->db->q($values['cache_url']));
			}

			$this->db->setQuery($query)->execute();
		}
	}

	/**
	 * Listener for the `onBeforeCompileHead` event
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function onBeforeCompileHead()
	{
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		JtaldefHelper::$debug = $this->params->get('debug', false);

		$parseHeadLinks = $this->params->get('parseHeadLinks', true);

		if ($parseHeadLinks)
		{
			$this->parseHeadLinks();
		}

		$parseHeadInlineStyles = $this->params->get('parseHeadInlineStyles', true);

		if ($parseHeadInlineStyles)
		{
			$this->parseHeadInlineStyles();
		}
	}

	/**
	 * Parse head links
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function parseHeadLinks()
	{
		$newStylesheets = array();

		// Get all linked stylesheets from head
		$document    = Factory::getDocument();
		$loadedFiles = array_keys($document->_styleSheets);

		foreach ($loadedFiles as $loadedFile)
		{
			$newCssFile = $this->getNewCssFilePath($loadedFile);

			// Is triggered if wo cant handle it
			if (empty($newCssFile))
			{
				// Set the original entry
				$newStylesheets[$loadedFile] = $document->_styleSheets[$loadedFile];

				// Add class as identifier
				$newStylesheets[$loadedFile]['class'] = 'jtaldef';

				continue;
			}

			// Set the new entry
			$newStylesheets[$newCssFile] = array(
				'options' => array(
					'version' => 'auto',
				),
				'type'    => 'text/css',
				'class'   => 'jtaldef',
			);
		}

		// Replace all linked stylesheets in the head with the new
		$document->_styleSheets = $newStylesheets;

		return;
	}

	/**
	 * Parse head inline styles
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function parseHeadInlineStyles()
	{
		// Get the inline style from head
		$document = Factory::getDocument();

		if (empty($inlineStyle = $document->_style['text/css']))
		{
			return;
		}

		// Parse the inline style
		$newInlineStyle = JtaldefHelper::getNewFileContent($inlineStyle, 'ParseInline');

		// Replace the inline style in the head with the parsed
		if (!empty($newInlineStyle))
		{
			$document->_style['text/css'] = $newInlineStyle;
		}

		return;
	}

	/**
	 * Delete cached information in database
	 *
	 * @return  boolean
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function clearCache()
	{
		try
		{
			$this->db->setQuery('TRUNCATE TABLE ' . JtaldefHelper::JTLSGF_DB_TABLE)->execute();
		}
		catch (Exception $e)
		{
			throw new Exception(Text::_('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_DB'), 500);
		}

		return Folder::delete(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD);
	}

	/**
	 * Check valid AJAX request
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isAjaxRequest()
	{
		return strtolower($this->app->input->server->get('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
	}

	/**
	 * Ajax methode
	 *
	 * @return   void
	 * @throws   Exception
	 * @since    1.2.0
	 */
	public function onAjaxJtaldefClearTrash()
	{
		$accessDenied = Text::_('JGLOBAL_AUTH_ACCESS_DENIED');

		if (!$this->app->getSession()->checkToken())
		{
			throw new Exception(Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_TOKEN', $accessDenied), 403);
		}

		if (!$this->isAjaxRequest())
		{
			throw new Exception(Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_AJAX_REQUEST', $accessDenied), 403);
		}

		$cacheCleared = $this->clearCache();

		if (!$cacheCleared)
		{
			throw new Exception(Text::_('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_FILES'), 500);
		}
	}
}
