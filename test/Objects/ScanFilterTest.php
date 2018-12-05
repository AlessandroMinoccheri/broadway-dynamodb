<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-05
 * Time: 15:47
 */

namespace Tests\Objects;

use Broadway\EventStore\DynamoDb\Objects\ScanFilter;
use Ramsey\Uuid\Uuid;

class ScanFilterTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateScanFilterWithSimpleCondition()
    {
        $randomInt = random_int(1, 999999);

        $fields = [
            'foo' => 'baz',
            'bar' => $randomInt
        ];

        $scanFilter = new ScanFilter($fields);

        $this->assertEquals('#foo = :foo and #bar = :bar', $scanFilter->getFilter());
        $this->assertEquals('{":foo":"baz",":bar":' . $randomInt . '}', $scanFilter->getJson());
        $this->assertEquals(['#foo' => 'foo', '#bar' => 'bar'], $scanFilter->getAttributeNames());
    }

    public function testCreateScanFilterWithInCondition()
    {
        $id1 = Uuid::uuid4()->toString();
        $id2 = Uuid::uuid4()->toString();
        $fields = [];
        $fields['uuid'] = ['in' => [$id1, $id2]];

        $scanFilter = new ScanFilter($fields);

        $this->assertEquals('#uuid IN (:uuid1, :uuid2)', $scanFilter->getFilter());
        $this->assertEquals('{":uuid1":"'. $id1 . '",":uuid2":"' . $id2 . '"}', $scanFilter->getJson());
        $this->assertEquals(['#uuid' => 'uuid'], $scanFilter->getAttributeNames());
    }
}