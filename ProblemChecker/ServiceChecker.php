<?php

/*
 * This file is part of the phlexible indexer storage elastica package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\ProblemChecker;

use Phlexible\Bundle\ElasticaBundle\Elastica\Index;
use Phlexible\Bundle\ProblemBundle\Entity\Problem;
use Phlexible\Bundle\ProblemBundle\ProblemChecker\ProblemCheckerInterface;

/**
 * Service check
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ServiceChecker implements ProblemCheckerInterface
{
    /**
     * Driver object to communicate with solr.
     *
     * @var Index
     */
    private $index;

    /**
     * @param Index $index
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    /**
     * {@inheritdoc}
     */
    public function check()
    {
        $problems = array();

        try {
            $status = $this->index->getStatus();

            if (!$status->get('index')) {
                $problem = new Problem();
                $problem
                    ->setId('indexerstorageelastica_check_not_responding')
                    ->setCheckClass(__CLASS__)
                    ->setIconClass('p-indexerstorageelastica-component-icon')
                    ->setSeverity(Problem::SEVERITY_WARNING)
                    ->setMessage('No elasticsearch server responding.')
                    ->setHint('Check if configured elasticsearch server is running.')
                ;
                $problems[] = $problem;
            }
        } catch (\Exception $e) {
            $problem = new Problem();
            $problem
                ->setId('indexerstorageelastica_status_exception')
                ->setCheckClass(__CLASS__)
                ->setIconClass('p-indexerstorageelastica-component-icon')
                ->setSeverity(Problem::SEVERITY_WARNING)
                ->setMessage('Error getting status of index, message: ' . $e->getMessage())
            ;
            $problems[] = $problem;
        }

        return $problems;
    }
}
