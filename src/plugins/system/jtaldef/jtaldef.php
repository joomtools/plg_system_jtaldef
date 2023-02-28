<?php
/**
 * Automatic local download external files
 *
 * @package     Joomla.Plugin
 * @subpackage  System.Jtaldef
 *
 * @author      Guido De Gobbis <support@joomtools.de>
 * @copyright   JoomTools.de - All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

\JLoader::registerNamespace('Jtaldef', JPATH_PLUGINS . '/system/jtaldef/src', true, false, 'psr4');

use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Profiler\Profiler;
use Joomla\Console\Command\AbstractCommand;
use Jtaldef\Helper\JtaldefHelper;

/**
 * Class PlgSystemJtaldef
 *
 * @since  2.0.0
 */
class PlgSystemJtaldef extends CMSPlugin
{
    /**
     * The version of this plugin.
     *
     * @var    string
     * @since  2.0.1
     */
    const JTALDEF_VERSION = '##VERSION##';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  2.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Global application object
     *
     * @var    \Joomla\CMS\Application\CMSApplication
     * @since  2.0.0
     */
    protected $app;

    /**
     * Website HTML content.
     *
     * @var    string
     * @since  2.0.0
     */
    private $htmlBuffer;

    /**
     * List of indexed files.
     *
     * @var    array
     * @since  2.0.0
     */
    private $indexedFiles = array();

    /**
     * List of new indexed files to add to the index.
     *
     * @var    array
     * @since  2.0.0
     */
    private $newIndexedFiles = array();

    /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An optional associative array of configuration settings.
     *                             Recognized key values include 'name', 'group', 'params', 'language'
     *                             (this list is not meant to be comprehensive).
     *
     * @since   2.0.6
     */
    public function __construct(&$subject, $config = array())
    {
        if (version_compare(JVERSION, '4', 'ge')) {
            $subject->addListener(ApplicationEvents::BEFORE_EXECUTE, [$this, 'registerCliCommands']);
        }

        parent::__construct($subject, $config);
    }

    /**
     * Registers command classes to the CLI application.
     *
     * This is an event handled for the ApplicationEvents::BEFORE_EXECUTE event.
     *
     * @param   ApplicationEvent  $event  The before_execite application event being handled
     *
     * @since   2.0.6
     *
     * @noinspection PhpUnused
     */
    public function registerCLICommands(ApplicationEvent $event)
    {
        $serviceId = 'CacheCleaner';

        /** @var ConsoleApplication $app */
        $app      = $event->getApplication();
        $classFQN = 'Jtaldef\\Console\\' . $serviceId;

        if (!class_exists($classFQN)) {
            throw new \RuntimeException(sprintf('Unknown JTALDEF CLI command class ‘%s’.', $serviceId));
        }

        $classParents = class_parents($classFQN);

        if (!in_array(AbstractCommand::class, $classParents)) {
            throw new \RuntimeException(sprintf('Invalid JTALDEF CLI command object ‘%s’.', $serviceId));
        }

        $o = new $classFQN;

        try {
            $app->addCommand($o);
        } catch (Throwable $e) {
            return;
        }
    }

