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
use Elastica\Index;
use Elastica\Query as ElasticaQuery;
use Elastica\Query;
use Elastica\Result;
use Phlexible\Bundle\IndexerBundle\Document\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Storage\Flushable;
use Phlexible\Bundle\IndexerBundle\Storage\Optimizable;
use Phlexible\Bundle\IndexerBundle\Storage\StorageInterface;
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
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $indexName
     */
    public function __construct(Client $client, ElasticaMapper $mapper, EventDispatcherInterface $eventDispatcher, $indexName)
    {
        $this->client = $client;
        $this->mapper = $mapper;
        $this->eventDispatcher = $eventDispatcher;
        $this->indexName = $indexName;
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
    public function execute(UpdateQuery $update)
    {
        return $update->execute($this);
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

        $this->client->deleteIds(array($identifier), $this->getIndexName(), $result->getType());
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
}
