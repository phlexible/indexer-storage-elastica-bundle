<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\ResultRenderer;

/**
 * Result renderer interface
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
interface ResultRendererInterface
{
    /**
     * @param array $solrResult
     * @return string
     */
    public function renderToResult($solrResult);
}