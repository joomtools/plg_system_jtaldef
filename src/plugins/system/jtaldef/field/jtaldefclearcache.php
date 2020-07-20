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

JLoader::registerNamespace('Jtaldef', JPATH_PLUGINS . '/system/jtaldef/src', false, false, 'psr4');

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Jtaldef\JtaldefHelper;
use Joomla\CMS\Language\Text;

/**
 * List of supported frameworks
 *
 * @since  1.0.0
 */
class JFormFieldJtaldefClearCache extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'JtaldefClearCache';

	/**
	 * Summary of cached items
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	protected $countCachedItems;

	/**
	 * Generate the field output
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getInput()
	{
		$countCachedItems = $this->countCachedItems();
		$disabled = $countCachedItems < 1 ? 'class="btn btn-secondary" disabled' : 'class="btn btn-primary"';
		$clickAction = 'index.php?option=com_ajax&group=system&plugin=JtaldefClearTrash&format=json';


		$content = '<p>' . Text::sprintf('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_INFO', $countCachedItems);
		$content .= '<button id="jtaldefClearCache" data-action="' . $clickAction . '" ' . $disabled . '>';
		$content .= Text::_('PLG_SYSTEM_JTALDEF_CLEAR_CACHE_LABEL') . '</button></p>';

		HTMLHelper::_('script', 'plg_system_jtlsgf/jtaldefClickAction.js', array('version' => 'auto', 'relative' => true));

		return $content;

	}

	/**
	 * Get cached information from database
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function countCachedItems()
	{
		if (null !== $this->countCachedItems)
		{
			return $this->countCachedItems;
		}

		$db    = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select('COUNT(*)')->from(JtaldefHelper::JTLSGF_DB_TABLE);

		$this->countCachedItems = (int) $db->setQuery($query)->loadResult();

		return $this->countCachedItems;
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
