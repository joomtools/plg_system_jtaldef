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
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * Script file of Joomla CMS
 *
 * @since  2.0.0
 */
class PlgSystemJtaldefInstallerScript
{
    /**
     * Minimum Joomla version to install
     *
     * @var    string
     * @since  2.0.0
     */
    public $minimumJoomla = '3.10';

    /**
     * Minimum PHP version to install
     *
     * @var    string
     * @since  2.0.0
     */
    public $minimumPhp = '5.6';

    /**
     * Previous version
     *
     * @var    string
     * @since  2.0.0
     */
    private $fromVersion;

    /**
     * New values for the service names to parse
     *
     * @var    array
     * @since  2.0.0
     */
    private $newServiceToParseList = array(
        'fontawesome'               => 'FontAwesome',
        'googlefonts'               => 'GoogleFontsFontsource',
        'googlefontsfontsource'     => 'GoogleFontsFontsource',
        'googlefontsold'            => 'GoogleFontsWebfontshelper',
        'googlefontswebfontshelper' => 'GoogleFontsWebfontshelper',
    );

    /**
     * Function to act prior the installation process begins
     *
     * @param   string     $action     Which action is happening (install|uninstall|discover_install|update)
     * @param   Installer  $installer  The class calling this method
     *
     * @return  boolean
     * @throws  Exception
     *
     * @since   2.0.0
     */
    public function preflight($action, $installer)
    {
        $app = Factory::getApplication();

        Factory::getLanguage()->load('plg_system_jtaldef', dirname(__FILE__));

        if (version_compare(PHP_VERSION, $this->minimumPhp, 'lt')) {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_MINPHPVERSION', $this->minimumPhp), 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, 'lt')) {
            $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_MINJVERSION', $this->minimumJoomla), 'error');

            return false;
        }

        if ($action == 'update' && !empty($installer->extension->manifest_cache)) {
            // Get the version we are updating from
            $manifestValues    = json_decode($installer->extension->manifest_cache, true);
            $this->fromVersion = $manifestValues['version'];
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
     * @since   2.0.0
     */
    public function postflight($action, $installer)
    {
        if ($action == 'update') {
            $indexToClear = array();

            if (version_compare($this->fromVersion, '1.0.0-rc11', 'lt')) {
                // Remove database
                $db = Factory::getDbo();
                $db->setQuery('DROP TABLE IF EXISTS #__jtaldef')->execute();

                // Prior 1.0.0-rc6
                $indexToClear[] = '/media/plg_system_jtaldef/cache';
            }

            // Since 1.0.0-rc6
            $indexToClear[] = '/media/plg_system_jtaldef/index';

            // Since 1.0.7
            $indexToClear[] = '/plugins/system/jtaldef/src/data';

            $this->deleteOrphans('folder', $indexToClear);

            // Since 1.0.14
            $filesToClear[] = '/plugins/system/jtaldef/src/Fontawesome.php';
            $filesToClear[] = '/plugins/system/jtaldef/src/GoogleFonts.php';
            $filesToClear[] = '/plugins/system/jtaldef/src/ParseCss.php';
            $filesToClear[] = '/plugins/system/jtaldef/src/JtaldefHelper.php';

            // Since 2.0.3
            $filesToClear[] = '/plugins/system/jtaldef/src/Service/GoogleFonts.php';
            $filesToClear[] = '/plugins/system/jtaldef/src/Service/GoogleFontsOld.php';

            $this->deleteOrphans('file', $filesToClear);
        }

        if (in_array($action, array('update', 'install')) && $this->updateParams() === false) {
            $app = Factory::getApplication();

            $app->enqueueMessage(Text::_('PLG_SYSTEM_JTALDEF_SERVICE_NOT_UPDATED'), 'error');
        }

        return true;
    }

    /**
     * @param   string  $type     Which type are orphans of (file or folder)
     * @param   array   $orphans  Array of files or folders to delete
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function deleteOrphans($type, array $orphans)
    {
        $app = Factory::getApplication();

        foreach ($orphans as $item) {
            $item = JPATH_ROOT . $item;

            if ($type == 'folder' && (Folder::exists($item) && Folder::delete($item) === false)) {
                $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_NOT_DELETED', $item), 'warning');
            }

            if ($type == 'file' && (File::exists($item) && File::delete($item) === false)) {
                $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_JTALDEF_NOT_DELETED', $item), 'warning');
            }
        }
    }

    private function getPluginDbo()
    {
        $db     = Factory::getDbo();
        $where  = array(
            $db->quoteName('name') . ' = ' . $db->quote('plg_system_jtaldef'),
            $db->quoteName('type') . ' = ' . $db->quote('plugin'),
            $db->quoteName('folder') . ' = ' . $db->quote('system'),
            $db->quoteName('element') . ' = ' . $db->quote('jtaldef'),
        );
        $select = array(
            $db->quoteName('extension_id'),
            $db->quoteName('params'),
        );

        try {
            $result = $db->setQuery(
                $db->getQuery(true)
                    ->select($select)
                    ->from($db->quoteName('#__extensions'))
                    ->where($where)
            )->loadObject();
        } catch (Exception $e) {
            return false;
        }

        return $result;
    }

    private function updateParams()
    {
        $plgDbo = $this->getPluginDbo();

        if ($plgDbo === false) {
            return false;
        }

        $params         = new Registry($plgDbo->params);
        $serviceToParse = $params->get('handlerToParse');

        if (empty($serviceToParse)) {
            $serviceToParse = $params->get('serviceToParse');

            if (!is_array($serviceToParse)) {
                $serviceToParse = explode(',', $serviceToParse);
                $serviceToParse = str_replace(
                    array('"', "'", '[', ']', '\\', ' '),
                    '',
                    $serviceToParse
                );
            }
        }

        $newServiceToParse = array();

        foreach ($serviceToParse as $service) {
            $key = strtolower($service);

            if (array_key_exists($key, $this->newServiceToParseList)) {
                $newServiceToParse[] = $this->newServiceToParseList[$key];
            }
        }

        $params->set('serviceToParse', $newServiceToParse);

        $removeNotParsedFromDom = (int) $params->get('removeNotParsedFromHead', 1);

        $params->set('removeNotParsedFromDom', $removeNotParsedFromDom);
        $params->set('parseHead', '1');

        $parseHeadLinks = $params->get('parseHeadLinks', null);

        if (!is_null($parseHeadLinks)) {
            $params->set('parseHead', $parseHeadLinks);
            $params->remove('parseHeadLinks');
        }

        $db = Factory::getDbo();

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
                    ->where(
                        $db->quoteName('extension_id') . '=' . $db->quote((int) $plgDbo->extension_id)
                    )
            )->execute();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
