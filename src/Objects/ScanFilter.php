<?php

namespace Broadway\EventStore\DynamoDb\Objects;

class ScanFilter
{
    private $json;
    private $filter;
    private $attributeNames;

    public function __construct(array $fields)
    {
        $this->json = '{';
        $this->filter = '';
        $this->attributeNames = [];

        $firstField = true;

        foreach ($fields as $field => $value) {
            if (!$firstField) {
                $this->json .= ',';
                $this->filter .= ' and ';
            }

            if (is_array($value) && array_key_exists('in', $value)) {
                $this->filter .=  '#' . $field . ' IN (';
                foreach ($value['in'] as $position => $inConditionValue) {
                    if ($position !== 0) {
                        $this->filter .= ', ';
                        $this->json .= ',';
                    }

                    $positionField = $position + 1;

                    if (is_string($inConditionValue)) {
                        $inConditionValue = '"' . $inConditionValue . '"';
                    }

                    $this->json .= '":' . $field. $positionField . '":'. $inConditionValue;
                    $this->filter .= ':' . $field . $positionField;
                }

                $this->filter .= ')';
                $this->attributeNames['#' . $field] = $field;
            } else {
                if (is_string($value)) {
                    $value = '"' . $value . '"';
                }

                $this->json .= '":' . $field . '":'. $value;
                $this->filter .=  '#' . $field . ' = :' . $field;
                $this->attributeNames['#' . $field] = $field;
            }


            $firstField = false;
        }

        $this->json .= '}';
    }

    /**
     * @return mixed
     */
    public function getJson() :string
    {
        return $this->json;
    }

    /**
     * @return mixed
     */
    public function getFilter() :string
    {
        return $this->filter;
    }

    public function getAttributeNames() :array
    {
        return $this->attributeNames;
    }
}