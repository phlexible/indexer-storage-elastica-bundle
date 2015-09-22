<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Client;
use Elastica\Index;
use Elastica\Type\Mapping;
use Phlexible\Bundle\IndexerBundle\Indexer\IndexerCollection;
use Phlexible\Bundle\IndexerBundle\Indexer\IndexerInterface;
use Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\ElasticaStorage;

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
     * @var Client
     */
    private $client;

    /**
     * @var Index
     */
    private $index;

    /**
     * @var string
     */
    private $indexName;

    /**
     * @param IndexerCollection $indexers
     * @param Client            $client
     * @param string            $indexName
     */
    public function __construct(IndexerCollection $indexers, Client $client, $indexName)
    {
        $this->indexers = $indexers;
        $this->client = $client;
        $this->indexName = $indexName;

        $this->index = $client->getIndex($this->indexName);
    }

    /**
     * @return Mapping[]
     * @throws \Exception
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
                    'name' => 'id', 'type' => 'string', 'index' => 'analyzed', 'store' => true
                ),
                'type' => array(
                    'name'  => 'type', 'type'  => 'string', 'index' => 'not_analyzed', 'store' => true
                ),
                'autocomplete' => array(
                    'name' => 'autocomplete', 'type' => 'string', 'analyzer' => 'autocomplete', 'store' => true, 'index'    => 'analyzed'
                ),
                'did_you_mean' => array(
                    'name' => 'did_you_mean', 'type' => 'string', 'analyzer' => 'didYouMean', 'store' => true, 'index' => 'analyzed'
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
                    'name' => $name,
                    'type' => $type,
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
     */
    public function initialize(array $mappings, array $config)
    {
        $this->index->create($config, true);

        foreach ($mappings as $name => $mapping) {
            $mapping->send();
        }
    }
}
