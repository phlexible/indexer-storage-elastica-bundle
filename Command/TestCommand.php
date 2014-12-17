<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Command;

use Phlexible\Bundle\IndexerBundle\Query\Facet\TermsFacet;
use Phlexible\Bundle\IndexerBundle\Query\Filter\TermFilter;
use Phlexible\Bundle\IndexerBundle\Query\Query\QueryString;
use Phlexible\Bundle\IndexerBundle\Query\Suggest;
use Phlexible\Bundle\IndexerBundle\Query\Suggest\TermSuggest;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test command
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class TestCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('indexer-storage-elastica:test')
            ->setDescription('Test elastica storage.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storage = $this->getContainer()->get('phlexible_indexer.storage.default');

        $query = $storage->createQuery();
        //$query->setQuery(new QueryString('Werbeprogramm'));

        $facet = new TermsFacet('blubb');
        $facet->setField('tid');
        $query->setFacets(array($facet));

        $filter = new TermFilter(array('elementtype' => 'faq'));
        $query->setFilter($filter);

        $suggestion = new TermSuggest('suggi', 'elementtype');
        $suggest = new Suggest($suggestion);
        $suggest->setGlobalText("antworte");
        $query->setSuggest($suggest);

        $result = $storage->query($query);

        ldd($result);

        /* @var $result \Elastica\ResultSet */
        foreach ($result as $id => $hit) {
            echo 'Document ' . $hit->getId() . ': ';
            ld($hit->getData());
        }

        foreach ($result->getFacets() as $name => $facet) {
            echo 'Facet ' . $name . ': ';
            ld($facet);
        }

        foreach ($result->getSuggests() as $name => $suggest) {
            echo 'Suggest ' . $name . ': ';
            ld($suggest);
        }

        return 0;
    }

}
