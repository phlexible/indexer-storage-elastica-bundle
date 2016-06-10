<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Client;
use Elastica\Filter\Ids;
use Elastica\Filter\MatchAll;
use Elastica\Index;
use Elastica\Query as ElasticaQuery;
use Elastica\Query;
use Elastica\Result;
use Elastica\ResultSet;
use Phlexible\Bundle\IndexerBundle\Document\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Storage\Flushable;
use Phlexible\Bundle\IndexerBundle\Storage\Operation\Operations;
use Phlexible\Bundle\IndexerBundle\Storage\Operation\Operator;
use Phlexible\Bundle\IndexerBundle\Storage\Optimizable;
use Phlexible\Bundle\IndexerBundle\Storage\StorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Elastica storage
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElasticaStorage implements StorageInterface, Optimizable, Flushable
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ElasticaMapper
     */
    private $mapper;

    /**
     * @var Operator
     */
    private $operator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private $indexName;

    /**
     * @param Client                   $client
     * @param ElasticaMapper           $mapper
     * @param Operator                 $operator
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $indexName
     */
    public function __construct(
        Client $client,
        ElasticaMapper $mapper,
        Operator $operator,
        EventDispatcherInterface $eventDispatcher,
        $indexName)
    {
        $this->client = $client;
        $this->mapper = $mapper;
        $this->operator = $operator;
        $this->eventDispatcher = $eventDispatcher;
        $this->indexName = $indexName;
    }

    /**
     * {@inheritdoc}
     */
    public function createOperations()
    {
        return $this->operator->createOperations();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Operations $operations)
    {
        return $this->operator->execute($this, $operations);
    }

    /**
     * {@inheritdoc}
     */
    public function queue(Operations $operations)
    {
        return $this->operator->queue($operations);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionString()
    {
        return $this->client->getConnection()->getHost() .
            ':' . $this->client->getConnection()->getPort() .
            '/' . $this->getIndexName();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->getIndex()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countType($type)
    {
        return $this->getIndex()->getType($type)->count();
    }

    /**
     * {@inheritdoc}
     */
    public function addDocument(DocumentInterface $document)
    {
        $response = $this->getIndex()->addDocuments(array($this->mapper->mapDocument($document, $this->getIndexName())));

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function updateDocument(DocumentInterface $document)
    {
        $response = $this->getIndex()->updateDocuments(array($this->mapper->mapDocument($document, $this->getIndexName())));

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDocument(DocumentInterface $document)
    {
        $response = $this->getIndex()->deleteDocuments(array($this->mapper->mapDocument($document, $this->getIndexName())));

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($identifier)
    {
        $result = $this->find($identifier);

        if (!$result) {
            return 0;
        }

        $response = $this->client->deleteIds(array((string) $identifier), $this->getIndexName(), $result->getType());

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteType($type)
    {
        $resultSet = $this->findType($type);

        if (!$resultSet || !$resultSet->count()) {
            return 0;
        }

        $ids = array();
        foreach ($resultSet->getResults() as $result) {
            $ids[] = $result->getId();
        }

        $response = $this->client->deleteIds($ids, $this->getIndexName(), $type);

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        $resultSet = $this->findAll();

        if (!$resultSet) {
            return 0;
        }

        $typeIds = array();
        foreach ($resultSet->getResults() as $result) {
            $typeIds[$result->getType()][] = $result->getId();
        }

        $count = 0;
        foreach ($typeIds as $type => $ids) {
            $response = $this->client->deleteIds($ids, $this->getIndexName(), $type);

            $count += $response->count();
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
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
        try {
            $serverStatus = $this->client->getStatus()->getServerStatus();
            $healthy = $serverStatus['status'] == 200;
        } catch (\Exception $e) {
            $healthy = false;
        }

        if ($healthy) {
            $healthy = $this->getIndex()->exists();
        }

        return $healthy;
    }

    /**
     * @return array
     */
    public function check()
    {
        $errors = array();

        try {
            $serverStatus = $this->client->getStatus()->getServerStatus();

            if ($serverStatus['status'] == 200) {
                $index = $this->getIndex();
                if ($index->exists()) {
                } else {
                    $errors[] = 'Index ' . $this->getIndexName() . ' does not exist.';
                }
            } else {
                $errors[] = 'Elasticsearch server status not ok.';
            }
        } catch (\Exception $e) {
            $errors[] = 'Elasticsearch server not reachable.';
        }

        return $errors;
    }

    /**
     * @return string
     */
    public function getIndexName()
    {
        return $this->indexName;
    }

    /**
     * @return Index
     */
    public function getIndex()
    {
        return $this->client->getIndex($this->getIndexName());
    }

    /**
     * @param string $identifier
     *
     * @return Result
     */
    private function find($identifier)
    {
        $query = new ElasticaQuery();
        $filter = new Ids();
        $filter->addId((string) $identifier);
        $query->setPostFilter($filter);
        $resultSet = $this->getIndex()->search($query);

        return $resultSet->current();
    }

    /**
     * @return ResultSet
     */
    private function findAll()
    {
        $query = new ElasticaQuery();
        $filter = new MatchAll();
        $query->setPostFilter($filter);
        $resultSet = $this->getIndex()->search($query);

        return $resultSet;
    }

    /**
     * @param string $type
     *
     * @return ResultSet
     */
    private function findType($type)
    {
        $query = new ElasticaQuery();
        $filter = new MatchAll();
        $query->setPostFilter($filter);
        $resultSet = $this->getIndex()->getType($type)->search($query);

        return $resultSet;
    }
}
