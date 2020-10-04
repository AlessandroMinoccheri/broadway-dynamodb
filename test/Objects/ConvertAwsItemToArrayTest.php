<?php

namespace Tests\Objects;

use Broadway\EventStore\DynamoDb\Objects\ConvertAwsItemToArray;
use PHPUnit\Framework\TestCase;

/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-28
 * Time: 18:23
 */

class ConvertAwsItemToArrayTest extends TestCase
{
    public function testReturnNullIfItemIsEmpty()
    {
        $resultConverted = ConvertAwsItemToArray::convert(null);
        $this->assertNull($resultConverted);
    }

    public function testThrowExceptionIfTypeNotFound()
    {
        $this->expectException(\Exception::class);
        ConvertAwsItemToArray::convert([['notExistingKey' => 'foo']]);
    }

    public function testPassCorrectKeyReturnData()
    {
        $itemValue = 'foo';
        $resultConverted = ConvertAwsItemToArray::convert([['S' => $itemValue]]);

        $this->assertEquals([$itemValue], $resultConverted);
    }
}
