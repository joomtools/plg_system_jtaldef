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

namespace Jtaldef\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Jtaldef\Helper\JtaldefHelper;
use Jtaldef\JtaldefAwareTrait;
use Jtaldef\JtaldefInterface;

/**
 * Download and save Google Fonts
 *
 * @since  2.0.4
 */
class GoogleFontsFontsource implements JtaldefInterface
{
    use JtaldefAwareTrait {
        JtaldefAwareTrait::getNewFileContentLink as getNewFileContentLinkTrait;
    }

    /**
     * URL to fonts API.
     *
     * @var    string
     * @since  2.0.4
     */
    const GF_DATA_API = 'https://api.fontsource.org/v1/fonts';

    /**
     * All the Google Fonts data for the font.
     *
     * @var    array
     * @since  2.0.4
     */
    private static $googleFontsJson = array();

    /**
     * Font name of the Google Font.
     *
     * @var    string
     * @since  2.0.4
     */
    private $fontName;

    /**
     * Font name of the Google Font.
     *
     * @var    array
     * @since  2.0.4
     */
    private $variants;

    /**
     * Subsets of the Google Font.
     *
     * @var    array
     * @since  2.0.4
     */
    private $fontsSubsets;

    /**
     * Value of font-display for the Google Font.
     *
     * @var    string
     * @since  2.0.4
     */
    private $fontsDisplay;

    /**
     * Font data collected from API - via JSON for this font.
     *
     * @var    array
     * @since  2.0.4
     */
    private $fontData;

    /**
     * Constructor
     *
     * @return   void
     *
     * @since   2.0.4
     */
    public function __construct()
    {
        // The real name of the Service.
        $this->set('name', 'Google Fonts (Fontsource)');

        // Trigger to parse <script/> tags.
        $this->set('parseScripts', false);

        // List of values to trigger the service.
        $this->set(
            'stringsToTrigger',
            array(
                'fonts.googleapis.com',
            )
        );

        // List of namespaces to remove matches from DOM if not parsed.
        $this->set(
            'nsToRemoveNotParsedItemsFromDom',
            array(
                "//*[contains(@href,'fonts.gstatic.com') or contains(@href,'fonts.googleapis.com')]",
                "//*[contains(@src,'fonts.gstatic.com') or contains(@src,'fonts.googleapis.com')]",
            )
        );
    }

    /**
     * Description
     *
     * @param   string  $link  Link to parse.
     *
     * @return  string      False if no font info is set in the query else the local path to the css file.
     * @throws  \Exception  If the file couldn't be saved.
     *
     * @since   2.0.4
     */
    public function getNewFileContentLink($link)
    {
        $css   = array();
        $link  = $this->getNewFileContentLinkTrait($link);
        $fonts = $this->getFontInfoByQuery($link);

        if (!$fonts) {
            return false;
        }

        $this->fontsSubsets = $fonts['subsets'];
        $this->fontsDisplay = $fonts['display'] ?? null;

        foreach ($fonts['families'] as $font) {
            $this->fontName = $font['name'];
            $this->variants = $font['variants'];
            $this->fontData = $this->getGoogleFontsJson();

            // Generate the CSS for this family
            $css = array_merge(
                $css,
                $this->generateCss()
            );
        }

        if (empty($css)) {
            return false;
        }

        $css      = implode(PHP_EOL, $css);
        $filename = md5($css) . '.css';

        return JtaldefHelper::saveFile($filename, $css);
    }

