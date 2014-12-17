<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Query as ElasticaQuery;
use Elastica\Facet as ElasticaFacet;
use Elastica\Filter as ElasticaFilter;
use Elastica\Suggest as ElasticaSuggest;
use Phlexible\Bundle\IndexerBundle\Query\Facet\FacetInterface;
use Phlexible\Bundle\IndexerBundle\Query\Facet;
use Phlexible\Bundle\IndexerBundle\Query\Filter\FilterInterface;
use Phlexible\Bundle\IndexerBundle\Query\Filter;
use Phlexible\Bundle\IndexerBundle\Query\Query;
use Phlexible\Bundle\IndexerBundle\Query\Query\QueryInterface;
use Phlexible\Bundle\IndexerBundle\Query\Suggest;
use Phlexible\Bundle\IndexerBundle\Query\Suggest\AbstractSuggest;

/**
 * Elasticsearch query mapper
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class QueryMapper
{
    /**
     * @param Query $query
     *
     * @return ElasticaQuery
     */
    public function map(Query $query)
    {
        $elasticaQuery = new ElasticaQuery();
        if ($query->getSize()) {
            $elasticaQuery->setSize($query->getSize());
        }
        if ($query->getStart()) {
            $elasticaQuery->setFrom($query->getStart());
        }
        if ($query->getHighlight()) {
            $elasticaQuery->setHighlight($query->getHighlight());
        }
        if ($query->getSort()) {
            $elasticaQuery->setSort($query->getSort());
        }
        if ($query->getExplain()) {
            $elasticaQuery->setExplain($query->getExplain());
        }
        if ($query->getMinScore()) {
            $elasticaQuery->setMinScore($query->getMinScore());
        }
        if ($query->getVersion()) {
            $elasticaQuery->setVersion($query->getVersion());
        }
        if ($query->getFields()) {
            $elasticaQuery->setFields($query->getFields());
        }
        if ($query->getSource()) {
            $elasticaQuery->setSource($query->getSource());
        }

        if ($query->getQuery()) {
            $elasticaQuery->setQuery($this->mapQuery($query->getQuery()));
        }
        if ($query->getFilter()) {
            $elasticaQuery->setPostFilter($this->mapFilter($query->getFilter()));
        }
        if ($query->getFacets()) {
            $elasticaQuery->setFacets($this->mapFacets($query->getFacets()));
        }
        if ($query->getSuggest()) {
            $suggest = $this->mapSuggest($query->getSuggest());
            if ($suggest) {
                $elasticaQuery->setSuggest($suggest);
            }
        }

        return $elasticaQuery;
    }

    /**
     * @param QueryInterface $query
     *
     * @return ElasticaQuery\AbstractQuery|null
     */
    private function mapQuery(QueryInterface $query)
    {
        if ($query instanceof Query\QueryString) {
            $elasticaQuery = new ElasticaQuery\QueryString();
            foreach ($query->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaQuery->$method($value);
            }

            return $elasticaQuery;
        }

        return null;
    }

    /**
     * @param FilterInterface $filter
     *
     * @return ElasticaFilter\AbstractFilter|null
     */
    private function mapFilter(FilterInterface $filter)
    {
        if ($filter instanceof Filter\TermFilter) {
            $elasticaFilter = new ElasticaFilter\Term();
            foreach ($filter->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaFilter->$method($value);
            }

            return $elasticaFilter;
        } elseif ($filter instanceof Filter\BoolAndFilter) {
            $elasticaFilter = new ElasticaFilter\BoolAnd();
            foreach ($filter->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaFilter->$method($value);
            }

            return $elasticaFilter;
        } elseif ($filter instanceof Filter\BoolOrFilter) {
            $elasticaFilter = new ElasticaFilter\BoolAnd();
            foreach ($filter->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaFilter->$method($value);
            }

            return $elasticaFilter;
        } elseif ($filter instanceof Filter\BoolNotFilter) {
            $elasticaFilter = new ElasticaFilter\BoolAnd();
            foreach ($filter->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaFilter->$method($value);
            }

            return $elasticaFilter;
        }

        return null;
    }

    /**
     * @param FacetInterface[] $facets
     *
     * @return ElasticaFacet\AbstractFacet[]
     */
    private function mapFacets(array $facets)
    {
        $elasticaFacets = array();
        foreach ($facets as $facet) {
            if ($facet instanceof Facet\TermsFacet) {
                $elasticaFacet = new ElasticaFacet\Terms($facet->getName());
                foreach ($facet->getParams() as $key => $value) {
                    $method = 'set' . ucfirst($key);
                    $elasticaFacet->$method($value);
                }

                $elasticaFacets[] = $elasticaFacet;
            } elseif ($facet instanceof Facet\QueryFacet) {
                $elasticaFacet = new ElasticaFacet\Query($facet->getName());
                foreach ($facet->getParams() as $key => $value) {
                    $method = 'set' . ucfirst($key);
                    $elasticaFacet->$method($value);
                }

                $elasticaFacets[] = $elasticaFacet;
            }
        }

        return $elasticaFacets;
    }

    /**
     * @param Suggest $suggest
     *
     * @return ElasticaSuggest\AbstractSuggest|null
     */
    private function mapSuggest(Suggest $suggest)
    {
        $elasticaSuggest = new ElasticaSuggest();

        $elasticaSuggestions = array();
        foreach ($suggest->getSuggestions() as $suggestion) {
            /* @var $suggestion AbstractSuggest */
            if ($suggestion instanceof Suggest\TermSuggest) {
                $elasticaSuggestion = new ElasticaSuggest\Term($suggestion->getName(), '');
                foreach ($suggestion->getParams() as $key => $value) {
                    $method = 'set' . ucfirst($key);
                    $elasticaSuggestion->$method($value);
                }

                $elasticaSuggestions[] = $elasticaSuggestion;
            }
        }

        if (!count($elasticaSuggestions)) {
            return null;
        }

        foreach ($suggest->getParams() as $key => $value) {
            $method = 'set' . ucfirst($key);
            $elasticaSuggest->$method($value);
        }

        foreach ($elasticaSuggestions as $elasticaSuggestion) {
            $elasticaSuggest->addSuggestion($elasticaSuggestion);
        }

        return $elasticaSuggest;
    }
}