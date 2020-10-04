<?php

/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-06
 * Time: 10:50
 */

namespace Tests\Expressions;


use Broadway\EventStore\DynamoDb\Expressions\ExpressionAttributeValues;
use PHPUnit\Framework\TestCase;

class ExpressionAttributeValuesTest extends TestCase
{
    private $expressionAttributeValues;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expressionAttributeValues = new ExpressionAttributeValues();
    }

    public function testCreateExpressionAttributeValues()
    {

        $this->assertEquals('{', $this->expressionAttributeValues->getExpression());
    }

    public function testAddFieldWithPosition()
    {
        $field = 'foo';
        $position = random_int(1, 999);
        $positionExpected = $position + 1;
        $value = 'bar';

        $this->expressionAttributeValues->addFieldWithPosition($field, $position, $value);

        $this->assertEquals('{":foo' . $positionExpected . '":"bar"', $this->expressionAttributeValues->getExpression());
    }

    public function testAddField()
    {
        $field = 'foo';
        $value = 'bar';

        $this->expressionAttributeValues->addField($field, $value);

        $this->assertEquals('{":foo":"bar"', $this->expressionAttributeValues->getExpression());
    }

    public function testAddComma()
    {
        $this->expressionAttributeValues->addComma();

        $this->assertEquals('{,', $this->expressionAttributeValues->getExpression());
    }

    public function testCloseExpression()
    {
        $this->expressionAttributeValues->close();

        $this->assertEquals('{}', $this->expressionAttributeValues->getExpression());
    }
}

