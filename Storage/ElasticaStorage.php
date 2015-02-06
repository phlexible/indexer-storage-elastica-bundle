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
use Phlexible\Bundle\IndexerBundle\Storage\Optimizable;
use Phlexible\Bundle\IndexerBundle\Storage\StorageInterface;
use Phlexible\Bundle\IndexerBundle\Storage\UpdateQuery\Command\CommandCollection;
use Phlexible\Bundle\IndexerBundle\Storage\UpdateQuery\UpdateQuery;
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
     * @var UpdateQuery
     */
    private $updateQuery;

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
     * @param UpdateQuery              $updateQuery
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $indexName
     */
    public function __construct(
        Client $client,
        ElasticaMapper $mapper,
        UpdateQuery $updateQuery,
        EventDispatcherInterface $eventDispatcher,
        $indexName)
    {
        $this->client = $client;
        $this->mapper = $mapper;
        $this->updateQuery = $updateQuery;
        $this->eventDispatcher = $eventDispatcher;
        $this->indexName = $indexName;
    }

    /**
     * {@inheritdoc}
     */
    public function createCommands()
    {
        return $this->updateQuery->createCommands();
    }

    /**
     * {@inheritdoc}
     */
    public function runCommands(CommandCollection $commands)
    {
        return $this->updateQuery->run($this, $commands);
    }

    /**
     * {@inheritdoc}
     */
    public function queueCommands(CommandCollection $commands)
    {
        return $this->updateQuery->queue($commands);
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
        $this->getIndex()->addDocuments(array($this->mapper->mapDocument($document, $this->getIndexName())));
    }

    /**
     * {@inheritdoc}
     */
    public function updateDocument(DocumentInterface $document)
    {
        $this->getIndex()->updateDocuments(array($this->mapper->mapDocument($document, $this->getIndexName())));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDocument(DocumentInterface $document)
    {
        $this->getIndex()->deleteDocuments(array($this->mapper->mapDocument($document, $this->getIndexName())));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteType($type)
    {
        $query = new Query(new Query\MatchAll());
        $this->getIndex()->getType($type)->deleteByQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($identifier)
    {
        $result = $this->find($identifier);

        if (!$result) {
            return;
        }

        $this->client->deleteIds(array($identifier), $this->getIndexName(), $result->getType());
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        $resultSet = $this->findAll();

        if (!$resultSet) {
            return;
        }

        $typeIds = array();
        foreach ($resultSet->getResults() as $result) {
            $typeIds[$result->getType()][] = $result->getId();
        }

        foreach ($typeIds as $type => $ids) {
            $this->client->deleteIds($ids, $this->getIndexName(), $type);
        }
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
        $serverStatus = $this->client->getStatus()->getServerStatus();

        return $serverStatus['status'] == 200;
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
        $filter->addId($identifier);
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
}
