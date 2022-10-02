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

use Joomla\CMS\Filter\InputFilter;

/**
 *
 * @since   __DEPLOY_VERSION__
 */
trait JtaldefAwareTrait
{
	/**
	 * The real name of the Service.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private $name;

	/**
	 * Trigger to parse <script/> tags.
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	private $parseScripts = false;

	/**
	 * List of values to trigger the service.
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	private $stringsToTrigger = array();

	/**
	 * List of namespaces to remove matches from DOM if not parsed.
	 *
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	private $nsToRemoveNotParsedItemsFromDom = array();

	/**
	 * Constructor
	 *
	 * @return   void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct()
	{
		// The real name of the Service.
		$this->set('name', '');

		// Trigger to parse <script/> tags.
		$this->set('parseScripts', false);

		// List of values to trigger the service.
		$this->set(
			'stringsToTrigger',
			array()
		);

		// List of namespaces to remove matches from DOM if not parsed.
		$this->set(
			'nsToRemoveNotParsedItemsFromDom',
			array()
		);
	}

	/**
	 * Description
	 *
	 * @param   string  $link  Link to parse.
	 *
	 * @return  string|boolean  False if no font info is set in the query else the local path to the css file.
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNewFileContentLink($value)
	{
		return trim(InputFilter::getInstance()->clean($value));
	}

	/**
	 * Get the real name of the Service.
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getRealServiceName()
	{
		return $this->name;
	}

	/**
	 * Trigger to parse <script/> tags.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function parseScripts()
	{
		return $this->parseScripts;
	}

	/**
	 * Get the list of values to trigger the service.
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getListToTriggerService()
	{
		return $this->stringsToTrigger;
	}

	/**
	 * Get the list of namespaces to remove matches from DOM if not parsed.
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getNsToRemoveNotParsedItemsFromDom()
	{
		return $this->nsToRemoveNotParsedItemsFromDom;
	}

	private function set($property, $value)
	{
		switch ($property)
		{
			case 'name':
				$this->$property = (string) $value;
				break;

			case 'parseScripts':
				$this->$property = ($value === true);
				break;

			case 'stringsToTrigger':
			case 'nsToRemoveNotParsedItemsFromDom':
				$this->$property = (array) $value;
				break;

			default:
				break;
		}
	}
}