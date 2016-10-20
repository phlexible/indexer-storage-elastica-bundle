<?php

/*
 * This file is part of the phlexible indexer storage elastica package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\IndexerStorageElasticaBundle\Tests\Storage;

use Elastica\ResultSet as ElasticaResultSet;
use Elastica\Result as ElasticaResult;
use Phlexible\Bundle\IndexerBundle\Document\Document;
use Phlexible\Bundle\IndexerBundle\Document\DocumentFactory;
use Phlexible\Bundle\IndexerStorageElasticaBundle\Storage\ElasticaMapper;
use Prophecy\Prophecy\ObjectProphecy;

class TestDocument extends Document
{
    public function getName()
    {
        return 'testType';
    }
}

/**
 * Elastica mapper test
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElasticaMapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DocumentFactory|ObjectProphecy
     */
    private $documentFactory;

    /**
     * @var ElasticaMapper
     */
    private $mapper;

    public function setUp()
    {
        $this->documentFactory = $this->prophesize('Phlexible\Bundle\IndexerBundle\Document\DocumentFactory');

        $testDocument = new TestDocument();
        $testDocument
            ->setField('firstname')
            ->setField('lastname');

        $this->documentFactory->factory('testType')->willReturn($testDocument);

        $this->mapper = new ElasticaMapper($this->documentFactory->reveal());

    }

    public function testMapDocument()
    {
        $document = new TestDocument();
        $document
            ->setField('firstname')
            ->set('firstname', 'testFirstname')
            ->setField('lastname')
            ->set('lastname', 'testLastname');

        $elasticaDocument = $this->mapper->mapDocument($document, 'testIndex');

        $this->assertSame('testIndex', $elasticaDocument->getIndex());
        $this->assertSame($document->get('firstname'), $elasticaDocument->get('firstname'));
        $this->assertSame($document->get('lastname'), $elasticaDocument->get('lastname'));
    }

    public function testMapResultSet()
    {
        $response = new \Elastica\Response('{"took":16,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":1,"max_score":1.0,"hits":[{"_index":"testIndex","_type":"testType","_id":"123","_score":1.0,"_source":{"firstname":"testFirstname","lastname":"testLastname"}}]}}');
        $query = new \Elastica\Query();
        $elasticaResultSet = new ElasticaResultSet($response, $query);

        $resultSet = $this->mapper->mapResultSet($elasticaResultSet);

        $this->assertSame($elasticaResultSet->getTotalTime(), $resultSet->getTotalTime());
        $this->assertSame($elasticaResultSet->getTotalHits(), $resultSet->getTotalHits());
        $this->assertSame($elasticaResultSet->getMaxScore(), $resultSet->getMaxScore());
        $this->assertSame($elasticaResultSet->current()->getData()['firstname'], $resultSet->current()->get('firstname'));
        $this->assertSame($elasticaResultSet->current()->getData()['lastname'], $resultSet->current()->get('lastname'));
    }

    public function testMapResult()
    {
        $elasticaResult = new ElasticaResult(
            array(
                '_type' => 'testType',
                '_source' => array(
                    'firstname' => 'testFirstname',
                    'lastname' => 'testLastname'
                )
            )
        );
        $document = $this->mapper->mapResult($elasticaResult);

        $this->assertSame($elasticaResult->getData()['firstname'], $document->get('firstname'));
        $this->assertSame($elasticaResult->getData()['lastname'], $document->get('lastname'));
    }
}
