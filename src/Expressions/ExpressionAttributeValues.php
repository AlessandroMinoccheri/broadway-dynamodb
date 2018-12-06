<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-06
 * Time: 10:37
 */

namespace Broadway\EventStore\DynamoDb\Expressions;


class ExpressionAttributeValues
{
    private $expression;

    public function __construct()
    {
        $this->expression = '{';
    }

    public function addComma()
    {
        $this->expression .= ',';
    }

    public function addFieldWithPosition(string $field, int $position, $value)
    {
        $positionField = $position + 1;

        $value = $this->addQuoteIfIsString($value);

        $this->expression .= '":' . $field. $positionField . '":'. $value;
    }

    public function addField(string $field, $value)
    {
        $value = $this->addQuoteIfIsString($value);

        $this->expression .= '":' . $field . '":'. $value;
    }

    public function close()
    {
        $this->expression .= '}';
    }

    public function getExpression() :string
    {
        return $this->expression;
    }

    private function addQuoteIfIsString($value)
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }

        return $value;
    }
}