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
use Joomla\CMS\Installer\Installer;
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
	 * Previous version
	 *
	 * @var     string
	 * @since   1.0.0
	 */
	private $fromVersion;

	/**
	 * Function to act prior the installation process begins
	 *
	 * @param   string     $action     Which action is happening (install|uninstall|discover_install|update)
	 * @param   Installer  $installer  The class calling this method
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

		if ($action == 'update')
		{
			// Get the version we are updating from
			if (!empty($installer->extension->manifest_cache))
			{
				$manifestValues = json_decode($installer->extension->manifest_cache, true);

				if (array_key_exists('version', $manifestValues))
				{
					$this->fromVersion = $manifestValues['version'];
				}
			}
		}

		return true;
	}

	/**
	 * Called after any type of action
	 *
	 * @param   string     $action     Which action is happening (install|uninstall|discover_install|update)
	 * @param   Installer  $installer  The class calling this method
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.0.0
	 */
	public function postflight($action, $installer)
	{
		if ($action == 'update')
		{
			$indexToClear = array();

			if (version_compare($this->fromVersion, '1.0.0-rc11', 'lt'))
			{
				// Remove database
				$db    = Factory::getDbo();
				$db->setQuery('DROP TABLE IF EXISTS #__jtaldef')->execute();

				// Prior 1.0.0-rc6
				$indexToClear[] = '/media/plg_system_jtaldef/cache';
			}

			// Since 1.0.0-rc6
			$indexToClear[] = '/media/plg_system_jtaldef/index';

			// Since 1.0.7
			$indexToClear[] = '/plugins/system/jtaldef/src/data';

			$this->deleteOrphans('folder', $indexToClear);
		}

		return true;
	}

	/**
	 * @param   string  $type     Which type are orphans of (file or folder)
	 * @param   array   $orphans  Array of files or folders to delete
	 *
	 * @return  void
	 *
	 * @since  1.0.7
	 */
	private function deleteOrphans($type, array $orphans)
	{
		$app = Factory::getApplication();

		foreach ($orphans as $item)
		{
			if ($type == 'folder')
			{
				if (is_dir($item))
				{
					if (Folder::delete($item) === false)
					{
						$app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_NOT_DELETED', $item), 'warning');
					}
				}
			}

			if ($type == 'file')
			{
				if (is_file($item))
				{
					if (File::delete($item) === false)
					{
						$app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_NOT_DELETED', $item), 'warning');
					}
				}
			}
		}
	}
}
