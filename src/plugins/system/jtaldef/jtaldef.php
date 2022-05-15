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

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Profiler\Profiler;
use Jtaldef\JtaldefHelper;

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
	 * Listener for the `onAfterRender` event
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function onBeforeRender()
	{
		if (version_compare(JVERSION, '4', 'lt'))
		{
			HTMLHelper::_('behavior.core');
		}
		else
		{
			$this->app->getDocument()->getWebAssetManager()->useScript('messages');
			$this->app->enqueueMessage(
				'irgend was',
				'info'
			);
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

		// Set starttime for process total time
		$startTime = microtime(1);

		if (version_compare(JVERSION, '4', 'ge'))
		{
			$this->app->getMessageQueue(true);
		}

		JtaldefHelper::$debug = $this->params->get('debug', false);

		if (JtaldefHelper::$debug)
		{
			Profiler::getInstance('JT - ALDEF (onAfterRender)')->setStart($startTime);
		}

		$parseHeadLinks = $this->params->get('parseHeadLinks', false);

		if ($parseHeadLinks)
		{
			$this->parseHeadLinks();

			$removeNotParsedFromHead = $this->params->get('removeNotParsedFromHead', true);

			if ($removeNotParsedFromHead)
			{
				$handlerToParse = (array) $this->params->get('handlerToParse', array());

				foreach ($handlerToParse as $handler)
				{
					$this->removeNotParsedFromHead($handler::REMOVE_NOT_PARSED_FROM_HEAD_NS);
				}

			}
		}

		$parseHeadStyleTags = $this->params->get('parseHeadStyleTags', false);
		$parseBodyStyleTags = $this->params->get('parseBodyStyleTags', false);

		if ($parseHeadStyleTags || $parseBodyStyleTags)
		{
			switch (true)
			{
				case $parseBodyStyleTags && !$parseHeadStyleTags :
					$ns = "//body/style";
					break;

				case $parseBodyStyleTags && $parseHeadStyleTags :
					$ns = "//style";
					break;

				default:
					$ns = "//head/style";
			}

			$this->parseInlineStyles($ns);
		}

		// Save the index entrys in database if debug is off
		if (!empty($this->newIndexedFiles))
		{
			$this->saveIndex();
		}

		if (JtaldefHelper::$debug)
		{
			$this->app->enqueueMessage(
				Profiler::getInstance('JT - ALDEF (onAfterRender)')->mark('Verarbeitungszeit'),
				'info'
			);

			$this->parseMessageQueue();
		}

		$this->app->setBody($this->getHtmlBuffer());
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
	 * @param   array  $searches  Array of values to search in the HTML buffer
	 * @param   array  $replaces  Array of values to set in the HTML buffer
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function setNewHtmlBuffer($searches, $replaces)
	{
		$buffer = $this->getHtmlBuffer();

		if (!empty($buffer) && !empty($searches))
		{
			$this->htmlBuffer = str_replace($searches, $replaces, $buffer);
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
		$searches = array();
		$replaces = array();

		$items    = $this->getLinkedStylesheetsFromHead();

		foreach ($items as $item)
		{
			$search = str_replace(array('/>', '>'), '', $item->asXML());
			$url    = $item->attributes()['href']->asXML();
			$url    = trim(str_replace(array('href=', '"', "'"), '', $url));
			$newUrl = $this->getNewCssFilePath($url);

			if (false === strpos($this->getHtmlBuffer(), $url))
			{
				$url    = htmlspecialchars_decode($url);
				$search = htmlspecialchars_decode($search);
			}

			$regex  = array(
					'search'  => array(
						$url,
						'href=',
					),
					'replace' => array(
						empty($newUrl) ? $url : $newUrl,
						'data-jtaldef="indexed" href=',
					),
				);

			// Create searches and replacements
			$searches[] = $search;
			$replaces[] = str_replace($regex['search'], $regex['replace'], $search);
		}

		$this->setNewHtmlBuffer($searches, $replaces);
	}

	/**
	 * Parse inline styles (<style/>)
	 *
	 * @param   string  $ns  The namespace to search for style tags in HTML
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	private function parseInlineStyles($ns)
	{
		$searches = array();
		$replaces = array();

		// Get styles from XML buffer
		$styles = $this->getXmlBuffer($ns);

		foreach ($styles as $style)
		{
			$search = $style->asXML();
			$style = (string) $style;

			// Parse the inline style
			$newStyle = JtaldefHelper::getNewFileContent($style, 'ParseStyle');

			if (false === $newStyle)
			{
				continue;
			}

			// Create searches and replacements
			$searches[] = $search;
			$replaces[] = str_replace($style, $newStyle, $search);
		}

		$this->setNewHtmlBuffer($searches, $replaces);
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
		$value = htmlspecialchars_decode($value);

		// Set scheme if protocol of URL is relative
		if (substr($value, 0, 2) == '//')
		{
			$value = 'https:' . $value;
		}

		// We're not working with encoded URLs
		if (false !== strpos($value, '%'))
		{
			$value = urldecode($value);
		}

		$isExternalUrl = JtaldefHelper::isExternalUrl($value);

		// Remove unneeded query on internal file path
		if (!$isExternalUrl)
		{
			$value = JtaldefHelper::removeBasePath($value);
		}

		$originalId = md5($value);

		// Searching the indexes
		$indexes   = $this->getIndexed();
		$isIndexed = in_array($originalId, array_keys($indexes));

		// Is triggered if we have a indexed entry
		if ($isIndexed)
		{
			// Return the cached file path
			return $indexes[$originalId];
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
			$handlerToParse = (array) $this->params->get('handlerToParse', array());

			if (in_array($downloadHandler, $handlerToParse, true) || $downloadHandler == 'ParseCss')
			{
				$newCssFile = JtaldefHelper::getNewFileContent($value, $downloadHandler);

				/* At the moment not needed
				if (!$newCssFile && JtaldefHelper::$debug)
				{
					$this->app->enqueueMessage(
						Text::sprintf('PLG_SYSTEM_JTALDEF_ERROR_WHILE_PROCESSING', $value),
						'error'
					);
				}
				*/
			}
		}

		// Register new cache entry
		if (!$newCssFile)
		{
			if (!$isExternalUrl && $parseLocalCssFiles)
			{
				$this->addNewCacheEntry($originalId, false);
			}

			return $newCssFile;
		}

		$this->addNewCacheEntry($originalId, $newCssFile);

		return $newCssFile;
	}

	/**
	 * Load indexed files
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getIndexed()
	{
		if (null === $this->indexedFiles)
		{
			if (JtaldefHelper::$debug)
			{
				return array();
			}

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
		if (empty($originalId))
		{
			return;
		}

		$this->newIndexedFiles = array_merge(
			$this->newIndexedFiles,
			array(
				$originalId => $localFilePath,
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
	private function saveIndex()
	{
		$newCachedFiles = $this->newIndexedFiles;

		if (!empty($newCachedFiles))
		{
			$newCachedFiles = array_merge($this->getIndexed(), $newCachedFiles);
			$newCachedFiles = json_encode($newCachedFiles);

			if (!is_dir(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD))
			{
				Folder::create(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD);
			}

			@file_put_contents(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD . '/fileindex', $newCachedFiles);
		}
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
	 * @throws   \Exception
	 * @since    1.2.0
	 */
	public function onAjaxJtaldefClearIndex()
	{
		$accessDenied = Text::_('JGLOBAL_AUTH_ACCESS_DENIED');

		if (!$this->app->getSession()->checkToken())
		{
			throw new \InvalidArgumentException(Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_TOKEN', $accessDenied), 403);
		}

		if (!$this->isAjaxRequest())
		{
			throw new \InvalidArgumentException(Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_AJAX_REQUEST', $accessDenied), 403);
		}

		$clearIndex = Folder::delete(JPATH_ROOT . '/' . JtaldefHelper::JTLSGF_UPLOAD);

		if (!$clearIndex)
		{
			throw new \InvalidArgumentException(Text::_('PLG_SYSTEM_JTALDEF_CLEAR_INDEX_ERROR'), 500);
		}
	}

	/**
	 * Get the HTML buffer as XML or the nodes passed by param
	 *
	 * @param   string  $ns  Xpath namespace to return
	 *
	 * @return  array|\SimpleXMLElement[]
	 *
	 * @since   1.0.0
	 */
	private function getXmlBuffer($ns = null)
	{
		$matches = array();

		// Get html buffer
		$htmlBuffer = $this->getHtmlBuffer();

		if (empty($htmlBuffer))
		{
			return array();
		}

		$htmlBuffer = str_replace('xmlns=', 'ns=', $htmlBuffer);

		$dom = new DOMDocument;
		libxml_use_internal_errors(true);

		$dom->loadHTML($htmlBuffer);
		libxml_clear_errors();

		try
		{
			$xmlString = $dom->saveXML($dom->getElementsByTagName('html')->item(0), LIBXML_NOEMPTYTAG);
		}
		catch (\RuntimeException $e)
		{
			$this->app->enqueueMessage(
				$e->getMessage(),
				'error'
			);

			return array();
		}

		try
		{
			$xmlBuffer = new \SimpleXMLElement($xmlString);
		}
		catch (\RuntimeException $e)
		{
			$this->app->enqueueMessage(
				$e->getMessage(),
				'error'
			);

			return array();
		}

		if (null !== $ns && !empty($xmlBuffer))
		{
			$matches = $xmlBuffer->xpath($ns);
		}

		return $matches;
	}

	/**
	 * Find linked stylesheets in the head by namespace
	 *
	 * @return  array|\SimpleXMLElement[]
	 *
	 * @since   1.0.2
	 */
	private function getLinkedStylesheetsFromHead()
	{
		$hrefs = array();

		$namespace = "//head//*[contains(@href,'.css')]|//head//*[@rel='lazy-stylesheet']|//head//*[@rel='stylesheet']";

		$hrefs = array_merge($hrefs, $this->getXmlBuffer($namespace));

		return $hrefs;
	}

	/**
	 * Remove not parsed links in the head
	 *
	 * @return  void
	 *
	 * @since   1.0.4
	 */
	private function removeNotParsedFromHead($namespace)
	{
		$searches = array();
		$replaces = array();

		$hrefs = $this->getXmlBuffer($namespace);

		foreach ($hrefs as $href)
		{
			$search = str_replace(array('/>', '>'), '', html_entity_decode(trim($href->asXML())));
			$searches[] = $search . '>';
			$searches[] = $search . ' />';
		}

		$this->setNewHtmlBuffer($searches, $replaces);
	}

	/**
	 * Parse message queue as javascript into <head>
	 *
	 * @return  void
	 *
	 * @since   1.0.5
	 */
	private function parseMessageQueue()
	{
		// Get rendered system message output
		$oldMessagesOutput = $this->getXmlBuffer("//body//*[@id='system-message-container']");

		if (!empty($oldMessagesOutput) && !empty(json_encode($messageQueue = $this->getJsonMessageQueue())))
		{
			$search  = array('</head>');
			if (version_compare(JVERSION, '4', 'lt'))
			{
				$replace = array("\t<script data-jtaldef=\"joomla-messages\">"
					. "document.addEventListener('DOMContentLoaded', () => {"
					. "Joomla.renderMessages(" . $messageQueue . ");"
					. "});"
					. "</script>"
					. "\n</head>",
				);
			}
			else
			{
				$replace = array("\t<script class=\"joomla-script-options new\" type=\"application/json\" data-jtaldef=\"joomla-messages\">"
					. "{\"joomla.messages\":["
					. $messageQueue
					. "]}"
					. "</script>"
					. "\n</head>",
				);
			}

			// Render updated system message output
			$this->setNewHtmlBuffer($search, $replace);
		}
	}

	/**
	 * Get bundled message queue by type
	 *
	 * @return   string  Json encoded
	 *
	 * @since   1.0.5
	 */
	private function getJsonMessageQueue()
	{
		$messages = array();
		$queue    = $this->app->getMessageQueue();

		foreach ($queue as $message)
		{
			$messages[$message['type']][] = $message['message'];
		}

		return json_encode($messages);
	}

}
