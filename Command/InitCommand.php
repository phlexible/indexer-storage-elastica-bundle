<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Command;

use Elastica\Type\Mapping;
use Phlexible\Bundle\IndexerBundle\Indexer\IndexerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Init command
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
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexers = $this->getContainer()->get('phlexible_indexer.indexers');

        $types = array();

        foreach ($indexers as $indexer) {
            /* @var $indexer IndexerInterface */

            $document = $indexer->createDocument();

            $fields = array(
                'id'              => array('type' => 'string', 'index' => 'analyzed', 'store' => true),
                '_documentclass_' => array('type' => 'string', 'index' => 'not_analyzed', 'store' => true),
            );

            $output->writeln('Indexer: ' . $indexer->getLabel());
            $output->writeln('  Document: ' . $indexer->getDocumentClass());

            foreach ($document->getFields() as $name => $config) {
                $type = 'string';
                $index = 'analyzed';
                $store = false;

                if (isset($config['indexed']) && empty($config['indexed'])) {
                    $index = 'not_analyzed';
                }

                if (isset($config['stored'])) {
                    $store = (bool) $config['stored'];
                }

                if (isset($config['type'])) {
                    if ($config['type'] === 'text') {
                        $type = 'string';
                    } elseif ($config['type'] === 'boolean') {
                        $type = 'boolean';
                        $index = 'not_analyzed';
                    } else {
                        $type = $config['type'];
                    }
                }

                $field = array(
                    'name'  => $name,
                    'type'  => $type,
                    'index' => $index,
                    'store' => $store
                );

                if (isset($fields[$name]) && $fields[$name] !== $field) {
                    throw new \Exception("Conflict in field $name");
                }
                $fields[$name] = $field;

                $output->writeln('    ' . $name .': ' . json_encode($config));
                $output->writeln('     => ' . json_encode($field));
            }

            $types[$document->getName()] = $fields;
        }

        $client = $this->getContainer()->get('phlexible_elastica.default_client');

        try {
            $indexName = 'test2';
            $index = $client->getIndex($indexName);

            $index->create(
                array(
                    //'number_of_shards' => 4,
                    //'number_of_replicas' => 1,
                    'analysis' => array(
                        'analyzer' => array(
                            'indexAnalyzer' => array(
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => array('lowercase', 'mySnowball')
                            ),
                            'searchAnalyzer' => array(
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => array('standard', 'lowercase', 'mySnowball')
                            )
                        ),
                        'filter' => array(
                            'mySnowball' => array(
                                'type' => 'snowball',
                                'language' => 'German'
                            )
                        )
                    )
                ),
                true
            );

            $output->writeln("Index $indexName created.");

            foreach ($types as $name => $fields) {
                $type = $index->getType($name);
                $mapping = new Mapping();
                $mapping->setType($type);
                $mapping->setParam('index_analyzer', 'indexAnalyzer');
                $mapping->setParam('search_analyzer', 'searchAnalyzer');
                $mapping->setProperties($fields);
                $mapping->send();

                $output->writeln("  Type $name mapped.");
            }
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());

            return 1;
        }

        return 0;
    }

}
