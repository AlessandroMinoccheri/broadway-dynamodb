<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-06
 * Time: 10:47
 */

namespace Tests\Expressions;


use Broadway\EventStore\DynamoDb\Expressions\ExpressionAttributeNames;
use PHPUnit\Framework\TestCase;

class ExpressionAttributeNamesTest extends TestCase
{
    public function testCreateExpressionAttributeNames()
    {
        $field = 'foo';
        $expressionAttributeNames = new ExpressionAttributeNames();
        $expressionAttributeNames->addField($field);

        $expected = [
            '#' . $field => $field
        ];

        $this->assertEquals($expected, $expressionAttributeNames->getExpression());
    }
}