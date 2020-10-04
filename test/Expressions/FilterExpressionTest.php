<?php

/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-06
 * Time: 11:00
 */

namespace Tests\Expressions;


use Broadway\EventStore\DynamoDb\Expressions\FilterExpression;
use PHPUnit\Framework\TestCase;

class FilterExpressionTest extends TestCase
{
    private $filterExpression;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterExpression = new FilterExpression();
    }

    public function testAddField()
    {
        $field = 'foo';

        $this->filterExpression->addField($field);

        $expected = '#' . $field . ' = :' . $field;

        $this->assertEquals($expected, $this->filterExpression->getExpression());
    }

    public function testInFieldWithPosition()
    {
        $field = 'foo';
        $position = random_int(1, 9999);
        $positionExpected = $position + 1;

        $this->filterExpression->addInFieldWithPosition($field, $position);

        $expected = ':' . $field . $positionExpected;

        $this->assertEquals($expected, $this->filterExpression->getExpression());
    }

    public function testAddConditionOperator()
    {
        $condition = 'or';

        $this->filterExpression->addConditionOperator($condition);

        $expected = ' or ';

        $this->assertEquals($expected, $this->filterExpression->getExpression());
    }

    public function testAddInCondition()
    {
        $field = 'field';

        $this->filterExpression->addInCondition($field);

        $expected = '#' . $field . ' IN(';

        $this->assertEquals($expected, $this->filterExpression->getExpression());
    }

    public function testAddComma()
    {
        $this->filterExpression->addComma();

        $expected = ', ';

        $this->assertEquals($expected, $this->filterExpression->getExpression());
    }
}

