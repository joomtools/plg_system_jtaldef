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
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Profiler\Profiler;
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
	 * Website HTML content.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $htmlBuffer;

	/**
	 * List of indexed files.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $indexedFiles;

	/**
	 * List of new indexed files to add to the index.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $newIndexedFiles = array();

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

		JtaldefHelper::$debug  = JDEBUG;
		$parseHeadInlineStyles = $this->params->get('parseHeadInlineStyles', false);

		if ($parseHeadInlineStyles)
		{
			$this->parseHeadInlineStyles();
		}
	}

	/**
	 * Listener for the `onAfterRender` event
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function onAfterRender()
	{
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		$parseHeadLinks = $this->params->get('parseHeadLinks', false);

		if ($parseHeadLinks)
		{
			$this->parseHeadLinks();
		}

		$parseBodyInlineStyles = $this->params->get('parseBodyInlineStyles', false);

		if ($parseBodyInlineStyles)
		{
			// TODO Parse <body> content
		}

		// Save the index entrys in database if debug is off
		if (!empty($this->newIndexedFiles))
		{
			$this->saveCache();
		}

		$this->app->setBody($this->getHtmlBuffer());

		if (JtaldefHelper::$debug)
		{
			$this->app->enqueueMessage(
				Profiler::getInstance('Application')->mark('JT - ALDEF (onAfterRender)'),
				'info'
			);
		}
	}

	/**
	 * Get the rendered HTML before be outputed
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function getHtmlBuffer()
	{
		if (null === $this->htmlBuffer)
		{
			$this->htmlBuffer = $this->app->getBody();
		}

		return $this->htmlBuffer;
	}

	/**
	 * Set the parsed HTML buffer before be outputed
	 *
	 * @param   string  $buffer  The parsed HTML buffer
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function setHtmlBuffer($buffer)
	{
		if (!empty($buffer))
		{
			$this->htmlBuffer = $buffer;
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
	private function parseHeadLinks()
	{
		// Get all linked stylesheets from head
		$body = $this->getHtmlBuffer();

		$hrefs    = $this->getXmlBuffer("//head/link[@rel='stylesheet']");
		$searches = array();
		$replaces = array();

		foreach ($hrefs as $href)
		{
			$search = str_replace(array('/>', '>'), '', $href->asXML());
			$searches[] = $search;
			$url = (string) $href->attributes()['href'];

			$newUrl = $this->getNewCssFilePath($url);

			$regex = (false !== strpos($search, 'class='))
				? array(
					$url => empty($newUrl) ? $url : $newUrl,
					'class="' => 'class="jtaldef ',
					"class='" => "class='jtaldef ",
				)
				: array(
					$url => empty($newUrl) ? $url : $newUrl,
					'href=' => 'class="jtaldef" href=',
				);

			$replaces[] = str_replace(array_keys($regex), array_values($regex), $search);
		}

		$body = str_replace($searches, $replaces, $body);
		$this->setHtmlBuffer($body);
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

		// Searching the indexes
		$indexes    = $this->getIndexes();
		$isIndexed = in_array($originalId, array_keys($indexes));

		// Is triggered if we have a cached entry
		if ($isIndexed)
		{
			// Return the cached file path
			return $indexes[$originalId]['cache_url'];
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
		if (!$isIndexed && !empty($downloadHandler))
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

		return $newCssFile . '?' . $originalId;
	}

	/**
	 * Get cached information from database
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getIndexes()
	{
		if (null === $this->indexedFiles)
		{
			if (file_exists(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD . '/fileindex'))
			{
				return $this->indexedFiles = (array) json_decode(
					@file_get_contents(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD . '/fileindex'),
					true
				);
			}
		}

		return (array) $this->indexedFiles;
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

		$this->newIndexedFiles = array_merge(
			$this->newIndexedFiles,
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
		$newCachedFiles = $this->newIndexedFiles;

		if (!empty($newCachedFiles))
		{
			$newCachedFiles = array_merge($this->getIndexes(), $newCachedFiles);
			$newCachedFiles = json_encode(array_unique($newCachedFiles, SORT_REGULAR));

			@file_put_contents(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD . '/fileindex', $newCachedFiles);

			return;
		}
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
	public function onAjaxJtaldefClearIndex()
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

		$clearIndex = Folder::delete(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD);

		if (!$clearIndex)
		{
			throw new Exception(Text::_('PLG_SYSTEM_JTALDEF_CLEAR_INDEX_ERROR'), 500);
		}
	}

	/**
	 * Get the HTML buffer as XML or the nodes passed by param
	 *
	 * @param   string  $ns  Xpath namespace to return
	 *
	 * @return  \SimpleXMLElement|array
	 *
	 * @since   1.0.0
	 */
	private function getXmlBuffer($ns = null)
	{
		$body      = $this->getHtmlBuffer();
		$xmlString = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . $body;

		$dom = new DOMDocument;
		libxml_use_internal_errors(true);

		$dom->loadHTML($xmlString);
		libxml_clear_errors();

		$xmlString = $dom->saveXML($dom->getElementsByTagName('html')->item(0));
		$xml       = simplexml_load_string($xmlString);

		if (null !== $ns)
		{
			$nameSpaced = $xml->xpath($ns);

			return $nameSpaced;
		}

		return $xml;
	}
}
