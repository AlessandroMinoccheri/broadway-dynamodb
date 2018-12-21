<?php

namespace Tests;

use Aws\DynamoDb\DynamoDbClient;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventStore\DynamoDb\DynamoDbEventStore;
use Broadway\EventStore\DynamoDb\Objects\DeserializeEvent;
use Broadway\EventStore\EventVisitor;
use Broadway\EventStore\Management\Criteria;
use Broadway\Serializer\Serializable;
use Broadway\Serializer\SerializationException;
use Broadway\Serializer\Serializer;
use Broadway\Serializer\SimpleInterfaceSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-04
 * Time: 15:26
 */

class DynamoDbEventStoreTest extends TestCase
{
    private $dynamoDbEventStore;

    public function setUp()
    {
        $dynamodb = new DynamoDbClient([
            'region'   => 'us-west-2',
            'version'  => 'latest',
            'endpoint' => 'http://localhost:8000',
            'credentials' => [
                'key' => 'not-a-real-key',
                'secret' => 'not-a-real-secret',
            ],
        ]);

        $tables = $dynamodb->listTables([]);

        if (isset($tables['TableNames'])) {
            foreach ($tables['TableNames'] as $dynamoDbTable) {
                $dynamodb->deleteTable([
                    'TableName' => $dynamoDbTable,
                ]);
            }
        }

        $dynamodb->createTable([
            'TableName' => 'dynamo_table',
            'AttributeDefinitions' => [
                [
                    'AttributeName' => 'id',
                    'AttributeType' => 'S'
                ],
                [
                    'AttributeName' => 'playhead',
                    'AttributeType' => 'N'
                ]
            ],
            'KeySchema' => [
                [
                    'AttributeName' => 'id',
                    'KeyType' => 'HASH'
                ],
                [
                    'AttributeName' => 'playhead',
                    'KeyType' => 'RANGE'
                ]
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits'    => 5,
                'WriteCapacityUnits' => 6
            ]
        ]);

        $this->dynamoDbEventStore = new DynamoDbEventStore(
            $dynamodb,
            new SimpleInterfaceSerializer(),
            new SimpleInterfaceSerializer(),
            'dynamo_table'
        );
    }

    public function testInsertMessageAndLoadIt()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();
        $playhead = 0;
        $metadata = new \Broadway\Domain\Metadata(['id' => $id, 'foo' => 'bar']);
        $payload = new SerializableClass();
        $recordedOn = \Broadway\Domain\DateTime::now();

        $domainMessage = new DomainMessage(
            $id,
            $playhead,
            $metadata,
            $payload,
            $recordedOn
        );

        $eventStream = new DomainEventStream([$domainMessage]);

        $this->dynamoDbEventStore->append($id, $eventStream);

        $events = $this->dynamoDbEventStore->load($id);

        $this->assertCount(1, $events);

        foreach ($events as $event) {
            $this->assertEquals($id, $event->getId());
            $this->assertEquals($playhead, $event->getPlayhead());
            $this->assertEquals($metadata, $event->getMetadata());
            $this->assertEquals($payload, $event->getPayload());
            $this->assertEquals($recordedOn, $event->getRecordedOn());
        }

    }

    public function testInsertMessageAndLoadFromPlayhead()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();
        $playhead = random_int(1, 9999);
        $metadata = new \Broadway\Domain\Metadata(['id' => $id, 'foo' => 'bar']);
        $payload = new SerializableClass();
        $recordedOn = \Broadway\Domain\DateTime::now();

        $domainMessage = new DomainMessage(
            $id,
            $playhead,
            $metadata,
            $payload,
            $recordedOn
        );

        $eventStream = new DomainEventStream([$domainMessage]);

        $this->dynamoDbEventStore->append($id, $eventStream);

        $events = $this->dynamoDbEventStore->loadFromPlayhead($id, $playhead);

        $this->assertCount(1, $events);

        foreach ($events as $event) {
            $this->assertEquals($id, $event->getId());
            $this->assertEquals($playhead, $event->getPlayhead());
            $this->assertEquals($metadata, $event->getMetadata());
            $this->assertEquals($payload, $event->getPayload());
            $this->assertEquals($recordedOn, $event->getRecordedOn());
        }
    }

    private function appendEvent($id)
    {
        $playhead = random_int(1, 9999);
        $metadata = new \Broadway\Domain\Metadata(['id' => $id, 'foo' => 'bar']);
        $payload = new SerializableClass();
        $recordedOn = \Broadway\Domain\DateTime::now();

        $domainMessage = new DomainMessage(
            $id,
            $playhead,
            $metadata,
            $payload,
            $recordedOn
        );

        return new DomainEventStream([$domainMessage]);
    }

    public function testInsertMessageAndVisitEvents()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();
        $id2 =  \Ramsey\Uuid\Uuid::uuid4()->toString();

        $eventStream = $this->appendEvent($id);
        $this->dynamoDbEventStore->append($id, $eventStream);

        $eventStream = $this->appendEvent($id2);
        $this->dynamoDbEventStore->append($id2, $eventStream);

        $criteria = Criteria::create()->withAggregateRootIds([
            $id,
            $id2,
        ]);

        $eventVisitor = new RecordingEventVisitor();

        $events = $this->dynamoDbEventStore->visitEvents($criteria, $eventVisitor);

        $this->assertCount(2, $events['Items']);

        foreach ($events['Items'] as $event) {
            $eventDeserialized = DeserializeEvent::deserialize($event, new SerializerClass(), new SerializerClass());
            $this->assertTrue($eventDeserialized->getId() === $id || $eventDeserialized->getId() === $id2);
        }
    }
}

class SerializableClass implements Serializable
{

    /**
     * @return mixed The object instance
     */
    public static function deserialize(array $data)
    {
        return new self;
    }

    /**
     * @return array
     */
    public function serialize()
    {
        return array();
    }
}

class SerializerClass implements Serializer
{


    /**
     * @throws SerializationException
     *
     * @return array
     */
    public function serialize($object)
    {
        return array();
    }

    /**
     * @param array $serializedObject
     *
     * @throws SerializationException
     *
     * @return mixed
     */
    public function deserialize(array $serializedObject)
    {
        return new Metadata();
    }
}

class RecordingEventVisitor implements EventVisitor
{
    /**
     * @var DomainMessage
     */
    private $visitedEvents;

    public function doWithEvent(DomainMessage $domainMessage)
    {
        $this->visitedEvents[] = $domainMessage;
    }

    public function getVisitedEvents()
    {
        return $this->visitedEvents;
    }

    public function clearVisitedEvents()
    {
        $this->visitedEvents = [];
    }
}