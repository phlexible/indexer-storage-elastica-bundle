<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\ResultRenderer;

use Phlexible\Bundle\IndexerBundle\Document\DocumentFactory;

/**
 * Result renderer
 *
 * @author Marco Fischer <mf@brainbits.net>
 */
class ResultRenderer implements ResultRendererInterface
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

    public function renderToResult($solrResult)
    {
        // @TODO inject!
        $result = \MWF_Registry::getContainer()->indexerResult;

        $found = $solrResult['response']['numFound'];

        if ($found == 0)
        {
            return $result;
        }

        $maxScore  = $solrResult['response']['maxScore'];
        $highlight = $solrResult['highlighting'];

        foreach ($solrResult['response']['docs'] as $doc)
        {
            $identifier    = $doc['_identifier_'];
            $documentClass = $doc['_documentclass_'];
            $documentType  = $doc['_documenttype_'];
            unset($doc['_identifier_'], $doc['_documentclass_'], $doc['_documenttype_']);

            $document = $this->documentFactory->factory($documentClass, $documentType);
            $document->setIdentifier($identifier);
            $document->setRelevance(round($doc['score'] / $maxScore * 100, 2));
            unset($doc['score']);

            $highlightTitle = $doc['title'];
            if (!empty($highlight[$identifier]['title']))
            {
                $highlightTitle = $highlight[$identifier]['title'][0];
                $highlightTitle = \Brainbits_Util_String::stripBrokenHtmlEntities($highlightTitle);
            }
            $document->setValue('highlight_title', $highlightTitle);

            // use highlighted content
            $copy = $doc['copy'];
            if (!empty($highlight[$identifier]['copy']))
            {
                $copy = $highlight[$identifier]['copy'];
                $copy = \Brainbits_Util_String::stripBrokenHtmlEntities($copy);
            }
            $document->setValue('copy', $copy);
            unset($doc['copy']);

            $mapping = $this->fieldNameMapper->getMapping($documentClass, $documentType);

            // copy known fields
            foreach ($mapping as $documentKey => $solrKey)
            {
                if (isset($doc[$solrKey]))
                {
                    $document->setValue($documentKey, $doc[$solrKey]);
                    unset($doc[$solrKey]);
                }
            }

            // copy implicit fields
            foreach ($doc as $key => $value)
            {
                $keyPrefix = substr($key, 0, 8);

                if ($keyPrefix === 'attr_is_' ||
                    $keyPrefix === 'attr_im_' ||
                    $keyPrefix === 'attr_ss_' ||
                    $keyPrefix === 'attr_sm_' ||
                    $keyPrefix === 'copy_is_' ||
                    $keyPrefix === 'copy_im_' ||
                    $keyPrefix === 'copy_ss_' ||
                    $keyPrefix === 'copy_sm_')
                {
                    $documentKey = substr($key, 8);
                    $document->setValue($documentKey, $doc[$key], true);
                    unset($doc[$key]);
                }
            }

            $result->addDocument($document);
        }

        return $result;
    }
}