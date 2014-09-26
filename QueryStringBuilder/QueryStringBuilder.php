<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\QueryStringBuilder;

use Phlexible\Bundle\IndexerBundle\Document\DocumentFactory;
use Phlexible\Bundle\IndexerBundle\Query\Query\QueryInterface;

/**
 * Query string builder
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class QueryStringBuilder implements QueryStringBuilderInterface
{
    /**
     * @var DocumentFactory
     */
    protected $documentFactory;

    /**
     * @param DocumentFactory $documentFactory
     */
    public function __construct(DocumentFactory $documentFactory)
    {
        $this->documentFactory = $documentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function buildQueryString(QueryInterface $query)
    {
        $boost    = $query->getBoost();
        $parser   = $query->getParser();
        $positive = $parser->getPositiveTerms();
        $negative = $parser->getNegativeTerms();

        $parts = array();

        if (count($positive))
        {
            $dismaxParts = array();
            foreach ($positive as $term)
            {
                $escapedTerm = mb_strtolower($this->_escape($term));

                $dismaxQueries = array();
                foreach ($this->_getSelectFields($query) as $field)
                {
                    $dismaxQueries[] = $this->_getDismaxString($field, $escapedTerm, $query);
                }

                $dismaxParts[] = '+(' . implode(' | ', $dismaxQueries) . ')';
            }

            $phraseQueries = array();
            if (count($positive) > 1)
            {
                foreach ($this->_getSelectFields($query) as $field)
                {
                    $boostField = $boost ? $boost->getBoost($field) : 1;
                    $phraseQueries[] = $field . ':"' . implode(' ', $positive). '"^' . $this->_boost($boostField, 7.5);
                }
            }

            $parts[] = '+(' . implode(' | ', array_merge($phraseQueries, $dismaxParts)) . ')';
        }
        else
        {
            $parts[] = '*:*';
        }

        foreach ($negative as $term)
        {
            $escapedTerm = $this->_escape($term);

            $dismaxQueries = array();
            foreach ($this->_getSelectFields($query) as $field)
            {
                $dismaxQueries[] = $this->_getDismaxString($field, $escapedTerm, $query);
            }

            $parts[] = '-(' . implode(' | ', $dismaxQueries) . ')';
        }

        $queryString = implode(' ', $parts);

        return $queryString;
    }

    /**
     * {@inheritdoc}
     */
    public function buildFilterQueryString(QueryInterface $query)
    {
        $filters = (array) $query->getFilters();

        $documentTypes = $query->getDocumentTypes();
        if (count($documentTypes))
        {
            $filters['_documenttype_'] = $documentTypes;
        }

        $queryString = $this->_parseFilter($filters, $documentTypes);

        return $queryString;
    }

    /**
     * {@inheritdoc}
     */
    public function buildCombinedQueryString(QueryInterface $query)
    {
        $queryString = trim(sprintf('%s %s', $this->buildQueryString($query), $this->buildFilterQueryString($query)));

        return $queryString;
    }

    protected function _parseFilter(array $filters, array $documentTypes)
    {
        $parts = array();
        foreach ($filters as $key => $value)
        {
            if (is_numeric($key))
            {
                $filterString = $this->_parseAnd($value, $documentTypes);
            }
            else
            {
                $filterString = $this->_parseOr($key, (array) $value, $documentTypes);
            }

            if (mb_strlen($filterString))
            {
                $parts[] = $filterString;
            }
        }

        if (!count($parts))
        {
            return '';
        }

        $parts = array_unique($parts);

        return '+(+' . implode(' +', $parts) . ')';
    }

    protected function _parseAnd(array $parts, array $documentTypes)
    {
        $ands = array();
        foreach ($parts as $part)
        {
            foreach ($part as $key => $value)
            {
                if (is_numeric($key))
                {
                    $filterString = $this->_parseAnd($value, $documentTypes);
                }
                else
                {
                    $filterString = $this->_parseOr($key, (array) $value, $documentTypes);
                }

                if (mb_strlen($filterString))
                {
                    $ands[] = $filterString;
                }
            }
        }

        $ands = array_unique($ands);

        $filterString = count($ands)
            ? '(' . implode(' | ', $ands) . ')'
            : '';

        return $filterString;
    }

    protected function _parseOr($key, array $values, $documentTypes)
    {
        $values = array_unique($values);

        $filters = array();
        foreach ($values as $filterValueItem)
        {
            $mappedFieldNames = $this->getMappedFieldNames($key, $documentTypes);
            foreach ($mappedFieldNames as $mappedFieldName)
            {
                $filters[] = $mappedFieldName . ':' . $filterValueItem;
            }
        }

        $filters = array_unique($filters);

        $filterString = count($filters)
            ? '(' . implode(' | ', $filters) . ')'
            : '';

        return $filterString;
    }

    /**
     * Return select fields
     *
     * @param QueryInterface $query
     * @return array
     */
    protected function _getSelectFields(QueryInterface $query)
    {
        $fields = array();

        foreach ($query->getDocumentTypes() as $documentType)
        {
            $document = $this->documentFactory
                ->factory('Phlexible\IndexerComponent\Document\Document', $documentType);

            foreach ($query->getFields() as $key)
            {
                if ($document->hasField($key))
                {
                    $fieldConfig = $document->getField($key);
                    $fields[]    = $key; //$this->fieldNameMapper->map($key, $fieldConfig);
                }
            }
        }

        array_unique($fields);

        return $fields;
    }

    protected function _boost($a, $b)
    {
        $boost = $a * $b;

        return str_replace(',', '.', (string)round($boost, 2));
    }

    protected function _getDismaxString($field, $term, QueryInterface $query)
    {
        $multiplierClosure = 5;
        $multiplierExact   = 2.0;
        $multiplierPart    = 1.5;
        $multiplierFuzzy   = 1.0;

        $boost = $query->getBoost();
        $boostField = $boost ? $boost->getBoost($field) : 1;
        $precision = $boost ? $boost->getPrecision($field) : 1;
        $precision = str_replace(',', '.', $precision);

        $dismaxParts = array();

        if (false !== strpos($term, ' '))
        {
            $dismaxParts[] = $field . ':"' .  $term . '"^' . $this->_boost($boostField, $multiplierClosure);
            $dismaxParts[] = $field . ':"' .  $term . '"~' . $precision . '^' . $this->_boost($boostField, $multiplierFuzzy);
        }
        else
        {
            $dismaxParts[] = $field . ':' .  $term . '^' . $this->_boost($boostField, $multiplierExact);
            $dismaxParts[] = $field . ':*' . $term . '*^' . $this->_boost($boostField, $multiplierPart);
            $dismaxParts[] = $field . ':' .  $term . '~' . $precision . '^' . $this->_boost($boostField, $multiplierFuzzy);
        }

        return implode(' | ', $dismaxParts);
    }

    /**
     * Escape a value for special query characters such as ':', '(', ')', '*', '?', etc.
     *
     * NOTE: inside a phrase fewer characters need escaped, use {@link Brainbits_Solr::escapePhrase()} instead
     *
     * @param string $value
     * @return string
     */
    protected function _escape($value)
    {
        //list taken from http://lucene.apache.org/java/docs/queryparsersyntax.html#Escaping%20Special%20Characters
        $pattern = '/(\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
    }

    /**
     * Get all possible mapped field names for all document types for a filed.
     *
     * @param string $fieldName
     * @param array  $documentTypes
     *
     * @return array
     */
    protected function getMappedFieldNames($fieldName, array $documentTypes)
    {
        $mappedFieldNames = array();

        foreach ($documentTypes as $documentType)
        {
            $document = $this->documentFactory
                ->factory('Phlexible\IndexerComponent\Document\Document', $documentType);

            if ($document->hasField($fieldName))
            {
                $mappedFieldNames[] = $fieldName;
            }
        }

        return $mappedFieldNames;
    }
}
