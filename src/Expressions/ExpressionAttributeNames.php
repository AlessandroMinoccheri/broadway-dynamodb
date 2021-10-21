<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-06
 * Time: 09:42
 */

namespace Broadway\EventStore\DynamoDb\Expressions;


class ExpressionAttributeNames
{
    private array $expression;

    public function __construct()
    {
        $this->expression = [];
    }

    public function addField(string $field): void
    {
        $this->expression['#' . $field] = $field;
    }

    public function getExpression() :array
    {
        return $this->expression;
    }
}