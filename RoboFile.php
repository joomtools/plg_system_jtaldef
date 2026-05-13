<?php

/**
 * @package    Jorobo
 *
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\Jorobo\Tasks\Tasks;
use Robo\Symfony\ConsoleIO;

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', __DIR__);
}

if (!defined('GLOB_BRACE')) {
    define('GLOB_BRACE', 0);
}

// PSR-4 Autoload by composer
require_once JPATH_BASE . '/vendor/autoload.php';

/**
 * Sample RoboFile - adjust to your needs, extend your own
 *
 * @since   1.0.0
 */
class RoboFile extends \Robo\Tasks
{
    use Tasks;

    /**
     * Initialize Robo
     */
    public function __construct()
    {
        $this->stopOnFail(true);
    }

    /**
     * Map into Joomla installation.
     *
     * @param   String  $target  The target joomla instance
     *
     * @return  void
     */
    public function map($target, $params = ['base' => JPATH_BASE])
    {
        $this->task(\Joomla\Jorobo\Tasks\Map::class, $target, $params)->run();
    }

    /**
     * Build the joomla extension package
     *
     * @param   array  $params  Additional params
     *
     * @return  void
     */
    public function build(ConsoleIO $io, $params = ['dev' => false, 'base' => JPATH_BASE])
    {
        $this->task(\Joomla\Jorobo\Tasks\Build::class, $params)->run();
    }

    /**
     * Generate an extension skeleton - not implemented yet
     *
     * @param   array  $extensions  Extensions to build (com_xy, mod_xy, pkg_name, plg_type_name, tpl_name)
     *
     * @return  void
     */
    public function generate(array $extensions, $params = ['base' => JPATH_BASE])
    {
        foreach ($extensions as $extension) {
            switch (substr($extension, 0, 3)) {
                case 'com':
                    $this->task(\Joomla\Jorobo\Tasks\Generate\Component::class, $extension, $params)->run();
                    break;
                case 'mod':
                    $this->task(\Joomla\Jorobo\Tasks\Generate\Module::class, $extension, $params)->run();
                    break;
                case 'pkg':
                    $this->task(\Joomla\Jorobo\Tasks\Generate\Package::class, $extension, $params)->run();
                    break;
                case 'plg':
                    $this->task(\Joomla\Jorobo\Tasks\Generate\Plugin::class, $extension, $params)->run();
                    break;
                case 'tpl':
                    $this->task(\Joomla\Jorobo\Tasks\Generate\Template::class, $extension, $params)->run();
                    break;
            }
        }
    }

    /**
     * Generate a component skeleton - not implemented yet
     *
     * @param   string  $name  Component name to build (e.g. com_xy)
     *
     * @return  void
     */
    public function generateComponent($name, $params = ['base' => JPATH_BASE, 'site' => true, 'api' => false, 'media' => false])
    {
        $this->task(\Joomla\Jorobo\Tasks\Generate\Component::class, $name, $params)->run();
    }

    /**
     * Generate a new component view skeleton - not implemented yet
     *
     * @param   string  $name  Component name to target (e.g. com_xy)
     * @param   string  $view  Name of the view (e.g. article)
     *
     * @return  void
     */
    public function generateView($name, $view, $params = ['base' => JPATH_BASE])
    {
        $this->task(\Joomla\Jorobo\Tasks\Generate\Component::class, $name, $params)->run();
    }

    /**
     * Generate a module skeleton - not implemented yet
     *
     * The module is generated in a folder structure fitting to directly
     * commit to a git repository. The structure follows the best coding
     * examples for Joomla 4.
     *
     * @param   string  $name    Module name to build (e.g. mod_xy)
     * @param   array   $params
     * @option  $base   A base path for the repository
     * @option  $client Select the client to build for ('site' or 'admin')
     *
     * @return  void
     */
    public function generateModule($name, $params = ['base' => JPATH_BASE, 'client' => 'site'])
    {
        $this->task(\Joomla\Jorobo\Tasks\Generate\Module::class, $name, $params)->run();
    }

    /**
     * Update copyright headers for this project. (Set the text up in the jorobo.ini)
     *
     * @return  void
     */
    public function headers($params = ['base' => JPATH_BASE])
    {
        $this->task(\Joomla\Jorobo\Tasks\CopyrightHeader::class, $params)->run();
    }

    /**
     * Bump Version placeholder __DEPLOY_VERSION__ in this project. (Set the version up in the jorobo.ini)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function bump($params = ['base' => JPATH_BASE])
    {
        $this->task(\Joomla\Jorobo\Tasks\BumpVersion::class, $params)->run();
    }
}
