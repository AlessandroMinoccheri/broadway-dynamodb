<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-05
 * Time: 17:10
 */

namespace Broadway\EventStore\DynamoDb\Objects;


use Broadway\Domain\DateTime;
use Broadway\Domain\DomainMessage;
use Broadway\Serializer\Serializer;

class DeserializeEvent
{
    public static function deserialize($row, Serializer $metadataSerializer, Serializer $payloadSerializer)
    {
        $eventData = ConvertAwsItemToArray::convert($row);

        return new DomainMessage(
            $eventData['uuid'],
            (int)$eventData['playhead'],
            $metadataSerializer->deserialize(json_decode($eventData['metadata'], true)),
            $payloadSerializer->deserialize(json_decode($eventData['payload'], true)),
            DateTime::fromString($eventData['recorded_on'])
        );
    }
}