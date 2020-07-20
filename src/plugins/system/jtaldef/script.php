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

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;

/**
 * Script file of Joomla CMS
 *
 * @since  3.0.0
 */
class PlgSystemJtaldefInstallerScript
{
	/**
	 * Minimum Joomla version to install
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $minimumJoomla = '3.9';

	/**
	 * Minimum PHP version to install
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $minimumPhp = '5.6';

	/**
	 * Function to act prior the installation process begins
	 *
	 * @param   string      $action     Which action is happening (install|uninstall|discover_install|update)
	 * @param   JInstaller  $installer  The class calling this method
	 *
	 * @return  boolean
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function preflight($action, $installer)
	{
		$app = Factory::getApplication();

		Factory::getLanguage()->load('plg_system_jtaldef', dirname(__FILE__));

		if (version_compare(PHP_VERSION, $this->minimumPhp, 'lt'))
		{
			$app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_MINPHPVERSION', $this->minimumPhp), 'error');

			return false;
		}

		if (version_compare(JVERSION, $this->minimumJoomla, 'lt'))
		{
			$app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_MINJVERSION', $this->minimumJoomla), 'error');

			return false;
		}

		return true;
	}
}
