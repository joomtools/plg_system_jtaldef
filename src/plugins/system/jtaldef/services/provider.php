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

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomTools\Plugin\System\Jtaldef\Extension\Jtaldef;

return new class() implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   2.1.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Jtaldef::class, function (Container $container) {
                $plugin = new Jtaldef(
                    (array) PluginHelper::getPlugin('system', 'jtaldef')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
