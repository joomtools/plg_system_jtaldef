<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * Download robo.phar from http://robo.li/robo.phar and type in the root of the repo: $ php robo.phar
 * Or do: $ composer update, and afterwards you will be able to execute robo like $ php vendor/bin/robo
 *
 * @see http://robo.li/
 */

use Joomla\Jorobo\Tasks\loadTasks;

if (!defined('JPATH_BASE'))
{
	define('JPATH_BASE', __DIR__);
}

// PSR-4 Autoload by composer
require_once JPATH_BASE . '/vendor/autoload.php';

class RoboFile extends \Robo\Tasks
{
	use loadTasks;

	/**
	 * Initialize Robo
	 */
	public function __construct()
	{
		$this->stopOnFail(false);
	}

	/**
	 * Build the joomla extension package
	 *
	 * @param   array  $params  Additional params
	 *
	 * @return  void
	 */
	public function build($params = ['dev' => false])
	{
		if (!file_exists('jorobo.ini'))
		{
			$this->_copy('jorobo.dist.ini', 'jorobo.ini');
		}

		// First bump version placeholder in the project
		(new \Joomla\Jorobo\Tasks\BumpVersion())->run();

		// Build the extension package
		(new \Joomla\Jorobo\Tasks\Build($params))->run();
	}

	/**
	 * Update copyright headers for this project. (Set the text up in the jorobo.ini)
	 *
	 * @return  void
	 */
	public function headers()
	{
		if (!file_exists('jorobo.ini'))
		{
			$this->_copy('jorobo.dist.ini', 'jorobo.ini');
		}

		(new \Joomla\Jorobo\Tasks\CopyrightHeader())->run();
	}

	/**
	 * Symlink projectfiles from source into target
	 *
	 * @param   string  $target  Absolute path to Joomla! root
	 *
	 * @return   void
	 */
	public function map($target)
	{
		if (!file_exists('jorobo.ini'))
		{
			$this->_copy('jorobo.dist.ini', 'jorobo.ini');
		}

		(new \Joomla\Jorobo\Tasks\Map($target))->run();
	}

	/**
	 * Bump Version placeholder __DEPLOY_VERSION__ in this project. (Set the version up in the jorobo.ini)
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function bump()
	{
		if (!file_exists('jorobo.ini'))
		{
			$this->_copy('jorobo.dist.ini', 'jorobo.ini');
		}

		(new \Joomla\Jorobo\Tasks\BumpVersion())->run();
	}
}
