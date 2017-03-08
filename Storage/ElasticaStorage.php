<?php

/*
 * This file is part of the phlexible indexer storage elastica package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Filter\Ids;
use Elastica\Filter\MatchAll;
use Elastica\Index;
use Elastica\Query as ElasticaQuery;
use Elastica\Result;
use Elastica\ResultSet;
use Phlexible\Bundle\IndexerBundle\Document\DocumentIdentity;
use Phlexible\Bundle\IndexerBundle\Document\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Storage\Flushable;
use Phlexible\Bundle\IndexerBundle\Storage\Operation\Operations;
use Phlexible\Bundle\IndexerBundle\Storage\Operation\Operator;
use Phlexible\Bundle\IndexerBundle\Storage\Optimizable;
use Phlexible\Bundle\IndexerBundle\Storage\StorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Elastica storage.
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElasticaStorage implements StorageInterface, Optimizable, Flushable
{
    /**
     * @var Index
     */
    private $index;

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
     * @param Index                    $index
     * @param ElasticaMapper           $mapper
     * @param Operator                 $operator
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        Index $index,
        ElasticaMapper $mapper,
        Operator $operator,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->index = $index;
        $this->mapper = $mapper;
        $this->operator = $operator;
        $this->eventDispatcher = $eventDispatcher;
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
        $connection = $this->index->getClient()->getConnection();

        return $connection->getHost().
            ':'.$connection->getPort().
            '/'.$this->index->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->index->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countType($type)
    {
        return $this->index->getType($type)->count();
    }

    /**
     * {@inheritdoc}
     */
    public function find(DocumentIdentity $identity)
    {
        $result = $this->findByIdentifier($identity);

        if (!$result) {
            return null;
        }

        return $this->mapper->mapResult($result);
    }

    /**
     * {@inheritdoc}
     */
    public function addDocument(DocumentInterface $document)
    {
        $response = $this->index->addDocuments(array($this->mapper->mapDocument($document, $this->index->getName())));

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function updateDocument(DocumentInterface $document)
    {
        $response = $this->index->updateDocuments(array($this->mapper->mapDocument($document, $this->index->getName())));

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDocument(DocumentInterface $document)
    {
        $response = $this->index->deleteDocuments(array($this->mapper->mapDocument($document, $this->index->getName())));

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($identifier)
    {
        $result = $this->findByIdentifier($identifier);

        if (!$result) {
            return 0;
        }

        $response = $this->index->getClient()->deleteIds(array((string) $identifier), $this->index->getName(), $result->getType());

        return $response->count();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteType($type)
    {
        $resultSet = $this->findAllByType($type);

        if (!$resultSet || !$resultSet->count()) {
            return 0;
        }

        $ids = array();
        foreach ($resultSet->getResults() as $result) {
            $ids[] = $result->getId();
        }

        $response = $this->index->getClient()->deleteIds($ids, $this->index->getName(), $type);

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
            $response = $this->index->getClient()->deleteIds($ids, $this->index->getName(), $type);

            $count += $response->count();
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->index->flush(true);
    }

    /**
     * {@inheritdoc}
     */
    public function optimize()
    {
        $this->index->optimize();
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy()
    {
        try {
            $serverStatus = $this->index->getClient()->getStatus()->getServerStatus();
            $healthy = $serverStatus['status'] === 200;
        } catch (\Exception $e) {
            $healthy = false;
        }

        if ($healthy) {
            $healthy = $this->index->exists();
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
            $serverStatus = $this->index->getClient()->getStatus()->getServerStatus();

            if ($serverStatus['status'] === 200) {
                if ($this->index->exists()) {
                } else {
                    $errors[] = 'Index '.$this->index->getName().' does not exist.';
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
     * @param string $identifier
     *
     * @return Result
     */
    private function findByIdentifier($identifier)
    {
        $query = new ElasticaQuery();
        $filter = new Ids();
        $filter->addId((string) $identifier);
        $query->setPostFilter($filter);
        $resultSet = $this->index->search($query);

        if (!count($resultSet)) {
            return null;
        }

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
        $resultSet = $this->index->search($query);

        return $resultSet;
    }

    /**
     * @param string $type
     *
     * @return ResultSet
     */
    private function findAllByType($type)
    {
        $query = new ElasticaQuery();
        $filter = new MatchAll();
        $query->setPostFilter($filter);
        $resultSet = $this->index->getType($type)->search($query);

        return $resultSet;
    }
}
