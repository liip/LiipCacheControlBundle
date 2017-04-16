<?php

namespace Liip\CacheControlBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;


/**
 * Importing zips to cooperatives mapping
 */
class InvalidateVarnishCommand extends ContainerAwareCommand
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
        ->setName('liip:cache-control:varnish:invalidate')
        ->setDescription('Clear cached entries from Varnish servers')
        ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'What URLs do you want to invalidate? (default: .)'
            );
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $this->logger = $container->get('logger');
        if (!$container->has('liip_cache_control.varnish')) {
            $this->logger->info('<error>There is no cache control configuration for Varnish in this environment.</error>');

            return;
        }
        $helper = $this->getContainer()->get('liip_cache_control.varnish');

        $path = $input->getArgument('path');
        if (!$path) {
            $path = ".";
        }
        $this->getLogger()->notice('Starting clearing varnish with path: "' . $path .'"');

        $helper->invalidatePath($path);

        $this->getLogger()->notice('Done clearing varnish');
    }

    /**
     * Returns the logger
     *
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (null == $this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }
}