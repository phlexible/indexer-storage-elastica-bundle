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
        $documentFactory = $this->getContainer()->get('phlexible_indexer.document_factory');

        $types = array();

        foreach ($indexers as $indexer) {
            /* @var $indexer IndexerInterface */

            $documentClass = $indexer->getDocumentClass();
            $document = $documentFactory->factory($documentClass);

            $fields = array(
                'id'           => array('name' => 'id', 'type' => 'string', 'index' => 'analyzed', 'store' => true),
                'type'         => array('name' => 'type', 'type' => 'string', 'index' => 'not_analyzed', 'store' => true),
                'autocomplete' => array('name' => 'autocomplete', 'type' => 'string', 'analyzer' => 'autocomplete', 'store' => true, 'index' => 'analyzed'),
                'did_you_mean' => array('name' => 'did_you_mean', 'type' => 'string', 'analyzer' => 'didYouMean', 'store' => true, 'index' => 'analyzed'),
            );

            $output->writeln('Indexer: ' . $indexer->getName());
            $output->writeln('  Document: ' . get_class($document));

            foreach ($fields as $name => $field) {
                $output->writeln('    ' . $name .':');
                $output->writeln('     => ' . json_encode($field));
            }

            foreach ($document->getFields() as $name => $config) {
                $type = 'string';
                $index = 'analyzed';
                $store = true;

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
                );

                if ($type === 'attachment') {
                    $field['fields'] = array(
                        "date"           => ["store" => "yes"],
                        "title"          => ["store" => "yes"],
                        "name"           => ["store" => "yes"],
                        "author"         => ["store" => "yes"],
                        "keywords"       => ["store" => "yes"],
                        "content_type"   => ["store" => "yes"],
                        "content_length" => ["store" => "yes"],
                        "language"       => ["store" => "yes"],
                        $name            => ["store" => "yes"],
                    );
                } else {
                    if ($index !== null) {
                        $field['index'] = $index;
                    }
                    if ($store !== null) {
                        $field['store'] = $store;
                    }

                    if ($name === 'content' || $name === 'title') {
                        $field['copy_to'] = array('autocomplete', 'did_you_mean');
                    }
                }

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
                        'filter' => array(
                            'stemmer' => array(
                                'type'     => 'stemmer',
                                'language' => 'german',
                            ),
                            'autocompleteFilter' => array(
                                'type'             => 'shingle',
                                'min_shingle_size' => 2,
                                'max_shingle_size' => 5,
                            ),
                            'stopwords' => array(
                                'type'      => 'stop',
                                'stopwords' => '_german_'
                            ),
                        ),
                        'analyzer' => array(
                            'didYouMean' => array(
                                'type'        => 'custom',
                                'tokenizer'   => 'standard',
                                'filter'      => array('lowercase'),
                                'char_filter' => array('html_strip'),
                            ),
                            'autocomplete' => array(
                                'type'        => 'custom',
                                'tokenizer'   => 'standard',
                                'filter'      => array('lowercase', 'autocompleteFilter'),
                                'char_filter' => array('html_strip')
                            ),
                            'default' => array(
                                'type'        => 'custom',
                                'tokenizer'   => 'standard',
                                'filter'      => array('lowercase', 'stopwords', 'stemmer'),
                                'char_filter' => array('html_strip')
                            ),
                        ),
                    ),
                ),
                true
            );

            $output->writeln("Index $indexName created.");

            foreach ($types as $name => $fields) {
                $type = $index->getType($name);
                $mapping = new Mapping();
                $mapping->setType($type);
                //$mapping->setParam('index_analyzer', 'indexAnalyzer');
                //$mapping->setParam('search_analyzer', 'searchAnalyzer');
                $mapping->setProperties($fields);
                if ($name === 'media') {
                    $mapping->setSource(array('excludes' => array('mediafile')));
                }
                $mapping->send();

                $output->writeln("  Type $name mapped.");
            }
        } catch (\Exception $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return 1;
        }

        return 0;
    }

}
