<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-06
 * Time: 09:27
 */

namespace Broadway\EventStore\DynamoDb\Expressions;


class FilterExpression
{
    private $expression;

    public function __construct()
    {
        $this->expression = '';
    }

    public function addComma()
    {
        $this->expression .= ', ';
    }

    public function addConditionOperator(string $condition)
    {
        $this->expression .= ' ' . $condition . ' ';
    }

    public function addInCondition($field)
    {
        $this->expression .=  '#' . $field . ' IN(';
    }

    public function addInFieldWithPosition(string $field, int $position)
    {
        $positionField = $position + 1;

        $this->expression .= ':' . $field . $positionField;
    }

    public function addField(string $field)
    {
        $this->expression .=  '#' . $field . ' = :' . $field;
    }

    public function close()
    {
        $this->expression .= ')';
    }

    public function getExpression() :string
    {
        return $this->expression;
    }
}