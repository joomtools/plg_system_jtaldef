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

namespace JoomTools\Plugin\System\Jtaldef\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use JoomTools\Plugin\System\Jtaldef\Helper\JtaldefHelper;
use JoomTools\Plugin\System\Jtaldef\JtaldefAwareTrait;
use JoomTools\Plugin\System\Jtaldef\JtaldefInterface;

/**
 * Download and save Fontawsome
 *
 * @since  2.0.0
 */
class FontAwesome implements JtaldefInterface
{
    use JtaldefAwareTrait {
        JtaldefAwareTrait::getNewFileContentLink as getNewFileContentLinkTrait;
    }

    /**
     * Base URL to download fonts data.
     *
     * @var    string
     * @since  2.0.0
     */
    private $downloadBaseUrl;

    /**
     * Version of the Font.
     *
     * @var    string
     * @since  2.0.0
     */
    private $fontVersion;

    /**
     * The downloaded content for the font file (css or js).
     *
     * @var    string
     * @since  2.0.0
     */
    private $fontContent;

    /**
     * List of font names to download.
     *
     * @var    string[]
     * @since  2.0.0
     */
    private $fontNames = [];

    /**
     * Constructor
     *
     * @return   void
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        // The real name of the Service.
        $this->set('name', 'Font Awesome');

        // Trigger to parse <script/> tags.
        $this->set('parseScripts', true);

        // List of values to trigger the service.
        $this->set(
            'stringsToTrigger',
            ['pro.fontawesome.com', 'use.fontawesome.com']
        );

        // List of namespaces to remove matches from DOM if not parsed.
        $this->set(
            'nsToRemoveNotParsedItemsFromDom',
            ["//*[contains(@href,'fontawesome.com') or contains(@src,'fontawesome.com')]"]
        );
    }

    /**
     * Description
     *
     * @param   string  $link  Link to parse.
     *
     * @return  string|boolean  False if no font info is set in the query else the local path to the css file
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    public function getNewFileContentLink($link)
    {
        $link = $this->getNewFileContentLinkTrait($link);

        // Parse the URL
        $parsedUrl             = parse_url($link);
        $path                  = explode('/', trim($parsedUrl['path'], '\\/'));
        $this->fontVersion     = $path[1];
        $this->downloadBaseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/' . $path[0];

        if (!$this->fontContent = $this->downloadContent($path[3])) {
            return false;
        }

        $this->parseCssForFontNames();

        if (!$this->downloadFonts()) {
            return false;
        }

        $filename = 'fontawesome-' . $this->fontVersion . '-' . $path[3];

        return JtaldefHelper::saveFile($filename, $this->fontContent);
    }

    /**
     * Parse CSS to get the font names to download.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function parseCssForFontNames()
    {
        $fontNames = [];
        $css       = $this->fontContent;
        $pattern1  = '%@font\-face\s?{(.*)}%Uu';
        $pattern2  = '%url\((.*)\)\s|;?%Uu';

        if (preg_match_all($pattern1, $css, $matches)) {
            foreach ($matches[1] as $match) {
                $array = explode(';', $match);

                foreach ($array as $value) {
                    if (
                        strpos($value, 'src') === false
                        || !preg_match_all($pattern2, $value, $fontPaths)
                    ) {
                        continue;
                    }

                    $fontPaths = array_filter($fontPaths[1]);

                    foreach ($fontPaths as $fontPath) {
                        $fontPath    = explode('/', $fontPath);
                        $fontNames[] = array_pop($fontPath);
                    }
                }
            }

            $this->fontNames = ArrayHelper::arrayUnique($fontNames);
        }
    }

    /**
     * Download the Fontawesome CSS to local filesystem
     *
     * @param   string  $filename  The CSS name for download the CSS.
     *
     * @return  string|bool
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    private function downloadContent($filename)
    {
        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);

        // Define the URL to download the CSS file
        $url = $this->downloadBaseUrl . '/' . $this->fontVersion . '/' . $fileExt . '/' . $filename;

        // Download the CSS file
        try {
            $response   = JtaldefHelper::getHttpResponseData($url);
            $statusCode = $response->getStatusCode();
            $content    = (string) $response->getBody();
        } catch (\RuntimeException $e) {
            if (JtaldefHelper::$debug) {
                Factory::getApplication()->enqueueMessage(
                    sprintf(
                        "<strong>" . addslashes(__METHOD__) . "():</strong>"
                            . "<br /><strong>URL:</strong> %s"
                            . "<br />%s",
                        $url,
                        $e->getMessage()
                    ),
                    'error'
                );
            }

            return false;
        }

        if ($statusCode < 200 || $statusCode >= 400 || empty($content)) {
            if (JtaldefHelper::$debug) {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf(
                        'PLG_SYSTEM_JTALDEF_ERROR_WHILE_DOWNLOADING_FONT',
                        'Fontawesome',
                        $url,
                        $content
                    ),
                    'error'
                );
            }

            return false;
        }

        return $content;
    }

    /**
     * Download Fontawesome to local filesystem
     *
     * @return  string|bool
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    private function downloadFonts()
    {
        $search  = [];
        $replace = [];
        $urlBase = $this->downloadBaseUrl . '/' . $this->fontVersion . '/webfonts';

        foreach ($this->fontNames as $fontName) {
            $filename = 'fontawesome-' . $this->fontVersion . '-' . $fontName;
            $filePath = JtaldefHelper::getCacheFilePath($filename);

            if (!file_exists(JPATH_ROOT . '/' . $filePath)) {
                try {
                    $response   = JtaldefHelper::getHttpResponseData($urlBase . '/' . $fontName);
                    $statusCode = $response->getStatusCode();
                    $content    = (string) $response->getBody();
                } catch (\RuntimeException $e) {
                    if (JtaldefHelper::$debug) {
                        Factory::getApplication()->enqueueMessage(
                            sprintf(
                                "<strong>" . addslashes(__METHOD__) . "():</strong>"
                                . "<br /><strong>URL:</strong> %s"
                                . "<br />%s",
                                $urlBase . '/' . $fontName,
                                $e->getMessage()
                            ),
                            'error'
                        );
                    }

                    return false;
                }

                if ($statusCode < 200 || $statusCode >= 400 || empty($content)) {
                    if (JtaldefHelper::$debug) {
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf(
                                'PLG_SYSTEM_JTALDEF_ERROR_WHILE_DOWNLOADING_FONT',
                                'Fontawesome',
                                $urlBase . '/' . $fontName,
                                $content
                            ),
                            'error'
                        );
                    }

                    return false;
                }

                $filePath = JtaldefHelper::saveFile($filename, $content);

                $search[]  = '../webfonts/' . $fontName;
                $replace[] = Uri::base(true) . '/' . $filePath;
            }
        }

        $this->fontContent = str_replace($search, $replace, $this->fontContent);

        return true;
    }
}
