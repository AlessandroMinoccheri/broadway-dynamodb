<?php

namespace Broadway\EventStore\DynamoDb\Expressions;

class CriteriaExpressionBuilder
{
    private FilterExpression $filterExpression;
    private ExpressionAttributeNames $expressionAttributeNames;
    private ExpressionAttributeValues $expressionAttributeValues;

    public function __construct(array $fields)
    {
        $this->filterExpression = new FilterExpression();
        $this->expressionAttributeNames = new ExpressionAttributeNames();
        $this->expressionAttributeValues = new ExpressionAttributeValues();

        $firstField = true;

        foreach ($fields as $field => $value) {
            if (!$firstField) {
                $this->expressionAttributeValues->addComma();
                $this->filterExpression->addConditionOperator('and');
            }

            if (is_array($value) && array_key_exists('in', $value)) {
                $this->filterExpression->addInCondition($field);
                foreach ($value['in'] as $position => $inConditionValue) {
                    if ($position !== 0) {
                        $this->expressionAttributeValues->addComma();
                        $this->filterExpression->addComma();
                    }

                    $this->expressionAttributeValues->addFieldWithPosition($field, $position, $inConditionValue);
                    $this->filterExpression->addInFieldWithPosition($field, $position);
                }

                $this->filterExpression->close();
                $this->expressionAttributeNames->addField($field);
            } else {
                $this->expressionAttributeValues->addField($field, $value);
                $this->filterExpression->addField($field);
                $this->expressionAttributeNames->addField($field);
            }

            $firstField = false;
        }

        $this->expressionAttributeValues->close();
    }

    public function getExpressionAttributeValues() :string
    {
        return $this->expressionAttributeValues->getExpression();
    }

    public function getExpressionAttributeNames() :array
    {
        return $this->expressionAttributeNames->getExpression();
    }

    public function getFilterExpression() :string
    {
        return $this->filterExpression->getExpression();
    }
}