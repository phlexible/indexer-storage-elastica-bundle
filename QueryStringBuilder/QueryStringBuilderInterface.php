<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\QueryStringBuilder;

use Phlexible\Bundle\IndexerBundle\Query\Query\QueryInterface;

/**
 * Query string builder interface
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
interface QueryStringBuilderInterface
{
    /**
     * Build query (Solrs 'q' parameter).
     *
     * @param QueryInterface $query
     * @return string
     */
    public function buildQueryString(QueryInterface $query);

    /**
     * Build filter query (Solrs 'fq' parameter).
     *
     * @param QueryInterface $query
     * @return string
     */
    public function buildFilterQueryString(QueryInterface $query);

    /**
     * Build combined query (Solrs 'q' and 'fq' parameters in one single string).
     *
     * @param QueryInterface $query
     * @return string
     */
    public function buildCombinedQueryString(QueryInterface $query);
}