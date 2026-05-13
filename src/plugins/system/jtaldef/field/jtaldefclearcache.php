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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use JoomTools\Plugin\System\Jtaldef\Helper\JtaldefHelper;

/**
 * List of supported frameworks
 *
 * @since  2.0.0
 */
class JFormFieldJtaldefClearCache extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  2.0.0
     */
    protected $type = 'JtaldefClearCache';

    /**
     * Summary of indexed items
     *
     * @var    integer
     * @since  2.0.0
     */
    protected $indexedItems;

    /**
     * Generate the field output
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getInput()
    {
        $indexedItems = $this->countIndexedItems();
        $disabled     = $indexedItems < 1 ? 'class="btn btn-secondary" disabled' : 'class="btn btn-primary"';
        $clickAction  = 'index.php?option=com_ajax&group=system&plugin=JtaldefClearCache&format=json';

        $content = '<p>' . Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_INFO', $indexedItems);
        $content .= '<button id="jtaldefClearCache" data-action="' . $clickAction . '" ' . $disabled . '>';
        $content .= Text::_('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_LABEL') . '</button></p>';

        /** @var CMSApplication $app */
        $app = Factory::getApplication();

        /** @var WebAssetManager $wa */
        $wa = $app->getDocument()->getWebAssetManager();

        $wa->registerAndUseScript(
            'jtaldefClickAction',
            'plg_system_jtaldef/jtaldefClickAction.js',
            ['version' => 'auto', 'relative' => true],
            [],
            []
        );

        return $content;
    }

    /**
     * Count indexed items
     *
     * @return  integer
     *
     * @since   2.0.0
     */
    private function countIndexedItems()
    {
        if (null !== $this->indexedItems) {
            return $this->indexedItems;
        }

        if (file_exists(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex')) {
            $this->indexedItems = \count(
                json_decode(
                    file_get_contents(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex'),
                    true
                )
            );
        }

        return (int) $this->indexedItems;
    }

    /**
     * Generate the label for the field
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getLabel()
    {
        return '';
    }
}
