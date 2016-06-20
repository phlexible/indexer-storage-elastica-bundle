<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Index;
use Elastica\Type\Mapping;
use Phlexible\Bundle\IndexerBundle\Indexer\IndexerCollection;
use Phlexible\Bundle\IndexerBundle\Indexer\IndexerInterface;
use Phlexible\Bundle\IndexerStorageElasticaBundle\Exception\InvalidArgumentException;

/**
 * Initializer
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class Initializer
{
    /**
     * @var IndexerCollection
     */
    private $indexers;

    /**
     * @var Index
     */
    private $index;

    /**
     * @param IndexerCollection $indexers
     * @param Index             $index
     */
    public function __construct(IndexerCollection $indexers, Index $index)
    {
        $this->indexers = $indexers;
        $this->index = $index;
    }

    /**
     * @return Mapping[]
     * @throws InvalidArgumentException
     */
    public function createMappings()
    {
        $mappings = array();

        foreach ($this->indexers as $indexer) {
            /* @var $indexer IndexerInterface */

            if (!$indexer->getStorage() instanceof ElasticaStorage) {
                continue;
            }

            $document = $indexer->createDocument();

            $fields = array(
                'id' => array(
                    'type' => 'string',
                    'index' => 'analyzed',
                    'store' => true
                ),
                'type' => array(
                    'type'  => 'string',
                    'index' => 'not_analyzed',
                    'store' => true
                ),
            );

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
                    'type' => $type,
                );

                if ($type === 'attachment') {
                    $field['fields'] = array(
                        'date'           => ['store' => 'yes'],
                        'title'          => ['store' => 'yes'],
                        'name'           => ['store' => 'yes'],
                        'author'         => ['store' => 'yes'],
                        'keywords'       => ['store' => 'yes'],
                        'content_type'   => ['store' => 'yes'],
                        'content_length' => ['store' => 'yes'],
                        'language'       => ['store' => 'yes'],
                        //'file'           => ['store' => 'yes'],
                    );
                } else {
                    if ($index !== null) {
                        $field['index'] = $index;
                    }
                    if ($store !== null) {
                        $field['store'] = $store;
                    }
                }

                if (!empty($config['copyTo'])) {
                    $field['copy_to'] = $config['copyTo'];
                }

                if (!empty($config['analyzer'])) {
                    $field['analyzer'] = $config['analyzer'];
                }

                if (isset($fields[$name]) && $fields[$name] !== $field) {
                    throw new InvalidArgumentException("Conflict in field $name");
                }
                $fields[$name] = $field;
            }

            $mapping = new Mapping();
            $mapping->setType($this->index->getType($document->getName()));
            //$mapping->setParam('index_analyzer', 'indexAnalyzer');
            //$mapping->setParam('search_analyzer', 'searchAnalyzer');
            $mapping->setProperties($fields);
            if ($document->getName() === 'media') {
                $mapping->setSource(array('excludes' => array('mediafile')));
            }

            $mappings[$document->getName()] = $mapping;
        }

        return $mappings;
    }

    /**
     * @return array
     */
    public function createConfig()
    {
        $config = array(
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
                    'lowercase' => array(
                        'type'        => 'custom',
                        'tokenizer'   => 'keyword',
                        'filter'      => array('lowercase'),
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
        );

        return $config;
    }
    /**
     * @param Mapping[] $mappings
     * @param array     $config
     * @param bool      $recreate
     */
    public function initialize(array $mappings, array $config, $recreate = false)
    {
        $this->index->create($config, $recreate);

        foreach ($mappings as $name => $mapping) {
            $mapping->send();
        }
    }
}
