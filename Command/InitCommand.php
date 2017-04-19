<?php

/*
 * This file is part of the phlexible indexer storage elastica package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Command;

use Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\InitializerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Init command.
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class InitCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('indexer-storage-elastica:init')
            ->setDescription('Initialize elastica storage.')
            ->addOption('--recreate', null, InputOption::VALUE_NONE)
            ->addOption('--show', null, InputOption::VALUE_NONE)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $initializer = $this->getContainer()->get('phlexible_indexer_storage_elastica.initializer');

        if (!$initializer instanceof InitializerInterface) {
            $output->writeln('<error>Not an initializer</>');

            return 1;
        }

        $mappings = $initializer->createMappings();
        $config = $initializer->createConfig();

        $yaml = new Yaml();
        $output->writeln('Index '.current($mappings)->getType()->getIndex()->getName());
        $output->writeln('  Configuration');
        $output->writeln($this->indent($yaml->dump($config, 3, 2)));
        foreach ($mappings as $mapping) {
            $output->writeln('  Type '.$mapping->getType()->getName());
            foreach ($mapping->getProperties() as $field => $value) {
                $output->writeln('    '.$field.': '.$yaml->dump($value, 0));
            }
        }

        if (!$input->getOption('show')) {
            $initializer->initialize($mappings, $config, $input->getOption('recreate'));

            $output->writeln('<info>Storage initialized.</info>');
        }


        return 0;
    }

    private function indent($s, $depth = 4)
    {
        $lines = array();
        foreach (explode(PHP_EOL, $s) as $line) {
            $lines[] = str_repeat(' ', $depth).$line;
        }

        return rtrim(implode(PHP_EOL, $lines));
    }
}
