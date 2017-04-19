<?php

/*
 * This file is part of the phlexible indexer storage elastica package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Type\Mapping;
use Phlexible\Bundle\IndexerStorageElasticaBundle\Exception\InvalidArgumentException;

/**
 * Initializer interface.
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
interface InitializerInterface
{
    /**
     * @return Mapping[]
     *
     * @throws InvalidArgumentException
     */
    public function createMappings();

    /**
     * @return array
     */
    public function createConfig();

    /**
     * @param Mapping[] $mappings
     * @param array     $config
     * @param bool      $recreate
     */
    public function initialize(array $mappings, array $config, $recreate = false);
}
