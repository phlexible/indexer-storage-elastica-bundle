<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Client;
use Phlexible\Bundle\IndexerBundle\Document\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Query\Query\QueryInterface;
use Phlexible\Bundle\IndexerBundle\Storage\SelectQuery\SelectQuery;
use Phlexible\Bundle\IndexerBundle\Storage\StorageAdapterInterface;
use Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\ResultRenderer\ResultRendererInterface;
use Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\SearchParametersBuilder\SearchParametersBuilderInterface;

/**
 * Elasticsearch storage adapter
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class ElasticsearchStorageAdapter implements StorageAdapterInterface
{
    /**
     * @var SearchParametersBuilderInterface
     */
    private $searchParametersBuilder;

    /**
     * @var ResultRendererInterface
     */
    private $resultRenderer;

    /**
     * @var string
     */
    protected $label = 'Elasticsearch storage adapter';

    /**
     * @var string
     */
    protected $resultClass = 'Phlexible\IndexerComponent\Document\DocumentInterface';

    /**
     * @var array
     */
    protected $acceptQuery = array('Phlexible\IndexerComponent\Query\QueryInterface');

    /**
     * @var array
     */
    protected $acceptStorage = array();

    /**
     * @var Client
     */
    protected $client;

    /**
     * @param Client                           $client
     * @param SearchParametersBuilderInterface $searchParametersBuilder
     * @param ResultRendererInterface          $resultRenderer
     */
    public function __construct(Client $client,
                                SearchParametersBuilderInterface $searchParametersBuilder,
                                ResultRendererInterface $resultRenderer)
    {
        $this->client = $client;
        $this->searchParametersBuilder = $searchParametersBuilder;
        $this->resultRenderer = $resultRenderer;
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
        return $this->client->getConnection()->getHost();
    }

    /**
     * {@inheritdoc}
     */
    public function getByQuery(SelectQuery $query)
    {
        $result =  $this->resultRenderer->renderToResult($this->_searchByQuery($query));

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll()
    {
        $result = $this->resultRenderer->renderToResult($this->_search('*:*'));

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getByIdentifier($identifier)
    {
        $result = $this->resultRenderer->renderToResult($this->_search('_identifier_:' . $identifier));

        if (count($result) == 1)
        {
            return $result[0];
        }

        return null;
    }

    private function _searchByQuery(QueryInterface $query)
    {
        $parameters = $this->searchParametersBuilder->fromQuery($query);

        return $this->client->search($parameters);
    }

    private function _search($queryString, $filterQueryString = '')
    {
        $parameters = $this->searchParametersBuilder->fromString($queryString, $filterQueryString);

        return $this->client->search($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function addDocument(DocumentInterface $document)
    {
        $documents = array(
            $this->indexerDocumentToElasticaDocument($document)
        );
        $this->client->addDocuments($documents);
    }

    private function indexerDocumentToElasticaDocument(DocumentInterface $document)
    {
        $fields = $document->getFields();

        $data = array();
        foreach ($fields as $key => $config)
        {
            if (!empty($config[DocumentInterface::CONFIG_READONLY]))
            {
                continue;
            }

            if (!$document->hasValue($key))
            {
                continue;
            }

            $data[$key] = $document->getValue($key);
        }

        return $doc = new \Elastica\Document($document->getIdentifier(), $data, 'test1', 'test2');
    }

    /**
     * {@inheritdoc}
     */
    public function updateDocument(DocumentInterface $document)
    {
        $this->addDocument($document);
    }

    /**
     * {@inheritdoc}
     */
    public function removeDocument(DocumentInterface $document)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function removeByClass($class)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function removeByIdentifier($identifier = null)
    {
        $this->client->deleteIds(array($identifier), 'test1', 'test2');
    }

    /**
     * {@inheritdoc}
     */
    public function removeByQuery(SelectQuery $query)
    {
        $this->client->deleteByQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll()
    {
        $this->client->deleteAll();
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function optimize()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy()
    {
        return $this->client->isHealthy();
    }
}
