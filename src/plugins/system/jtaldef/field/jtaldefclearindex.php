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

defined('JPATH_PLATFORM') or die;

\JLoader::registerNamespace('Jtaldef', JPATH_PLUGINS . '/system/jtaldef/src', false, false, 'psr4');

use Joomla\CMS\HTML\HTMLHelper;
use Jtaldef\Helper\JtaldefHelper;
use Joomla\CMS\Language\Text;

/**
 * List of supported frameworks
 *
 * @since  1.0.0
 */
class JFormFieldJtaldefClearIndex extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'JtaldefClearIndex';

	/**
	 * Summary of indexed items
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	protected $indexedItems;

	/**
	 * Generate the field output
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getInput()
	{
		$indexedItems = $this->countIndexedItems();
		$disabled = $indexedItems < 1 ? 'class="btn btn-secondary" disabled' : 'class="btn btn-primary"';
		$clickAction = 'index.php?option=com_ajax&group=system&plugin=JtaldefClearIndex&format=json';


		$content = '<p>' . Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_INFO', $indexedItems);
		$content .= '<button id="jtaldefClearIndex" data-action="' . $clickAction . '" ' . $disabled . '>';
		$content .= Text::_('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_LABEL') . '</button></p>';

		HTMLHelper::_('script', 'plg_system_jtaldef/jtaldefClickAction.js', array('version' => 'auto', 'relative' => true));

		return $content;

	}

	/**
	 * Count indexed items
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function countIndexedItems()
	{
		if (null !== $this->indexedItems)
		{
			return $this->indexedItems;
		}

		if (file_exists(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex'))
		{
			$this->indexedItems = count(json_decode(file_get_contents(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD . '/fileindex'), true));
		}

		return (int) $this->indexedItems;
	}

	/**
	 * Generate the label for the field
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getLabel()
	{
		return '';
	}
}
