<?php

namespace Broadway\EventStore\DynamoDb\Objects;

class ScanFilter
{
    private $json;
    private $filter;

    public function __construct(array $fields)
    {
        $this->json = '{';
        $this->filter = '';
        $firstField = true;

        foreach ($fields as $field => $value) {
            if (!$firstField) {
                $this->json .= ',';
                $this->filter .= ' and ';
            }

            if (is_string($value)) {
                $value = '"' . $value . '"';
            }

            $this->json .= '":' . $field . '":'. $value;
            $this->filter .=  $field . ' = :' . $field;
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
}