<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\IndexerStorageElasticaComponent;

use Elastica\Client;
use Phlexible\IndexerComponent\Document\DocumentInterface;
use Phlexible\IndexerComponent\Query\QueryInterface;
use Phlexible\IndexerComponent\Storage\AbstractStorageAdapter;
use Phlexible\IndexerStorageElasticaComponent\ResultRenderer\ResultRendererInterface;
use Phlexible\IndexerStorageElasticaComponent\SearchParametersBuilder\SearchParametersBuilderInterface;

/**
 * Elasticsearch storage adapter
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class ElasticsearchStorageAdapter extends AbstractStorageAdapter
{
    /**
     * @var SearchParametersBuilderInterface
     */
    private $searchParametersBuilder = null;

    /**
     * @var ResultRendererInterface
     */
    private $resultRenderer = null;

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

    public function getId()
    {
        return 'storage-elastica';
    }

    /**
     * Return connection parameters as string
     *
     * @return string
     */
    public function getConnectionString()
    {
        return $this->client->getConnection()->getHost();
    }

    /**
     * Return documents by query
     *
     * @param QueryInterface $query
     * @return array
     */
    public function getByQuery(QueryInterface $query)
    {
        $result =  $this->resultRenderer->renderToResult($this->_searchByQuery($query));

        return $result;
    }

    /**
     * Return all documents
     *
     * @return array
     */
    public function getAll()
    {
        $result = $this->resultRenderer->renderToResult($this->_search('*:*'));

        return $result;
    }

    /**
     * Return document by identifier
     *
     * @param string $identifier
     * @return DocumentInterface
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

        return $this->driver->search($parameters);
    }

    protected function _search($queryString, $filterQueryString = '')
    {
        $parameters = $this->searchParametersBuilder->fromString($queryString, $filterQueryString);

        return $this->driver->search($parameters);
    }
    /**
     * @param DocumentInterface $document
     */
    public function addDocument(DocumentInterface $document)
    {
        $documents = array(
            $this->indexerDocumentToElasticaDocument($document)
        );
        $this->client->addDocuments($documents);
    }

    protected function indexerDocumentToElasticaDocument(DocumentInterface $document)
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

        echo $document->getIdentifier().PHP_EOL;
        return $doc = new \Elastica\Document($document->getIdentifier(), $data, 'test1', 'test2');
    }
    /**
     * @param DocumentInterface $document
     */
    public function updateDocument(DocumentInterface $document)
    {
        $this->addDocument($document);
    }

    /**
     * @param string $identifier
     */
    public function removeByIdentifier($identifier = null)
    {
        $this->client->deleteIds(array($identifier), 'test1', 'test2');
    }

    /**
     * @param QueryInterface $query
     */
    public function removeByQuery(QueryInterface $query)
    {
        $this->driver->deleteByQuery($query);
    }

    public function removeAll()
    {
        $this->driver->deleteAll();
    }

    /**
     * @return integer
     */
    public function getPreference()
    {
        return self::PREFERENCE_FIRST_COICE;
    }

    public function isHealthy()
    {
        return $this->driver->isHealthy();
    }
}