    /**
     * Listener for the `onBeforeCompileHead` event
     *
     * @return  void
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    public function onBeforeCompileHead()
    {
        if ($this->app->isClient('administrator')) {
            return;
        }

        // Set starttime for process total time
        $startTime = microtime(1);

        JtaldefHelper::$debug = $this->params->get('debug', false);

        if (JtaldefHelper::$debug) {
            Profiler::getInstance('JT - ALDEF (onBeforeCompileHead)')->setStart($startTime);
        }

        try {
            $error          = false;
            $serviceToParse = (array) $this->params->get('serviceToParse', array());

            if ($this->params->get('parseLocalCssFiles', false)) {
                $serviceToParse[] = 'ParseCss';
            }

            JtaldefHelper::initializeServices($serviceToParse);

            if (version_compare(JVERSION, '4', 'lt')) {
                HTMLHelper::_('behavior.core');
            } else {
                $this->app->getDocument()->getWebAssetManager()->useScript('messages');
            }

            $parseHead = $this->params->get('parseHead', false);

            if ($parseHead) {
                $this->parseHeadStylesheetsBeforeCompiled();

                $parseHeadScripts = JtaldefHelper::existsServiceToParseScripts();

                if ($parseHeadScripts) {
                    $this->parseHeadScriptsBeforeCompiled();
                }
            }
        } catch (\Throwable $e) {
            $error = true;
        } catch (\ErrorException $e) {
            $error = true;
        }

        if (JtaldefHelper::$debug) {
            if ($error) {
                $backtrace = LayoutHelper::render('joomla.error.backtrace', array('backtrace' => $e->getTrace()));
                $this->app->enqueueMessage(
                    'Error during execution of onBeforeCompileHead():'
                    . ' <br/>' . $e->getMessage()
                    . ' <br/>in file ' . $e->getFile() . ':' . $e->getLine()
                    . ' <br/>' . $backtrace,
                    'error'
                );
            }

            $this->app->enqueueMessage(
                Profiler::getInstance('JT - ALDEF (onBeforeCompileHead)')->mark('Verarbeitungszeit'),
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
     * @since   2.0.0
     */
    public function onAfterRender()
    {
        if ($this->app->isClient('administrator')) {
            return;
        }

        // Set starttime for process total time
        $startTime = microtime(1);

        if (JtaldefHelper::$debug) {
            Profiler::getInstance('JT - ALDEF (onAfterRender)')->setStart($startTime);
        }

        try {
            $error     = false;
            $parseHead = $this->params->get('parseHead', false);

            if ($parseHead) {
                $this->parseHeadLinks();
            }

            $removeNotParsedFromDom = $this->params->get('removeNotParsedFromDom', true);

            if ($removeNotParsedFromDom) {
                if (version_compare(JVERSION, '4', 'ge')) {
                    $this->app->setHeader('Link', null, true);
                }

                $nsToRemove = (array) JtaldefHelper::getNotParsedNsFromServices();

                foreach ($nsToRemove as $ns) {
                    $this->removeNotParsedFromDom($ns);
                }
            }

            $parseHeadStyleTags = $this->params->get('parseHeadStyleTags', false);
            $parseBodyStyleTags = $this->params->get('parseBodyStyleTags', false);

            if ($parseHeadStyleTags || $parseBodyStyleTags) {
                switch (true) {
                    case $parseBodyStyleTags && !$parseHeadStyleTags :
                        $ns = "//body//style";
                        break;

                    case $parseBodyStyleTags && $parseHeadStyleTags :
                        $ns = "//style";
                        break;

                    default:
                        $ns = "//head//style";
                }

                $this->parseInlineStyles($ns);
            }
        } catch (\Throwable $e) {
            $error = true;
        } catch (\ErrorException $e) {
            $error = true;
        }

        if (JtaldefHelper::$debug) {
            if ($error) {
                $backtrace = LayoutHelper::render('joomla.error.backtrace', array('backtrace' => $e->getTrace()));
                $this->app->enqueueMessage(
                    'Error during execution of onAfterRender():'
                    . ' <br/>' . $e->getMessage()
                    . ' <br/>in file ' . $e->getFile() . ':' . $e->getLine()
                    . ' <br/>' . $backtrace,
                    'error'
                );
            }

            $this->app->enqueueMessage(
                Profiler::getInstance('JT - ALDEF (onAfterRender)')->mark('Verarbeitungszeit'),
                'info'
            );

            $this->parseMessageQueue();
        }

        // Save the index entrys in database if debug is off
        if (!empty($this->newIndexedFiles)) {
            $this->saveCacheIndex();
        }

        $this->app->setBody($this->getHtmlBuffer());
    }

    /**
     * Get the rendered HTML before be outputed
     *
     * @return  string
     *
     * @since   2.0.0
     */
    private function getHtmlBuffer()
    {
        if (null === $this->htmlBuffer) {
            $this->htmlBuffer = $this->app->getBody();
        }

        return $this->htmlBuffer;
    }

