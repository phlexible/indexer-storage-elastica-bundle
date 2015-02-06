<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Client;
use Elastica\Document as ElasticaDocument;
use Elastica\Filter\Ids;
use Elastica\Index;
use Elastica\Query as ElasticaQuery;
use Elastica\ResultSet;
use Phlexible\Bundle\IndexerBundle\Document\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Storage\Optimizable;
use Phlexible\Bundle\IndexerBundle\Storage\StorageAdapterInterface;
use Phlexible\Bundle\IndexerBundle\Storage\StorageInterface;
use Phlexible\Bundle\IndexerBundle\Storage\UpdateQuery\Command\AddCommand;
use Phlexible\Bundle\IndexerBundle\Storage\UpdateQuery\UpdateQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Elasticsearch storage adapter
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class ElasticsearchStorageAdapter implements StorageInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param Client                   $client
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(Client $client, EventDispatcherInterface $eventDispatcher)
    {
        $this->client = $client;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function createUpdate()
    {
        return new UpdateQuery($this->eventDispatcher);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionString()
    {
        return $this->client->getConnection()->getHost() . ':' . $this->client->getConnection()->getPort();
    }

    /**
     * {@inheritdoc}
     */
    public function findAll()
    {
        $resultSet = $this->getIndex()->search(array());

        return $this->mapElasticaResultSet($resultSet);
    }

    /**
     * {@inheritdoc}
     */
    public function find($identifier)
    {
        $query = new ElasticaQuery();
        $filter = new Ids();
        $filter->addId($identifier);
        $query->setPostFilter($filter);
        $resultSet = $this->getIndex()->search($query);

        return $this->mapElasticaResultSet($resultSet, true);
    }

    /**
     * {@inheritdoc}
     */
    public function addDocument(DocumentInterface $document)
    {
        $this->getIndex()->addDocuments(array($this->mapElasticaDocument($document)));
    }

    /**
     * {@inheritdoc}
     */
    public function updateDocument(DocumentInterface $document)
    {
        $this->getIndex()->updateDocuments(array($this->mapElasticaDocument($document)));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDocument(DocumentInterface $document)
    {
        $this->getIndex()->deleteDocuments(array($this->mapElasticaDocument($document)));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteType($type)
    {
        $this->getIndex()->getType($type)->deleteDocuments(array());
    }

    /**
     * {@inheritdoc}
     */
    public function delete($identifier)
    {
        $document = $this->find($identifier);

        $this->client->deleteIds(array($identifier), $this->getIndexName(), $document->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        $this->getIndex()->deleteDocuments(array());
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $this->getIndex()->flush(true);
    }

    /**
     * {@inheritdoc}
     */
    public function optimize()
    {
        $this->getIndex()->optimize();
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy()
    {
        $serverStatus = $this->client->getStatus()->getServerStatus();

        return $serverStatus['status'] == 200;
    }

    /**
     * @return string
     */
    private function getIndexName()
    {
        return 'test2';
    }

    /**
     * @return Index
     */
    private function getIndex()
    {
        return $this->client->getIndex($this->getIndexName());
    }

    /**
     * @param DocumentInterface $document
     *
     * @return ElasticaDocument
     */
    private function mapElasticaDocument(DocumentInterface $document)
    {
        $fields = $document->getFields();

        $data = array();
        foreach ($fields as $key => $config) {
            if (!empty($config[DocumentInterface::CONFIG_READONLY])) {
                continue;
            }

            if (!$document->hasValue($key)) {
                continue;
            }

            $data[$key] = $document->getValue($key);
        }

        return new ElasticaDocument($document->getIdentifier(), $data, $document->getName(), $this->getIndexName());
    }

    /**
     * @param ResultSet $resultSet
     * @param bool      $onlyFirst
     *
     * @return mixed
     */
    private function mapElasticaResultSet(ResultSet $resultSet, $onlyFirst = false)
    {
        if ($onlyFirst) {
            return $resultSet->current();
        }

        $data = array(
            'count'         => $resultSet->count(),
            'countSuggests' => $resultSet->countSuggests(),
            'maxScore'      => $resultSet->getMaxScore(),
            'totalHits'     => $resultSet->getTotalHits(),
            'totalTime'     => $resultSet->getTotalTime(),
            'query'         => $resultSet->getQuery()->toArray(),
        );
        if ($resultSet->hasFacets()) {
            $data['facets'] = $resultSet->getFacets();
        }

        if ($resultSet->hasSuggests()) {
            $data['suggest'] = $resultSet->getSuggests();
        }

        if ($resultSet->hasAggregations()) {
            $data['aggregations'] = $resultSet->getAggregations();
        }

        $data['results'] = array();
        foreach ($resultSet->getResults() as $result) {
            $data['results'][] = array(
                'score'      => $result->getScore(),
                'index'      => $result->getIndex(),
                'hit'        => $result->getHit(),
                'id'         => $result->getId(),
                'type'       => $result->getType(),
                'source'     => $result->getSource(),
                'version'    => $result->getVersion(),
                'data'       => $result->getData(),
                'explain'    => $result->getExplanation(),
                'highlights' => $result->getHighlights(),
            );
        }

        return $data;
    }
}
