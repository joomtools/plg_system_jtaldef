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

\JLoader::registerNamespace('Jtaldef', JPATH_PLUGINS . '/system/jtaldef/src', true, false, 'psr4');

if (version_compare(JVERSION, 4, 'lt'))
{
	JFormHelper::loadFieldClass('list');
}

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

/**
 * List of supported Handler
 *
 * @since  1.0.0
 */
class JFormFieldJtaldefServiceToParse extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'JtaldefServiceToParse';

	/**
	 * Name of the layout being used to render the field
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $layout = 'joomla.form.field.list-fancy-select';

	/**
	 * @var    string[]
	 * @since  __DEPLOY_VERSION__
	 */
	protected $exclude = array(
		'parsecss'
	);

	/**
	 * Method to get the field input markup for a generic list.
	 * Use the multiple attribute to enable multiselect.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since  3.7.0
	 */
	protected function getInput()
	{
		if (version_compare(JVERSION, '4', 'lt'))
		{
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
	 * @since  __DEPLOY_VERSION__
	 */
	protected function getOptions()
	{
		$options      = array();
		$servicesPath = JPATH_PLUGINS . '/system/jtaldef/src/Service';
		$services     = Folder::files($servicesPath);

		foreach ($services as $serviceFile)
		{
			$serviceName = File::stripExt($serviceFile);

			if (in_array(strtolower($serviceName), $this->exclude))
			{
				continue;
			}

			$service = 'Jtaldef\\Service\\' . ucfirst($serviceName);

			if (!class_exists($service))
			{
				throw new \Exception(sprintf("The class '%s' to call for handle the download could not be found.", $service));
			}

			$fileRealName = $service::NAME;

			$tmp = array(
				'value'      => $serviceName,
				'text'       => $fileRealName,
			);

			// Add the option object to the result set.
			$options[] = (object) $tmp;

		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}
}
