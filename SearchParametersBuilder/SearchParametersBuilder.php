<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\IndexerStorageElasticaComponent\SearchParametersBuilder;

use Phlexible\IndexerComponent\Query\QueryInterface;
use Phlexible\IndexerStorageElasticaComponent\QueryStringBuilder\QueryStringBuilder;

/**
 * Search parameters builder
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class SearchParametersBuilder implements SearchParametersBuilderInterface
{
    /**
     * @var QueryStringBuilder
     */
    private $queryStringBuilder;

    /**
     * @param QueryStringBuilder $queryStringBuilder
     */
    public function __construct(QueryStringBuilder $queryStringBuilder)
    {
        $this->queryStringBuilder = $queryStringBuilder;
    }

    /**
     * @inheritDoc
     */
    public function fromQuery(QueryInterface $query)
    {
        $queryString = $this->queryStringBuilder->buildQueryString($query);
        $filterQueryString = $this->queryStringBuilder->buildFilterQueryString($query);

        return $this->buildParameters($queryString, $filterQueryString);
    }

    /**
     * @inheritDoc
     */
    public function fromString($queryString, $filterQueryString = '')
    {
        return $this->buildParameters($queryString, $filterQueryString);
    }

    /**
     * @inheritDoc
     */
    private function buildParameters($queryString, $filterQueryString = '')
    {
        \MWF_Log::debug(__METHOD__ . ' ' . $queryString);
        \MWF_Log::debug(__METHOD__ . ' ' . $filterQueryString);

        $baseParams = array();

        // common parameters in this interface
        $baseParams['q']                       = $queryString;
        $baseParams['fl']                      = '*,score';
        $baseParams['hl']                      = 'on';
        $baseParams['hl.fl']                   = 'title,copy';
        $baseParams['hl.fragsize']             = '500';
        $baseParams['hl.usePhraseHighlighter'] = 'true';
        $baseParams['hl.highlightMultiTerm']   = 'true';
        $baseParams['hl.mergeContiguous']      = 'true';
        $baseParams['hl.simple.pre']           = '<strong>';
        $baseParams['hl.simple.post']          = '</strong>';
        $baseParams['rows']                    =  10000;

        if (is_string($filterQueryString) && mb_strlen($filterQueryString))
        {
            $baseParams['fq'] = $filterQueryString;
        }

        return $baseParams;
    }
}