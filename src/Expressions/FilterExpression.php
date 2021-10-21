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
    private string $expression;

    public function __construct()
    {
        $this->expression = '';
    }

    public function addComma(): void
    {
        $this->expression .= ', ';
    }

    public function addConditionOperator(string $condition): void
    {
        $this->expression .= ' ' . $condition . ' ';
    }

    /**
     * @param array-key $field
     */
    public function addInCondition($field): void
    {
        $this->expression .=  '#' . $field . ' IN(';
    }

    public function addInFieldWithPosition(string $field, int $position): void
    {
        $positionField = $position + 1;

        $this->expression .= ':' . $field . $positionField;
    }

    public function addField(string $field): void
    {
        $this->expression .=  '#' . $field . ' = :' . $field;
    }

    public function close(): void
    {
        $this->expression .= ')';
    }

    public function getExpression() :string
    {
        return $this->expression;
    }
}
