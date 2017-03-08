<?php

/*
 * This file is part of the phlexible indexer storage elastica package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Tests\Fixture;

use Phlexible\Bundle\IndexerBundle\Document\Document;

class TestDocument extends Document
{
    public function getName()
    {
        return 'testType';
    }
}
