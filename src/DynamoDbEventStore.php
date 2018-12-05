<?php
/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-11-27
 * Time: 17:17
 */

namespace Broadway\EventStore\DynamoDb;


use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\DynamoDb\Objects\ConvertAwsItemToArray;
use Broadway\EventStore\DynamoDb\Objects\ScanFilter;
use Broadway\EventStore\EventStore;
use Broadway\EventStore\EventStreamNotFoundException;
use Broadway\EventStore\EventVisitor;
use Broadway\EventStore\Exception\DuplicatePlayheadException;
use Broadway\EventStore\Management\Criteria;
use Broadway\EventStore\Management\EventStoreManagement;
use Broadway\Serializer\Serializer;
use Broadway\Domain\DateTime;
use Ramsey\Uuid\Uuid;

class DynamoDbEventStore implements EventStore, EventStoreManagement
{

    private $client;
    /**
     * @var Serializer
     */
    private $payloadSerializer;
    /**
     * @var Serializer
     */
    private $metadataSerializer;
    /**
     * @var string
     */
    private $table;

    public function __construct(
        DynamoDbClient $dynamoDbClient,
        Serializer $payloadSerializer,
        Serializer $metadataSerializer,
        string $table
    ) {
        $this->client = $dynamoDbClient;

        $this->payloadSerializer = $payloadSerializer;
        $this->metadataSerializer = $metadataSerializer;
        $this->table = $table;
    }

    /**
     * @param mixed $id
     *
     * @return DomainEventStream
     */
    public function load($id) :DomainEventStream
    {
        $marshaler = new Marshaler();

        $fields = [
            'uuid' => $id,
            'playhead' => 0
        ];

        $scanFilter = new ScanFilter($fields);

        $eav = $marshaler->marshalJson($scanFilter->getJson());

        $items = $this->client->scan(array(
            'TableName' => $this->table,
            'FilterExpression' => '#uuid = :uuid and playhead = :playhead',
            'ExpressionAttributeNames' =>['#uuid' => 'uuid'],
            "ExpressionAttributeValues" => $eav,
        ));

        $events = [];
        foreach ($items['Items'] as $item) {
            $events[] = $this->deserializeEvent($item);
        }

        if (empty($events)) {
            throw new EventStreamNotFoundException(sprintf('EventStream not found for aggregate with id %s for table %s', $id, $this->tableName));
        }

        return new DomainEventStream($events);
    }

    /**
     * @param mixed $id
     * @param int $playhead
     */
    public function loadFromPlayhead($id, int $playhead): DomainEventStream
    {
        $marshaler = new Marshaler();

        $fields = [
            'uuid' => $id,
            'playhead' => $playhead
        ];

        $scanFilter = new ScanFilter($fields);

        $eav = $marshaler->marshalJson($scanFilter->getJson());

        $items = $this->client->scan(array(
            'TableName' => $this->table,
            'FilterExpression' => '#uuid = :uuid and playhead = :playhead',
            'ExpressionAttributeNames' =>['#uuid' => 'uuid'],
            "ExpressionAttributeValues" => $eav,
        ));

        $events = [];
        foreach ($items['Items'] as $item) {
            $events[] = $this->deserializeEvent($item);
        }

        if (empty($events)) {
            throw new EventStreamNotFoundException(sprintf('EventStream not found for aggregate with id %s for table %s', $id, $this->tableName));
        }

        return new DomainEventStream($events);
    }

    private function deserializeEvent($row)
    {
        $eventData = ConvertAwsItemToArray::convert($row);

        return new DomainMessage(
            $eventData['uuid'],
            (int) $eventData['playhead'],
            $this->metadataSerializer->deserialize(json_decode($eventData['metadata'], true)),
            $this->payloadSerializer->deserialize(json_decode($eventData['payload'], true)),
            DateTime::fromString($eventData['recorded_on'])
        );
    }

    /**
     * @param mixed $id
     * @param DomainEventStream $eventStream
     *
     * @throws DuplicatePlayheadException
     */
    public function append($id, DomainEventStream $eventStream)
    {
        //TODO: use transactions
        foreach ($eventStream as $domainMessage) {
            $this->insertMessage($domainMessage);
        }
    }

    private function insertMessage(DomainMessage $domainMessage)
    {
        $data = [
            'id'          => Uuid::uuid4()->toString(),
            'uuid'        => $domainMessage->getId(),
            'playhead'    => $domainMessage->getPlayhead(),
            'metadata'    => json_encode($this->metadataSerializer->serialize($domainMessage->getMetadata())),
            'payload'     => json_encode($this->payloadSerializer->serialize($domainMessage->getPayload())),
            'recorded_on' => $domainMessage->getRecordedOn()->toString(),
            'type'        => $domainMessage->getType(),
        ];

        $marshal = new Marshaler();
        $data = $marshal->marshalJson(json_encode($data));

        $this->client->putItem(
            [
                'TableName' => $this->table,
                'Item' => $data
            ]
        );
    }

    public function visitEvents(Criteria $criteria, EventVisitor $eventVisitor)
    {
        // TODO: Implement visitEvents() method.
    }
}