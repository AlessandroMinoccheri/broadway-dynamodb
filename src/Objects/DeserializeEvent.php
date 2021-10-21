<?php

/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-05
 * Time: 17:10
 */

namespace Broadway\EventStore\DynamoDb\Objects;


use Aws\ResultInterface;
use Broadway\Domain\DateTime;
use Broadway\Domain\DomainMessage;
use Broadway\Serializer\Serializer;

class DeserializeEvent
{
    /**
     * @param ResultInterface|array $row
     */
    public static function deserialize(
        $row,
        Serializer $metadataSerializer,
        Serializer $payloadSerializer
    ): DomainMessage {
        $eventData = ConvertAwsItemToArray::convert($row);

        if (null === $eventData) {
            throw new \Exception('EventData cannot be null');
        }

        $uuid = $eventData['uuid'];
        $playhead = $eventData['playhead'];
        $payload = $eventData['payload'];
        $metadata = $eventData['metadata'];
        $recordedOn = $eventData['recorded_on'];

        if (null === $uuid) {
            throw new \Exception('uuid key not found');
        }

        if (null === $playhead) {
            throw new \Exception('playhead key not found');
        }

        if (null === $payload) {
            throw new \Exception('payload key not found');
        }

        if (null === $metadata) {
            throw new \Exception('metadata key not found');
        }

        if (null === $recordedOn) {
            throw new \Exception('recorded_on key not found');
        }

        return new DomainMessage(
            $uuid,
            (int) $playhead,
            $metadataSerializer->deserialize(json_decode($metadata, true)),
            $payloadSerializer->deserialize(json_decode($payload, true)),
            DateTime::fromString($recordedOn)
        );
    }
}
