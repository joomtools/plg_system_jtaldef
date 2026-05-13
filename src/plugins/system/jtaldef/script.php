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

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

/**
 * Script file of Joomla CMS
 *
 * @since  2.1.0
 */
return new class implements InstallerScriptInterface {
    /**
     * Minimum Joomla version to install
     *
     * @var    string
     * @since  2.0.0
     */
    private $minimumJoomla = '5.4.5';

    /**
     * Minimum PHP version to install
     *
     * @var    string
     * @since  2.0.0
     */
    private $minimumPhp = '8.1.0';

    /**
     * The version you need to have installed in order to perform the next update
     *
     * @var    string
     * @since  2.1.0
     */
    private $minimumExtensionVersion = '2.0.11';

    /**
     * Previous version
     *
     * @var    string
     * @since  2.0.0
     */
    private $fromVersion;

    /**
     * Function called before extension installation/update/removal procedure commences.
     *
     * @param   string            $type     The type of change (install or discover_install, update, uninstall)
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean  True on success
     *
     * @since   2.1.0
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();

        $app->getLanguage()->load('plg_system_jtaldef', dirname(__FILE__));

        if (version_compare(PHP_VERSION, $this->minimumPhp, 'lt')) {
            $app->enqueueMessage(
                Text::sprintf('PLG_SYSTEM_JTALDEF_MINPHPVERSION', $this->minimumPhp),
                'error'
            );

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, 'lt')) {
            $app->enqueueMessage(
                Text::sprintf('PLG_SYSTEM_JTALDEF_MINJVERSION', $this->minimumJoomla),
                'error'
            );

            return false;
        }

        if ($type == 'update') {
            // Get the version we are updating from

            $installed = $adapter->getParent()->extension;
            $id = $installed->find(['type' => 'plugin', 'folder' => 'system', 'element' => 'jtaldef']);

            if ($id) {
                $installed->load($id);
                $fromVersion = (new Registry($installed->manifest_cache))->get('version');
                $this->fromVersion = $fromVersion;
            }

            if (version_compare($fromVersion, $this->minimumExtensionVersion, 'lt')) {
                $app->enqueueMessage(
                    Text::sprintf('PLG_SYSTEM_JTALDEF_MINEXTVERSION', $this->minimumExtensionVersion),
                    'error'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Function called after extension installation/update/removal procedure commences.
     *
     * @param   string            $type     The type of change (install or discover_install, update, uninstall)
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean  True on success
     *
     * @since   2.1.0
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        $fromVersion  = $this->fromVersion;
        $toClearSince = [
            '2.0.11' => [
                'folder' => [],
                'files' => [
                    '/plugins/system/jtaldef/jtaldef.php',
                ],
            ],
        ];

        if ($type == 'update') {
            $index = array_search($fromVersion, array_keys($toClearSince));

            if ($index !== false) {
                // Keep $fromVersion and the following to clean
                $toClear = array_slice($toClearSince, $index, null, true);

                foreach ($toClear as $version => $listings) {
                    foreach ($listings as $key => $itemsToClear) {
                        if (!empty($itemsToClear)) {
                            $this->deleteOrphaned($key, $itemsToClear);
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param   string  $type     Which type are orphaned of (file or folder)
     * @param   array   $orphaned  Array of files or folders to delete
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function deleteOrphaned(string $type, array $orphaned)
    {
        $app = Factory::getApplication();

        foreach ($orphaned as $item) {
            $item = JPATH_ROOT . $item;

            if ($type == 'folder' && (Folder::exists($item) && Folder::delete($item) === false)) {
                $app->enqueueMessage(
                    Text::sprintf('PLG_SYSTEM_JTALDEF_NOT_DELETED', $item),
                    'warning'
                );
            }

            if ($type == 'file' && (File::exists($item) && File::delete($item) === false)) {
                $app->enqueueMessage(
                    Text::sprintf('PLG_SYSTEM_JTALDEF_NOT_DELETED', $item),
                    'warning'
                );
            }
        }
    }

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }
    
    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }
};
