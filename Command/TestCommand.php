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
use Phlexible\Bundle\IndexerBundle\Query\Query\BoolQuery;
use Phlexible\Bundle\IndexerBundle\Query\Query\FuzzyQuery;
use Phlexible\Bundle\IndexerBundle\Query\Query\QueryString;
use Phlexible\Bundle\IndexerBundle\Query\Query\TermQuery;
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

        $facet = new TermsFacet('tid');
        $facet->setField('tid');

        $filter = new TermFilter(array('elementtype' => 'faq'));

        $suggestion1 = new TermSuggest('suggi1', '_all');
        $suggestion1->setText('Imprssum');
        $suggestion2 = new TermSuggest('suggi2', '_all');
        $suggestion2->setText('Kntkt');
        $suggest = new Suggest();
        $suggest->addSuggestion($suggestion1);
        $suggest->addSuggestion($suggestion2);

        $q = $storage->createQuery();
        $query = new QueryString('Lorem Hilfe');
        $query->setDefaultOperator('AND');
        $q->setQuery($query);
        //$q->setQuery(new TermQuery(array('title' => 'impressu*')));
        $query = new BoolQuery();
        $query->addMust(new FuzzyQuery('content', 'lorem'));
        $query->addMustNot(new FuzzyQuery('title', 'impressum'));
        //$q->setQuery($query);
        //$q->setFacets(array($facet));
        //$q->setFilter($filter);
        //$q->setSuggest($suggest);

        $result = $storage->query($q);

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
