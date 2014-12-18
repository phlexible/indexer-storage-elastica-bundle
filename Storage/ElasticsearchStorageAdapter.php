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
use Elastica\Filter\Term;
use Elastica\Index;
use Elastica\Query as ElasticaQuery;
use Elastica\ResultSet;
use Phlexible\Bundle\IndexerBundle\Document\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Query\Query;
use Phlexible\Bundle\IndexerBundle\Storage\Optimizable;
use Phlexible\Bundle\IndexerBundle\Storage\StorageAdapterInterface;

/**
 * Elasticsearch storage adapter
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class ElasticsearchStorageAdapter implements StorageAdapterInterface, Optimizable
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var QueryMapper
     */
    private $queryMapper;

    /**
     * @param Client      $client
     * @param QueryMapper $queryMapper
     */
    public function __construct(Client $client,
                                QueryMapper $queryMapper)
    {
        $this->client = $client;
        $this->queryMapper = $queryMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return 'storage-elastica';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Elasticsearch';
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
    public function getByQuery(Query $query)
    {
        $elasticaQuery = $this->mapElasticaQuery($query);

        $resultSet = $this->getIndex()->search($elasticaQuery);

        return $this->mapElasticaResultSet($resultSet);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll()
    {
        $resultSet = $this->getIndex()->search(array());

        return $this->mapElasticaResultSet($resultSet);
    }

    /**
     * {@inheritdoc}
     */
    public function getByIdentifier($identifier)
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
    public function removeDocument(DocumentInterface $document)
    {
        $this->getIndex()->deleteDocuments(array($this->mapElasticaDocument($document)));
    }

    /**
     * {@inheritdoc}
     */
    public function removeByClass($class)
    {
        $query = new ElasticaQuery();
        $filter = new Term();
        $filter->setTerm('_documentclass', $class);
        $query->setPostFilter($filter);
        $result = $this->getIndex()->search($query);

        $this->getIndex()->deleteDocuments($result);
    }

    /**
     * {@inheritdoc}
     */
    public function removeByIdentifier($identifier = null)
    {
        $document = $this->getByIdentifier($identifier);

        $this->client->deleteIds(array($identifier), $this->getIndexName(), $document->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function removeByQuery(Query $query)
    {
        $documents = $this->getByQuery($query);

        $this->getIndex()->deleteDocuments($documents);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll()
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
     * @param Query $query
     *
     * @return ElasticaQuery
     */
    private function mapElasticaQuery(Query $query)
    {
        return $this->queryMapper->map($query);
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
            $data['results'][] = $result->getData();
        }

        return $data;
    }
}
