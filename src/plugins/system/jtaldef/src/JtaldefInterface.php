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

namespace Jtaldef;

defined('_JEXEC') or die;

/**
 * @property-read   string    $name                             The real name of the Service.
 * @property-read   boolean   $parseScripts                     Trigger to parse <script/> tags.
 * @property-read   string[]  $stringsToTrigger                 List of URL's to trigger the service.
 * @property-read   string[]  $nsToRemoveNotParsedItemsFromDom  List of namespaces to remove matches from DOM if not
 *                                                              parsed.
 * @since  2.0.0
 */
interface JtaldefInterface
{
    /**
     * Constructor
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function __construct();

    /**
     * Description
     *
     * @param   string  $link  Link to parse.
     *
     * @return  string|boolean   False if no font info is set in the query else the local path to the css file.
     * @throws  \Exception
     *
     * @since   2.0.0
     */
    public function getNewFileContentLink($link);

    /**
     * Get the real name of the Service.
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getRealServiceName();

    /**
     * Trigger to parse <script/> tags
     *
     * @return  boolean
     *
     * @since   2.0.0
     */
    public function parseScripts();

    /**
     * Get the list of values to trigger the service.
     *
     * @return  string[]
     *
     * @since   2.0.0
     */
    public function getListToTriggerService();

    /**
     * Get the list of namespaces to remove matches from DOM if not parsed.
     *
     * @return  string[]
     *
     * @since   2.0.0
     */
    public function getNsToRemoveNotParsedItemsFromDom();
}
