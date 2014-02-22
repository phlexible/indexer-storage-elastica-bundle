<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\IndexerStorageElasticaComponent\ProblemChecker;

use Elastica\Client;
use Phlexible\ProblemsComponent\Entity\Problem;
use Phlexible\ProblemsComponent\ProblemChecker\ProblemCheckerInterface;

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
     * @var Client
     */
    protected $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     *
     * @see MWF_Core_Problems_Check_Interface::check()
     */
    public function check()
    {
        $problems = array();

        $status = $this->client->getStatus();
        $serverStatus = $status->getServerStatus();

        try
        {
            if (!isset($serverStatus['status']) || $serverStatus['status'] != 200)
            {
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
        }
        catch (\Exception $e)
        {
            $problem = new Problem();
            $problem
                ->setId('indexerstorageelastica_check_exception')
                ->setCheckClass(__CLASS__)
                ->setIconClass('p-indexerstorageelastica-component-icon')
                ->setSeverity(Problem::SEVERITY_WARNING)
                ->setMessage('Error checking elasticsearch server , message: ' . $e->getMessage())
            ;
            $problems[] = $problem;
        }

        return $problems;
    }
}
