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

namespace JoomTools\Plugin\System\Jtaldef;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Filter\InputFilter;

/**
 *
 * @since  2.0.0
 */
trait JtaldefAwareTrait
{
    /**
     * The real name of the Service.
     *
     * @var    string
     * @since  2.0.0
     */
    private $name;

    /**
     * Trigger to parse <script/> tags.
     *
     * @var    boolean
     * @since  2.0.0
     */
    private $parseScripts = false;

    /**
     * List of values to trigger the service.
     *
     * @var    string[]
     * @since  2.0.0
     */
    private $stringsToTrigger = [];

    /**
     * List of namespaces to remove matches from DOM if not parsed.
     *
     * @var    string[]
     * @since  2.0.0
     */
    private $nsToRemoveNotParsedItemsFromDom = [];

    /**
     * Constructor
     *
     * @return  void
     *
     * @since   2.0.0
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
            []
        );

        // List of namespaces to remove matches from DOM if not parsed.
        $this->set(
            'nsToRemoveNotParsedItemsFromDom',
            []
        );
    }

    /**
     * Correct the local file path to the CSS file.
     *
     * @param   string  $value  Link to parse.
     *
     * @return  string|boolean  False if no font info is set in the query else the local path to the CSS file.
     * @throws  \Exception
     *
     * @since   2.0.0
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
     * @since   2.0.0
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
     * @since   2.0.0
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
     * @since   2.0.0
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
     * @since   2.0.0
     */
    public function getNsToRemoveNotParsedItemsFromDom()
    {
        return $this->nsToRemoveNotParsedItemsFromDom;
    }

    /**
     * Setter for properties.
     * 
     * @param   string  $property  The property name.
     * @param   mixed   $value     The value of the property.
     * 
     * @return  void
     * 
     * @since   2.0.0
     */
    private function set($property, $value)
    {
        switch ($property) {
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