    /**
     * Set the parsed HTML buffer before be outputed
     *
     * @param   array         $searches  Array of values to search in the HTML buffer
     * @param   string|array  $replaces  Array of values to set in the HTML buffer
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function setNewHtmlBuffer($searches, $replaces)
    {
        if (empty($searches)) {
            return;
        }

        $buffer           = $this->getHtmlBuffer();
        $this->htmlBuffer = preg_replace($searches, $replaces, $buffer);
    }

    /**
     * Parse head links of special templates
     *
     * @return  void
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    private function parseHeadLinks()
    {
        $searches = array();
        $replaces = array();

        JtaldefHelper::setServiceTriggerList();

        $items = $this->getLinkedStylesheetsFromHead();

        foreach ($items as $item) {
            $url        = $item->attributes()['href']->asXML();
            $url        = trim(str_replace(array('href=', '"', "'"), '', $url));
            $searchPath = parse_url($url, PHP_URL_PATH);
            $isExternal = JtaldefHelper::isExternalUrl($url);

            if ($isExternal) {
                $searchQuery = parse_url($url, PHP_URL_QUERY);
            }

            $newUrl = $this->getNewFilePath($url);

            $item->addAttribute('data-jtaldef-processed', self::JTALDEF_VERSION);

            if (false !== $newUrl) {
                $item->attributes()['href'] = $newUrl;
            }

            $replace = $item->asXML();

            // Create searches and replacements

            $search = $searchPath;

            if ($isExternal) {
                $search     = $searchPath . '?' . $searchQuery;
                $search    = str_replace(array('?', '+'), array('\\?', '\\+'), $search);
                $search2    = str_replace('&amp;', '&', $search);
                $searches[] = '%<link\s+(?:[^>]*?\s+)?href=(["\'])(?:[^\1].*)?' . $search2 . '(?:[^\1].*)?\1(?:[^>].*)?>%';
                $replaces[] = $replace;
            }

            $searches[] = '%<link\s+(?:[^>]*?\s+)?href=(["\'])(?:[^\1].*)?' . $search . '(?:[^\1].*)?\1(?:[^>].*)?>%';
            $replaces[] = $replace;
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
     * @since   2.0.0
     */
    private function parseInlineStyles($ns)
    {
        $searches = array();
        $replaces = array();

        // Get styles from XML buffer
        $styles = $this->getXmlBuffer($ns);

        foreach ($styles as $style) {
            $search = (string) $style;

            // Parse the inline style
            $newStyle = JtaldefHelper::getNewFileContentLink($search, 'ParseStyle');

            if (false === $newStyle) {
                continue;
            }

            // Create searches and replacements
            $searches[] = '%' . $search . '%';
            $replaces[] = $newStyle;
        }

        $this->setNewHtmlBuffer($searches, $replaces);
    }

