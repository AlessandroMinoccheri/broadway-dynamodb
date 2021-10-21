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
    private string $expression;

    public function __construct()
    {
        $this->expression = '{';
    }

    public function addComma(): void
    {
        $this->expression .= ',';
    }

    /**
     * @param string $field
     * @param int    $position
     * @param mixed  $value
     */
    public function addFieldWithPosition(string $field, int $position, $value): void
    {
        $positionField = $position + 1;

        $value = $this->addQuoteIfIsString($value);

        $this->expression .= '":' . $field. $positionField . '":'. $value;
    }

    /**
     * @param string $field
     * @param mixed  $value
     */
    public function addField(string $field, $value): void
    {
        $value = $this->addQuoteIfIsString($value);

        $this->expression .= '":' . $field . '":'. $value;
    }

    public function close(): void
    {
        $this->expression .= '}';
    }

    public function getExpression() :string
    {
        return $this->expression;
    }

    /**
     * @param mixed $value
     * @return mixed|string
     */
    private function addQuoteIfIsString($value)
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }

        return $value;
    }
}
