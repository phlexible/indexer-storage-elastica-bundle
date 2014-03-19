<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\IndexerStorageElasticaComponent;

use Phlexible\Component\Component;

/**
 * Elastica indexer storage component
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class IndexerStorageElasticaComponent extends Component
{
    public function __construct()
    {
        $this
            ->setVersion('0.7.2')
            ->setId('indexerstorageelastica')
            ->setPackage('phlexible');
    }
}
