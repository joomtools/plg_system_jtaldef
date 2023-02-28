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


namespace Jtaldef\Console;

// phpcs:disable PSR1.Files.SideEffects
\defined('JPATH_PLATFORM') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Filesystem\Folder;
use Joomla\Console\Command\AbstractCommand;
use Jtaldef\Helper\JtaldefHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for cleaning the JTALDEF cache
 *
 * @since  2.0.6
 */
class CacheCleaner extends AbstractCommand
{
    /**
     * The default command name
     *
     * @var    string
     * @since  2.0.6
     */
    protected static $defaultName = 'jtaldef:cache:clean';

    /**
     * Internal function to execute the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  integer  The command exit code
     *
     * @since   2.0.6
     */
    protected function doExecute(InputInterface $input, OutputInterface $output)
    : int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        if (is_dir(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD)) {
            $symfonyStyle->title('Cleaning JTALDEF fonts cache');

            $clearIndex = Folder::delete(JPATH_ROOT . '/' . JtaldefHelper::JTALDEF_UPLOAD);

            if (!$clearIndex) {
                $symfonyStyle->error("JTALDEF cache not cleaned:\n- There was an error when deleting the JTALDEF cached files.");

                return Command::FAILURE;
            }

            $symfonyStyle->success('JTALDEF cache cleaned.');

            return Command::SUCCESS;
        }

        $symfonyStyle->info('There is no JTALDEF cache to clean.');

        return Command::SUCCESS;
    }

    /**
     * Configure the command.
     *
     * @return  void
     *
     * @since   2.0.6
     */
    protected function configure()
    : void
    {
        $help = "<info>%command.name%</info> will clear entries from the JTALDEF fonts cache
		\nUsage: <info>php %command.full_name%</info>";

        $this->setDescription('Cleans JTALDEF cached entries');
        $this->setHelp($help);
    }
}
