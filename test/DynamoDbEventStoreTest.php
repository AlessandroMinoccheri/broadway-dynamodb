<?php

namespace Tests;

use Aws\DynamoDb\DynamoDbClient;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\DynamoDb\DynamoDbEventStore;
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

        $dynamodb->deleteTable(['TableName'=> 'dynamo_table']);

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
}