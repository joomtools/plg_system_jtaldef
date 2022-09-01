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

abstract class Jtaldef
{
	/**
	 * Name of the Service
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	const NAME = null;

	/**
	 * List of URL's to trigger the service
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	const URLS_TO_TRIGGER = array();

	/**
	 * Trigger to parse <script/> tags
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	const PARSE_SCRIPTS = false;

	/**
	 * Namespaces to remove item from DOM if not parsed.
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	const NS_TO_REMOVE_NOT_PARSED_ITEMS_FROM_DOM = array();

	/**
	 * Description
	 *
	 * @param   string  $link  The Link to parse
	 *
	 * @return  string|boolean   False if no font info is set in the query else the local path to the css file
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNewFileContentLink($link) {}

}