    /**
     * Get fonts credentials
     *
     * @param   string  $url  Url to parse
     *
     * @return  array|boolean  Return false if no query is set
     *
     * @since   2.0.4
     */
    private function getFontInfoByQuery($url)
    {
        $url = JtaldefHelper::normalizeUrl($url);

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
     * @since   2.0.4
     */
    private function parseFontsQuery($query)
    {
        $return     = array();
        $parsedList = array();
        $subsets    = array();

        // Filter empty values coming from && produced by Joomla51 themes
        $parsedProcessing = array_filter(explode('&', $query));

        foreach ($parsedProcessing as $var) {
            list($key, $value) = explode('=', $var);

            $key   = trim($key);
            $value = trim($value);

            if ($key == 'family') {
                if (false === strpos($value, '|')) {
                    $parsedList[$key][] = $value;
                    $parsedList[$key]   = array_filter($parsedList[$key]);

                    continue;
                }

                $value            = explode('|', $value);
                $parsedList[$key] = array_filter(array_map('trim', $value));

                continue;
            }

            $parsedList[$key] = $value;
        }

        $families = $parsedList['family'];

        if (!empty($parsedList['subset'])) {
            $subsets = explode(',', $parsedList['subset']);
        }

        // Define 'swap' as the default font-display, if there isn't set by URL
        $return['display'] = 'swap';

        if (!empty($parsedList['display'])) {
            $return['display'] = $parsedList['display'];
        }

        // Parse variants/weights and font names
        foreach ($families as $k => $font) {
            $variantsList = array();
            $fontQuery    = explode(':', $font);
            $fontName     = trim($fontQuery[0]);

            if (empty($fontQuery[1])) {
                $variantsList['italic'] = array('100', '200', '300', '400', '500', '600', '700', '800', '900');
                $variantsList['normal'] = array('100', '200', '300', '400', '500', '600', '700', '800', '900');
            }

            if (empty($variantsList)) {
                $variantsList = explode(',', $fontQuery[1]);

                if (false !== strpos($fontQuery[1], '@')) {
                    list($styleTypes, $variantsList) = explode('@', $fontQuery[1]);

                    $styleTypes         = array_reverse(explode(',', $styleTypes));
                    $variantsProcessing = explode(';', $variantsList);

                    $variantsList = array();

                    foreach ($variantsProcessing as $variant) {
                        if (false !== strpos($variant, ',')) {
                            list($key, $value) = explode(',', $variant);

                            if (empty($styleTypes[$key])) {
                                continue;
                            }

                            $style = strtolower($styleTypes[$key]);

                            if ($style == 'ital') {
                                $style = 'italic';
                            }

                            if ($style == 'wght') {
                                $style = 'normal';
                            }

                            $variantsList[$style][] = $value;

                            continue;
                        }

                        if (empty($styleTypes[0])) {
                            $style = 'normal';
                        } else {
                            $style = strtolower($styleTypes[0]);
                        }

                        if ($style == 'ital') {
                            $style = 'italic';
                        }

                        if ($style == 'wght') {
                            $style = 'normal';
                        }

                        $variantsList[$style][] = $variant;
                    }
                }
            }

            if (!empty($variantsList) && !isset($variantsList['normal'])) {
                $variantsList = $this->normalizeVariants($variantsList);
            }

            $families[$k] = array(
                'name'     => $fontName,
                'variants' => $variantsList,
            );

            // Third chunk - probably a subset here
            if (!empty($fontQuery[2])) {
                // Split and trim
                $fontSubs = array_map('trim', explode(',', $fontQuery[2]));

                // Add it to the subsets array
                $subsets = array_merge($subsets, $fontSubs);
            }
        }

        // Remove duplicates
        $subsets = array_unique($subsets);

        $return['families'] = $families;
        $return['subsets']  = $subsets;

        return $return;
    }

    /**
     * Load and return the Google Fonts data from google-webfonts-helper.herokuapp.com
     *
     * @param   array  $font
     *
     * @return  array
     *
     * @since   2.0.4
     */
    private function getGoogleFontsJson()
    {
        $fontId = strtolower(str_replace(array(' ', '+'), '-', $this->fontName));

        if (empty(self::$googleFontsJson[$fontId])) {
            $cacheFile = JtaldefHelper::getCacheFilePath($fontId . '.json');

            if (file_exists(JPATH_ROOT . '/' . $cacheFile)) {
                $content = file_get_contents($cacheFile);
            } else {
                $fontApiUrl = self::GF_DATA_API . '/' . $fontId;
                $response   = JtaldefHelper::getHttpResponseData($fontApiUrl);
                $statusCode = $response->code;
                $content    = $response->body;

                if ($statusCode != 200 || empty($content)) {
                    $fontsSubsets = !empty($this->fontsSubsets)
                        ? ' (' . implode(',', $this->fontsSubsets) . ')'
                        : '';

                    if (JtaldefHelper::$debug) {
                        Factory::getApplication()
                            ->enqueueMessage(
                                Text::sprintf(
                                    'PLG_SYSTEM_JTALDEF_ERROR_FONT_NOT_FOUND',
                                    $this->fontName . $fontsSubsets,
                                    $fontApiUrl,
                                    $content
                                ),
                                'error'
                            );
                    }

                    return array();
                }

                JtaldefHelper::saveFile($fontId . '.json', $content);
            }

            $result = json_decode($content, true);

            self::$googleFontsJson[$fontId] = $result;
        }

        return self::$googleFontsJson[$fontId];
    }

    /**
     * Generate CSS based on variants for the subsets
     *
     * @return  array
     * @throws  \Exception
     *
     * @since   2.0.4
     */
    private function generateCss()
    {
        if (empty($this->fontData)) {
            return array();
        }

        $css = array();

        foreach ($this->variants as $style => $weights) {
            if (isset($this->fontData['styles'])
                && !in_array($style, $this->fontData['styles'])
            ) {
                continue;
            }

            foreach ($weights as $weight) {
                if (isset($this->fontData['weights'])
                    && !in_array($weight, $this->fontData['weights'])
                ) {
                    continue;
                }

                // Weight doesn't exist?
                if (!isset($this->fontData['variants'])
                    || empty($data = $this->fontData['variants'][$weight][$style])
                ) {
                    continue;
                }

                // Font data (from JSON)
                $fontData = $this->downloadFonts($data);

                foreach ($fontData as $subset => $urls) {
                    // Common CSS rules to create
                    $rules = array(
                        'font-family: "' . $this->fontData['family'] . '"',
                        'font-weight: ' . (int) $weight,
                        'font-style: ' . $style,
                        'font-display: ' . $this->fontsDisplay,
                    );

                    // Build src array
                    $src = array();

                    $src[] = "url(" . $urls['woff2'] . ") format('woff2')";
                    $src[] = "url(" . $urls['woff'] . ") format('woff')";

                    // Add to rules array
                    $rules[] = 'src: ' . implode(', ', $src);

                    // Set unicode ranges
                    $rules[] = 'unicode-range: ' . $this->fontData['unicodeRange'][$subset];

                    // Add some formatting
                    $rules = array_map(
                        function ($rule) {
                            return "\t" . $rule . ";";
                        },
                        $rules
                    );

                    // Add to final CSS
                    $css[] = "/* " . $subset . " */\n@font-face {\n" . implode("\n", $rules) . "\n}";
                }
            }
        }

        if (!empty($css)) {
            array_unshift($css, '/* ' . $this->fontName . ' (' . implode(',', $this->fontsSubsets) . ') */');
        }

        return $css;
    }

    /**
     * Normalize variants from Google API v1
     *
     * @param   string[]  $variants  The list of variants from Google API v1
     *
     * @return  array
     *
     * @since   2.0.4
     */
    private function normalizeVariants($variants)
    {
        $return = array();

        foreach ($variants as $variant) {
            $style = 'normal';

            if ($variant == 'regular') {
                $variant = '400';
            }

            if ($variant == 'italic') {
                $style   = 'italic';
                $variant = '400';
            }

            if (false !== strpos($variant, 'i')) {
                $variant = str_replace(array('italics', 'italic', 'i'), '', $variant);
                $style   = 'italic';
            }

            // Google API v1 supports bold and b as variants too
            if (false !== stripos($variant, 'b')) {
                $variant = str_replace(array('bold', 'b'), '700', $variant);
            }

            // Fallback to 400
            if (!$variant || (!strstr($variant, 'italic') && !is_numeric($variant))) {
                $variant = '400';
            }

            $return[$style][] = $variant;
        }

        return $return;
    }

    /**
     * Download google fonts to local filesystem.
     *
     * @param   array  $subsetsUrlList  List of subsets containing the Urls for download the font.
     *
     * @return  array       List of subsets containing the local Urls of the downloaded font.
     * @throws  \Exception  If the file couldn't be saved.
     *
     * @since   2.0.4
     */
    private function downloadFonts($subsetsUrlList)
    {
        $newSubsetsUrlList = array();

        if (empty($this->fontsSubsets)) {
            $this->fontsSubsets = (array) $this->fontData['defSubset'] ?? array();
        }

        foreach ($this->fontsSubsets as $subset) {
            if (isset($this->fontData['subsets'])
                && !in_array($subset, (array) $this->fontData['subsets'])
            ) {
                continue;
            }

            foreach ($subsetsUrlList[$subset]['url'] as $type => $url) {
                if (strtolower($type) == 'ttf') {
                    continue;
                }

                // Set the file name
                $safeFileName = File::makeSafe(basename($url));
                $filePath     = JtaldefHelper::getCacheFilePath($safeFileName);

                if (!file_exists(JPATH_ROOT . '/' . $filePath)) {
                    $response   = JtaldefHelper::getHttpResponseData($url);
                    $statusCode = $response->code;
                    $content    = $response->body;

                    if ($statusCode < 200 || $statusCode >= 400 || empty($content)) {
                        // Return an error message if the fonts could not be downloaded

                        if (JtaldefHelper::$debug) {
                            Factory::getApplication()
                                ->enqueueMessage(
                                    Text::sprintf(
                                        'PLG_SYSTEM_JTALDEF_ERROR_WHILE_DOWNLOADING_FONT',
                                        $this->fontName,
                                        $url
                                    ),
                                    'error'
                                );
                        }

                        continue;
                    }

                    $filePath = JtaldefHelper::saveFile($safeFileName, $content);
                }

                $newSubsetsUrlList[$subset][$type] = Uri::base(true) . '/' . $filePath;
            }
        }

        return $newSubsetsUrlList;
    }
}
