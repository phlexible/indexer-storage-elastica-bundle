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

use Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\Initializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Init command.
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class InitCommand extends Command
{
    /**
     * @var Initializer
     */
    private $initializer;

    /**
     * @param Initializer $initializer
     */
    public function __construct(Initializer $initializer)
    {
        parent::__construct();

        $this->initializer = $initializer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('indexer-storage-elastica:init')
            ->setDescription('Initialize elastica storage.')
            ->addOption('--recreate', null, InputOption::VALUE_NONE)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mappings = $this->initializer->createMappings();
        $config = $this->initializer->createConfig();

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

        $this->initializer->initialize($mappings, $config, $input->getOption('recreate'));

        $output->writeln('<info>Storage initialized.</info>');

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