    /**
     * Parse head links of special templates
     *
     * @return  void
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    private function parseHeadStylesheetsBeforeCompiled()
    {
        $newStyleSheets = array();
        $document       = $this->app->getDocument();

        foreach ($document->_styleSheets as $url => $options) {
            if (isset($options['data-jtaldef-processed'])) {
                $newStyleSheets[$url] = $options;

                continue;
            }

            $newUrl = $this->getNewFilePath($url);
            $newUrl = empty($newUrl) ? $url : $newUrl;

            $options['data-jtaldef-processed'] = self::JTALDEF_VERSION;
            $newStyleSheets[$newUrl]           = $options;
        }

        $document->_styleSheets = $newStyleSheets;
    }

    /**
     * Parse head links of special templates
     *
     * @return  void
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    private function parseHeadScriptsBeforeCompiled()
    {
        $newScripts = array();
        $document   = $this->app->getDocument();

        foreach ($document->_scripts as $url => $options) {
            if (isset($options['data-jtaldef-processed'])) {
                $newScripts[$url] = $options;

                continue;
            }

            $newUrl = $this->getNewFilePath($url);
            $newUrl = empty($newUrl) ? $url : $newUrl;

            $options['data-jtaldef-processed'] = self::JTALDEF_VERSION;
            $newScripts[$newUrl]               = $options;
        }

        $document->_scripts = $newScripts;
    }

    /**
     * Get the new file path
     *
     * @param   string  $value  Url to parse
     *
     * @return  string|boolean  Returns false on error
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    private function getNewFilePath($value)
    {
        $value         = JtaldefHelper::normalizeUrl($value);
        $isExternalUrl = JtaldefHelper::isExternalUrl($value);

        if ($isExternalUrl) {
            $isUrlSchemeAllowed = JtaldefHelper::isUrlSchemeAllowed($value);

            if (!$isUrlSchemeAllowed && JtaldefHelper::$debug) {
                $this->app->enqueueMessage(
                    Text::sprintf('PLG_SYSTEM_JTALDEF_URL_SCHEME_NOT_ALLOWED', $value),
                    'warning'
                );

                return false;
            }
        }

        // Remove unneeded query on internal file path
        if (!$isExternalUrl) {
            $value = JtaldefHelper::removeBasePath($value);
        }

        $originalId = md5($value);

        // Searching the indexes
        $indexes   = $this->getCacheIndex();
        $isIndexed = in_array($originalId, array_keys($indexes));

        // Is triggered if we have a indexed entry
        if ($isIndexed) {
            // Return the cached file path
            return $indexes[$originalId];
        }

        $process            = false;
        $newCssFile         = false;
        $downloadService    = null;
        $parseLocalCssFiles = $this->params->get('parseLocalCssFiles', true);
        $fileExt            = pathinfo($value, PATHINFO_EXTENSION);

        if (strpos($fileExt, '?') !== false) {
            $fileExt = strstr($fileExt, '?', true);
        }

        if (!$isExternalUrl && $parseLocalCssFiles && $fileExt === 'css') {
            $process         = true;
            $downloadService = 'ParseCss';
        }

        if ($isExternalUrl && JtaldefHelper::getServiceByLink($value) !== false) {
            $process = true;
        }

        // Is triggered if we have no cached entry but a class to handle it
        if (!$isIndexed && $process) {
            $newCssFile = JtaldefHelper::getNewFileContentLink($value, $downloadService);
        }

        // Register new cache entry
        if (empty($newCssFile)) {
            $newCssFile = false;
        }

        $this->addNewCacheEntry($originalId, $newCssFile);

        return $newCssFile;
    }

    /**
     * Load indexed files
     *
     * @return  array
     *
     * @since   2.0.0
     */
    private function getCacheIndex()
    {
        $indexedFiles = $this->indexedFiles;

        if (empty($indexedFiles)
            && file_exists(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex')
        ) {
            $indexedFiles = (array) json_decode(
                @file_get_contents(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex'),
                true
            );
        }

        return $this->indexedFiles = $indexedFiles;
    }

    /**
     * Add new downloaded file to cache
     *
     * @param   string  $originalId     The identifier of the original file
     * @param   string  $localFilePath  The local path of the downloaded file
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function addNewCacheEntry($originalId, $localFilePath)
    {
        if (empty($originalId)) {
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
     * @since   2.0.0
     */
    private function saveCacheIndex()
    {
        $newCachedFiles = $this->newIndexedFiles;

        if (!empty($newCachedFiles)) {
            $newCachedFiles = array_merge($this->getCacheIndex(), $newCachedFiles);
            $newCachedFiles = json_encode($newCachedFiles);

            if (!is_dir(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD)) {
                Folder::create(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD);
            }

            @file_put_contents(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex', $newCachedFiles);
        }
    }

    /**
     * Check valid AJAX request
     *
     * @return  boolean
     *
     * @since   2.0.0
     */
    private function isAjaxRequest()
    {
        return strtolower($this->app->input->server->get('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Ajax methode
     *
     * @return  void
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    public function onAjaxJtaldefClearCache()
    {
        $accessDenied = Text::_('JGLOBAL_AUTH_ACCESS_DENIED');

        if (!$this->app->getSession()->checkToken()) {
            throw new \InvalidArgumentException(
                Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_TOKEN', $accessDenied),
                403
            );
        }

        if (!$this->isAjaxRequest()) {
            throw new \InvalidArgumentException(
                Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_ERROR_AJAX_REQUEST', $accessDenied),
                403
            );
        }

        $clearIndex = Folder::delete(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD);

        if (!$clearIndex) {
            throw new \InvalidArgumentException(Text::_('PLG_SYSTEM_JTALDEF_CLEAR_INDEX_ERROR'), 500);
        }
    }

    /**
     * Deletes invalid UTF-8 characters from a string, before it generates the XML.
     *
     * @param   string  $string  The string to clear.
     *
     * @return  string
     *
     * @since   2.0.0
     */
    private function stripInvalidXmlCharacters($string)
    {
        if (!empty($string)) {
            $string = preg_replace('/[^[:print:]\r\n\t]/u', '', $string);

            // remove EOT+NOREP+EOX|EOT+<char> sequence (FatturaPA)
            $string = preg_replace(
                '/(\x{0004}(?:\x{201A}|\x{FFFD})(?:\x{0003}|\x{0004}).)/u',
                '',
                $string
            );

            $regex  = '@(
            [\xC0-\xC1] // Invalid UTF-8 Bytes
            | [\xF5-\xFF] // Invalid UTF-8 Bytes
            | \xE0[\x80-\x9F] // Overlong encoding of prior code point
            | \xF0[\x80-\x8F] // Overlong encoding of prior code point
            | [\xC2-\xDF](?![\x80-\xBF]) // Invalid UTF-8 Sequence Start
            | [\xE0-\xEF](?![\x80-\xBF]{2}) // Invalid UTF-8 Sequence Start
            | [\xF0-\xF4](?![\x80-\xBF]{3}) // Invalid UTF-8 Sequence Start
            | (?<=[\x0-\x7F\xF5-\xFF])[\x80-\xBF] // Invalid UTF-8 Sequence Middle
            | (?<![\xC2-\xDF]|[\xE0-\xEF]|[\xE0-\xEF][\x80-\xBF]|[\xF0-\xF4]
                |[\xF0-\xF4][\x80-\xBF]|[\xF0-\xF4][\x80-\xBF]{2})[\x80-\xBF] // Overlong Sequence
            | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF]) // Short 3 byte sequence
            | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2}) // Short 4 byte sequence
            | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF]) // Short 4 byte sequence
        )@x';
            $string = preg_replace($regex, '', $string);
        }

