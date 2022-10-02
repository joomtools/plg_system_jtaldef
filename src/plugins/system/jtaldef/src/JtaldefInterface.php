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
 * @property-read   string    $name                             The real name of the Service.
 * @property-read   boolean   $parseScripts                     Trigger to parse <script/> tags.
 * @property-read   string[]  $stringsToTrigger                 List of URL's to trigger the service.
 * @property-read   string[]  $nsToRemoveNotParsedItemsFromDom  List of namespaces to remove matches from DOM if not parsed.
 *
 * @since   __DEPLOY_VERSION__
 */
interface JtaldefInterface
{
	/**
	 * Constructor
	 *
	 * @return   void
	 *
	 * @since   __DEPLOY_VERSION__
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
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNewFileContentLink($link);

	/**
	 * Get the real name of the Service.
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getRealServiceName();

	/**
	 * Trigger to parse <script/> tags
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function parseScripts();

	/**
	 * Get the list of values to trigger the service.
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getListToTriggerService();

	/**
	 * Get the list of namespaces to remove matches from DOM if not parsed.
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNsToRemoveNotParsedItemsFromDom();
}