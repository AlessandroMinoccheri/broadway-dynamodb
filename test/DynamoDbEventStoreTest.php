<?php

namespace Tests;

use Aws\DynamoDb\DynamoDbClient;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\DynamoDb\DynamoDbEventStore;
use Broadway\EventStore\DynamoDb\Objects\DeserializeEvent;
use Broadway\EventStore\EventVisitor;
use Broadway\EventStore\Management\Criteria;
use Broadway\Serializer\ReflectionSerializer;

/**
 * Created by PhpStorm.
 * User: alessandrominoccheri
 * Date: 2018-12-04
 * Time: 15:26
 */

class DynamoDbEventStoreTest extends \PHPUnit\Framework\TestCase
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
            new ReflectionSerializer(),
            new ReflectionSerializer(),
            'dynamo_table'
        );
    }

    public function testInsertMessageAndLoadIt()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();
        $playhead = 0;
        $metadata = new \Broadway\Domain\Metadata(['id' => $id, 'foo' => 'bar']);
        $payload = new class(){};
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
        $payload = new class(){};
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
        $payload = new class(){};
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

        $this->dynamoDbEventStore->visitEvents($criteria, $eventVisitor);
        $events = $this->dynamoDbEventStore->getItems();

        $this->assertCount(2, $events['Items']);

        foreach ($events['Items'] as $event) {
            $eventDeserialized = DeserializeEvent::deserialize($event, new ReflectionSerializer(), new ReflectionSerializer());
            $this->assertTrue($eventDeserialized->getId() === $id || $eventDeserialized->getId() === $id2);
        }
    }

    /**
     * @expectedException Broadway\EventStore\EventStreamNotFoundException
     */
    public function testEmptyEventsThrowExceptionOnLoad()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();

        $eventStream = new DomainEventStream([]);

        $this->dynamoDbEventStore->append($id, $eventStream);

        $this->dynamoDbEventStore->load($id);
    }

    /**
     * @expectedException Broadway\EventStore\EventStreamNotFoundException
     */
    public function testEmptyEventsThrowExceptionOnLoadFromPlayhead()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();
        $playhead = random_int(1, 9999);

        $eventStream = new DomainEventStream([]);

        $this->dynamoDbEventStore->append($id, $eventStream);

        $this->dynamoDbEventStore->loadFromPlayhead($id, $playhead);
    }

    /**
     * @expectedException Broadway\EventStore\Management\CriteriaNotSupportedException
     */
    public function testInsertMessageAndVisitEventsWithAggregateRootTypesThrowException()
    {
        $id =  \Ramsey\Uuid\Uuid::uuid4()->toString();
        $id2 =  \Ramsey\Uuid\Uuid::uuid4()->toString();

        $eventStream = $this->appendEvent($id);
        $this->dynamoDbEventStore->append($id, $eventStream);

        $eventStream = $this->appendEvent($id2);
        $this->dynamoDbEventStore->append($id2, $eventStream);

        $criteria = Criteria::create()->withAggregateRootTypes([
            'type1',
            'type2',
        ]);

        $eventVisitor = new RecordingEventVisitor();

        $this->dynamoDbEventStore->visitEvents($criteria, $eventVisitor);
    }
}


class RecordingEventVisitor implements EventVisitor
{
    /**
     * @var DomainMessage
     */
    private $visitedEvents;

    public function doWithEvent(DomainMessage $domainMessage) :void
    {
        $this->visitedEvents[] = $domainMessage;
    }

    public function getVisitedEvents()
    {
        return $this->visitedEvents;
    }

    public function clearVisitedEvents() :void
    {
        $this->visitedEvents = [];
    }
}