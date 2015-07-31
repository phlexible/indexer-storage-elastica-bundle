<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Document as ElasticaDocument;
use Elastica\Result as ElasticaResult;
use Elastica\ResultSet as ElasticaResultSet;
use Phlexible\Bundle\IndexerBundle\Document\DocumentFactory;
use Phlexible\Bundle\IndexerBundle\Model\DocumentInterface;
use Phlexible\Bundle\IndexerBundle\Result\ResultSet;

/**
 * Elastica mapper
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElasticaMapper
{
    /**
     * @var DocumentFactory
     */
    private $documentFactory;

    /**
     * @param DocumentFactory $documentFactory
     */
    public function __construct(DocumentFactory $documentFactory)
    {
        $this->documentFactory = $documentFactory;
    }

    /**
     * @param DocumentInterface $document
     * @param string            $indexName
     *
     * @return ElasticaDocument
     */
    public function mapDocument(DocumentInterface $document, $indexName)
    {
        $fields = $document->getFields();

        $data = array();
        foreach ($fields as $key => $config) {
            if (!empty($config[DocumentInterface::CONFIG_READONLY])) {
                continue;
            }

            if (!$document->has($key)) {
                continue;
            }

            $data[$key] = $document->get($key);
        }

        return new ElasticaDocument($document->getIdentifier(), $data, $document->getName(), $indexName);
    }

    /**
     * @param ElasticaResultSet $elasticaResults
     * @param bool              $onlyFirst
     *
     * @return ResultSet
     */
    public function mapResultSet(ElasticaResultSet $elasticaResults, $onlyFirst = false)
    {
        if ($onlyFirst) {
            $result = $elasticaResults->current();

            return $this->mapResult($result);
        }

        $results = new ResultSet();
        $results
            ->setMaxScore($elasticaResults->getMaxScore())
            ->setTotalHits($elasticaResults->getTotalHits())
            ->setTotalTime($elasticaResults->getTotalTime());
        foreach ($elasticaResults->getResults() as $result) {
            $results->add($this->mapResult($result));
        }

        return $results;
    }

    /**
     * @param ElasticaResult $result
     *
     * @return DocumentInterface
     */
    public function mapResult(ElasticaResult $result)
    {
        $data = $result->getData();
        $document = $this->documentFactory->factory($result->getType());
        foreach ($data as $key => $value) {
            $document->set($key, $value);
        }

        return $document;
    }
}
