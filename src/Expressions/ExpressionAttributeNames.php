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
    private $expression;

    public function __construct()
    {
        $this->expression = array();
    }

    public function addField($field)
    {
        $this->expression['#' . $field] = $field;
    }

    public function getExpression()
    {
        return $this->expression;
    }
}