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

defined('JPATH_PLATFORM') or die;

\JLoader::registerNamespace('Jtaldef', JPATH_PLUGINS . '/system/jtaldef/src', true, false, 'psr4');

if (version_compare(JVERSION, 4, 'lt')) {
    JFormHelper::loadFieldClass('list');
}

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

/**
 * List of supported Handler
 *
 * @since  2.0.0
 */
class JFormFieldJtaldefServiceToParse extends JFormFieldList
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  2.0.0
     */
    protected $type = 'JtaldefServiceToParse';

    /**
     * Name of the layout being used to render the field
     *
     * @var    string
     * @since  2.0.0
     */
    protected $layout = 'joomla.form.field.list-fancy-select';

    /**
     * @var    string[]
     * @since  2.0.0
     */
    protected $exclude = array(
        'parsecss',
    );

    /**
     * Method to get the field input markup for a generic list.
     * Use the multiple attribute to enable multiselect.
     *
     * @return  string  The field input markup.
     *
     * @since   2.0.0
     */
    protected function getInput()
    {
        if (version_compare(JVERSION, '4', 'lt')) {
            $this->layout = '';

            return parent::getInput();
        }

        $data = $this->getLayoutData();

        $data['options'] = (array) $this->getOptions();

        return $this->getRenderer($this->layout)->render($data);
    }

    /**
     * Method to get the field options.
     *
     * @return   array  The field option objects.
     *
     * @since   2.0.0
     */
    protected function getOptions()
    {
        $options      = array();
        $app          = Factory::getApplication();
        $servicesPath = JPATH_PLUGINS . '/system/jtaldef/src/Service';
        $services     = Folder::files($servicesPath);

        foreach ($services as $serviceFile) {
            $error       = false;
            $serviceName = File::stripExt($serviceFile);

            if (in_array(strtolower($serviceName), $this->exclude)) {
                continue;
            }

            $service = 'Jtaldef\\Service\\' . ucfirst($serviceName);

            try {
                $serviceHandler = new $service;
            } catch (\Throwable $e) {
                $error = true;
            } catch (\Exception $e) {
                $error = true;
            }

            if ($error) {
                $app->enqueueMessage(
                    sprintf("The class '%s' to call for handle the download could not be found.", $service),
                    'error'
                );

                continue;
            }

            $tmp = array(
                'value'    => $serviceName,
                'text'     => $serviceHandler->getRealServiceName(),
                'selected' => 'true',
                'checked'  => 'true',
            );

            // Add the option object to the result set.
            $options[] = (object) $tmp;
        }

        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $options);

        return $options;
    }
}