        return $string;
    }

    /**
     * Get the HTML buffer as XML or the nodes passed by param
     *
     * @param   string  $ns  Xpath namespace to return
     *
     * @return  array|\SimpleXMLElement[]
     *
     * @since   2.0.0
     */
    private function getXmlBuffer($ns = null)
    {
        $error   = false;
        $matches = array();

        // Get html buffer
        $htmlBuffer = $this->getHtmlBuffer();

        if (empty($htmlBuffer)) {
            return array();
        }

        $htmlBuffer = str_replace('xmlns=', 'ns=', $htmlBuffer);

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);

        $dom->loadHTML($htmlBuffer);
        libxml_clear_errors();

        try {
            $xmlString = $dom->saveXML($dom->getElementsByTagName('html')->item(0));
        } catch (\Throwable $e) {
            $error = true;
        } catch (\Exception $e) {
            $error = true;
        }

        if (!$error) {
            $xmlString = $this->stripInvalidXmlCharacters($xmlString);

            try {
                $xmlBuffer = new \SimpleXMLElement($xmlString);
            } catch (\Throwable $e) {
                $error = true;
            } catch (\Exception $e) {
                $error = true;
            }
        }

        if ($error) {
            $this->app->enqueueMessage(
                $e->getMessage(),
                'error'
            );

            return array();
        }

        if (null !== $ns && !empty($xmlBuffer)) {
            $matches = (array) $xmlBuffer->xpath($ns);
        }

        return $matches;
    }

    /**
     * Find linked stylesheets in the head by namespace
     *
     * @return  array|\SimpleXMLElement[]
     *
     * @since   2.0.0
     */
    private function getLinkedStylesheetsFromHead()
    {
        $hrefs              = array();
        $contains           = array();
        $serviceTriggerList = JtaldefHelper::$serviceTriggerList;

        $contains[] = "contains(@href,'.css')";

        foreach ($serviceTriggerList as $trigger) {
            $contains[] = "contains(@href,'" . $trigger . "')";
        }

        $contains[] = "@rel='lazy-stylesheet'";
        $contains[] = "@rel='stylesheet'";

        $namespace = "//head//*[" . implode(' or ', $contains) . "][not(contains(@data-jtaldef-processed,'" . self::JTALDEF_VERSION . "'))]";

        $hrefs = array_merge($hrefs, $this->getXmlBuffer($namespace));

        return $hrefs;
    }

    /**
     * Remove not parsed links in the head
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function removeNotParsedFromDom($namespace)
    {
        $searches = array();
        $replaces = array();

        $hrefs = $this->getXmlBuffer($namespace);

        foreach ($hrefs as $href) {
            $nodeName = $href->getName();

            switch ($nodeName) {
                case 'script':
                    $call = 'src';
                    break;
                default:
                    $call = 'href';
                    break;
            }

            $url    = $href->attributes()[$call]->asXML();
            $url    = trim(str_replace(array($call . '=', '"', "'"), '', $url));
            $search = parse_url($url, PHP_URL_PATH);

            if (JtaldefHelper::isExternalUrl($url)) {
                $search = parse_url($url, PHP_URL_HOST);
            }

            // Define the regex to search for.
            $regex = '%(<' . $nodeName . '\s+(?:[^>]*?\s+)?';
            $regex .= $call . '=(["\'])[^>]*?(' . $search . ')[^>]*?\2[^>]*?>)%';

            // Define an unique ID for the regex.
            $id = md5($regex);

            // Add the regex to the searches
            if (!array_key_exists($id, $searches)) {
                $searches[$id] = $regex;
            }
        }

        $replaces = Text::sprintf('PLG_SYSTEM_JTALDEF_DISABLED_BY', '$1');

        $this->setNewHtmlBuffer($searches, $replaces);
    }

    /**
     * Parse message queue as javascript into <head>
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function parseMessageQueue()
    {
        // Get rendered system message output
        $oldMessagesOutput = $this->getXmlBuffer("//body//*[@id='system-message-container']");
        $jsonMessageQueue  = $this->getJsonMessageQueue();

        if (!empty($oldMessagesOutput) && !empty($jsonMessageQueue)) {
            $search = array('%</head>%');

            if (version_compare(JVERSION, '4', 'lt')) {
                $replace = array(
                    "\t" . '<script data-jtaldef-processed="joomla-messages">'
                    . 'document.addEventListener("DOMContentLoaded", () => {'
                    . 'Joomla.renderMessages(' . $jsonMessageQueue . ');'
                    . '});'
                    . '</script>'
                    . "\n" . '</head>',
                );
            } else {
                $messageQueue                      = new stdClass;
                $messageQueue->{'joomla.messages'} = array(json_decode($jsonMessageQueue));
                $messageQueue                      = json_encode($messageQueue);
                $replace                           = array(
                    "\t" . '<script class="joomla-script-options new"'
                    . ' type="application/json"'
                    . ' data-jtaldef-processed="joomla-messages">'
                    . $messageQueue
                    . '</script>'
                    . "\n" . '</head>',
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
     * @since   2.0.0
     */
    private function getJsonMessageQueue()
    {
        $messages = array();
        $queue    = $this->app->getMessageQueue();

        foreach ($queue as $message) {
            $messages[$message['type']][] = $message['message'];
        }

        return json_encode($messages);
    }
}
