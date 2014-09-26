<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\SearchParametersBuilder;

use Phlexible\Bundle\IndexerBundle\Query\Query\QueryInterface;

/**
 * Search parameters builder interface
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
interface SearchParametersBuilderInterface
{
    /**
     * Create parameters from query
     *
     * @param QueryInterface $query
     * @return array
     */
    public function fromQuery(QueryInterface $query);

    /**
     * Create parameters from string
     *
     * @param string $queryString
     * @param string $filterQueryString
     * @return array
     */
    public function fromString($queryString, $filterQueryString);
}