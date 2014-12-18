<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage;

use Elastica\Query as ElasticaQuery;
use Elastica\Aggregation as ElasticaAggregation;
use Elastica\Facet as ElasticaFacet;
use Elastica\Filter as ElasticaFilter;
use Elastica\Suggest as ElasticaSuggest;
use Phlexible\Bundle\IndexerBundle\Query\Aggregation\AggregationInterface;
use Phlexible\Bundle\IndexerBundle\Query\Aggregation;
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
        if (is_int($query->getSize())) {
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
            foreach ($query->getFacets() as $facet) {
                $elasticaFacet = $this->mapFacet($facet);
                if ($elasticaFacet) {
                    $elasticaQuery->addFacet($elasticaFacet);
                }
            }
        }
        if ($query->getAggregations()) {
            foreach ($query->getAggregations() as $aggregation) {
                $elasticaAggregation = $this->mapAggregation($aggregation);
                if ($elasticaAggregation) {
                    $elasticaQuery->addAggregation($elasticaAggregation);
                }
            }
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
        } elseif ($query instanceof Query\TermQuery) {
            $elasticaQuery = new ElasticaQuery\Term();
            foreach ($query->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaQuery->$method($value);
            }

            return $elasticaQuery;
        } elseif ($query instanceof Query\PrefixQuery) {
            $elasticaQuery = new ElasticaQuery\Prefix();
            foreach ($query->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaQuery->$method($value);
            }

            return $elasticaQuery;
        } elseif ($query instanceof Query\MultiMatchQuery) {
            $elasticaQuery = new ElasticaQuery\MultiMatch();
            foreach ($query->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaQuery->$method($value);
            }

            return $elasticaQuery;
        } elseif ($query instanceof Query\FuzzyQuery) {
            $elasticaQuery = new ElasticaQuery\Fuzzy();
            $field = $query->getParam('field');
            $elasticaQuery->setField($field['fieldName'], $field['value']);
            foreach ($field['options'] as $option => $value) {
                $elasticaQuery->setFieldOption($option, $value);
            }

            return $elasticaQuery;
        } elseif ($query instanceof Query\DismaxQuery) {
            $elasticaQuery = new ElasticaQuery\Dismax();
            foreach ($query->getQueries() as $query) {
                $elasticaQuery->addQuery($this->mapQuery($query));
            }
            $elasticaQuery->addQuery($query);
            foreach ($query->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaQuery->$method($value);
            }

            return $elasticaQuery;
        } elseif ($query instanceof Query\BoolQuery) {
            $elasticaQuery = new ElasticaQuery\Bool();
            foreach ($query->getQueries() as $type => $queries) {
                foreach ($queries as $subQuery) {
                    $method = 'add' . ucfirst($type);
                    $elasticaQuery->$method($this->mapQuery($subQuery));
                }
            }
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
            foreach ($filter->getFilters() as $subFilter) {
                $elasticaSubFilter = $this->mapFilter($subFilter);
                $elasticaFilter->addFilter($elasticaSubFilter);
            }
            foreach ($filter->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                $elasticaFilter->$method($value);
            }

            return $elasticaFilter;
        } elseif ($filter instanceof Filter\BoolOrFilter) {
            $elasticaFilter = new ElasticaFilter\BoolOr();
            foreach ($filter->getFilters() as $subFilter) {
                $elasticaSubFilter = $this->mapFilter($subFilter);
                $elasticaFilter->addFilter($elasticaSubFilter);
            }
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
     * @param FacetInterface $facet
     *
     * @return ElasticaFacet\AbstractFacet
     */
    private function mapFacet(FacetInterface $facet)
    {
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

            return $elasticaFacet;
        }

        return null;
    }

    /**
     * @param AggregationInterface $aggregation
     *
     * @return ElasticaAggregation\AbstractAggregation
     */
    private function mapAggregation(AggregationInterface $aggregation)
    {
        if ($aggregation instanceof Aggregation\TermsAggregation) {
            $elasticaAggregation = new ElasticaAggregation\Terms($aggregation->getName());
            foreach ($aggregation->getParams() as $key => $value) {
                $method = 'set' . ucfirst($key);
                if ($method === 'setOrder') {
                    $elasticaAggregation->$method(key($value), current($value));
                if ($method === 'setInclude' || $method === 'setExclude') {
                    if (is_array($value)) {
                        $elasticaAggregation->$method(key($value), current($value));
                    } else {
                        $elasticaAggregation->$method($value);
                    }
                }
                } else {
                    $elasticaAggregation->$method($value);
                }
            }

            return $elasticaAggregation;
        }

        return null;
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
            } elseif ($suggestion instanceof Suggest\PhraseSuggest) {
                $elasticaSuggestion = new ElasticaSuggest\Phrase($suggestion->getName(), '');
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
