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
use Broadway\EventStore\DynamoDb\Objects\DeserializeEvent;
use Broadway\EventStore\DynamoDb\Expressions\CriteriaExpressionBuilder;
use Broadway\EventStore\EventStore;
use Broadway\EventStore\EventStreamNotFoundException;
use Broadway\EventStore\EventVisitor;
use Broadway\EventStore\Exception\DuplicatePlayheadException;
use Broadway\EventStore\Management\Criteria;
use Broadway\EventStore\Management\CriteriaNotSupportedException;
use Broadway\EventStore\Management\EventStoreManagement;
use Broadway\Serializer\Serializer;
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
    private $items;

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
    public function load($id): DomainEventStream
    {
        $marshaler = new Marshaler();

        $fields = [
            'uuid' => $id,
            'playhead' => 0
        ];

        $scanFilter = new CriteriaExpressionBuilder($fields);

        $eav = $marshaler->marshalJson($scanFilter->getExpressionAttributeValues());

        $itemsCollection = $this->client->scan(array(
            'TableName' => $this->table,
            'FilterExpression' => '#uuid = :uuid and playhead = :playhead',
            'ExpressionAttributeNames' => ['#uuid' => 'uuid'],
            "ExpressionAttributeValues" => $eav,
        ));

        $events = [];
        $items = $itemsCollection['Items'];

        if (null === $items) {
            throw new EventStreamNotFoundException(sprintf(
                'EventStream not found for aggregate with id %s for table %s',
                $id,
                $this->table
            ));
        }

        foreach ($items as $item) {
            $events[] = DeserializeEvent::deserialize($item, $this->payloadSerializer, $this->metadataSerializer);
        }

        if (empty($events)) {
            throw new EventStreamNotFoundException(sprintf(
                'EventStream not found for aggregate with id %s for table %s',
                $id,
                $this->table
            ));
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

        $scanFilter = new CriteriaExpressionBuilder($fields);

        $eav = $marshaler->marshalJson($scanFilter->getExpressionAttributeValues());

        $itemsCollection = $this->client->scan(array(
            'TableName' => $this->table,
            'FilterExpression' => '#uuid = :uuid and playhead = :playhead',
            'ExpressionAttributeNames' => ['#uuid' => 'uuid'],
            "ExpressionAttributeValues" => $eav,
        ));

        $events = [];
        $items = $itemsCollection['Items'];

        if (null === $items) {
            throw new EventStreamNotFoundException(sprintf(
                'EventStream not found for aggregate with id %s for table %s',
                $id,
                $this->table
            ));
        }

        foreach ($items as $item) {
            $events[] = DeserializeEvent::deserialize($item, $this->payloadSerializer, $this->metadataSerializer);
        }

        if (empty($events)) {
            throw new EventStreamNotFoundException(sprintf('EventStream not found for aggregate with id %s for table %s', $id, $this->table));
        }

        return new DomainEventStream($events);
    }

    /**
     * @param mixed $id
     * @param DomainEventStream $eventStream
     *
     * @throws DuplicatePlayheadException
     */
    public function append($id, DomainEventStream $eventStream): void
    {
        //TODO: use transactions
        foreach ($eventStream as $domainMessage) {
            $this->insertMessage($domainMessage);
        }
    }

    private function insertMessage(DomainMessage $domainMessage): void
    {
        $data = [
            'id' => Uuid::uuid4()->toString(),
            'uuid' => $domainMessage->getId(),
            'playhead' => $domainMessage->getPlayhead(),
            'metadata' => json_encode($this->metadataSerializer->serialize($domainMessage->getMetadata())),
            'payload' => json_encode($this->payloadSerializer->serialize($domainMessage->getPayload())),
            'recorded_on' => $domainMessage->getRecordedOn()->toString(),
            'type' => $domainMessage->getType(),
        ];

        $marshal = new Marshaler();

        $json = json_encode($data);

        if (!$json) {
            $json = '';
        }

        $data = $marshal->marshalJson($json);

        $this->client->transactWriteItems(
            [
                'TransactItems' => [
                    [
                        'Put' => [
                            'TableName' => $this->table,
                            'Item' => $data
                        ]
                    ]
                ]
            ]
        );
    }

    public function visitEvents(Criteria $criteria, EventVisitor $eventVisitor): void
    {
        if ($criteria->getAggregateRootTypes()) {
            throw new CriteriaNotSupportedException(
                'DynamoDb implementation cannot support criteria based on aggregate root types.'
            );
        }

        $fields = $this->convertCriteriaToArray($criteria);
        $scanFilter = new CriteriaExpressionBuilder($fields);

        $marshaler = new Marshaler();
        $eav = $marshaler->marshalJson($scanFilter->getExpressionAttributeValues());

        $itemsCollection = $this->client->scan(array(
            'TableName' => $this->table,
            'FilterExpression' => $scanFilter->getFilterExpression(),
            'ExpressionAttributeNames' => $scanFilter->getExpressionAttributeNames(),
            "ExpressionAttributeValues" => $eav,
        ));

        $this->items = $itemsCollection;

        if (null === $this->items) {
            throw new EventStreamNotFoundException(sprintf(
                'Items not found for table %s',
                $this->table
            ));
        }

        $events = $this->items['Items'];
        if (null === $events) {
            throw new EventStreamNotFoundException(sprintf(
                'Items not found for table %s',
                $this->table
            ));
        }

        foreach ($events as $event) {
            $eventVisitor->doWithEvent(
                DeserializeEvent::deserialize($event, $this->payloadSerializer, $this->metadataSerializer)
            );
        }
    }

    private function convertCriteriaToArray(Criteria $criteria): array
    {
        $findBy = [];
        if ($criteria->getAggregateRootIds()) {
            $findBy['uuid'] = ['in' => $criteria->getAggregateRootIds()];
        }
        if ($criteria->getEventTypes()) {
            $findBy['type'] = ['in' => $criteria->getEventTypes()];
        }

        return $findBy;
    }

    public function getItems()
    {
        return $this->items;
    }
}
