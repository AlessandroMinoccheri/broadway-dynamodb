# Broadway-dynamodb

This library is an event store implementation for (Broadway)[https://github.com/broadway/broadway] using Amazon DynamoDB.

## Installation:
-------------

[![Latest Stable Version](https://poser.pugx.org/AlessandroMinoccheri/broadway-dynamodb/v/stable.svg)](https://packagist.org/packages/alessandrominoccheri/broadway-dynamodb)
[![License](https://poser.pugx.org/AlessandroMinoccheri/broadway-dynamodb/license.svg)](https://packagist.org/packages/alessandrominoccheri/broadway-dynamodb)
[![Build Status](https://api.travis-ci.org/AlessandroMinoccheri/broadway-dynamodb.png)](https://travis-ci.org/AlessandroMinoccheri/broadway-dynamodb)
[![Total Downloads](https://poser.pugx.org/AlessandroMinoccheri/broadway-dynamodb/d/total.png)](https://packagist.org/packages/alessandrominoccheri/broadway-dynamodb)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/AlessandroMinoccheri/broadway-dynamodb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/AlessandroMinoccheri/broadway-dynamodb/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/AlessandroMinoccheri/broadway-dynamodb/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/AlessandroMinoccheri/broadway-dynamodb/?branch=master)

Install package via composer

```
$ composer require alessandrominoccheri/broadway-dynamodb
```

## Symfony

To install this library into a Symfony 5 application you can check this repository:

[Symfony demo](https://github.com/AlessandroMinoccheri/broadway-dynamodb-demo)

## Laravel

To use this library into a Laravel project you need to install first broadway for laravel via composer and after broadway-dynamodb like this:

```
composer require nwidart/laravel-broadway
composer require alessandrominoccheri/broadway-dynamodb
```

Inside ```config/broadway.php``` you can configure dynamo event store like this:

```
'event-store' => [
    'table' => 'event_store',
    'driver' => 'dynamoDb',
    'connection' => 'mysql_events',
    'endpoint' => env("AWS_END_POINT"),
    'access_key_id' => env("AWS_ACCESS_KEY_ID_DYNAMO"),
    'secret_access_key' => env("AWS_SECRET_ACCESS_KEY_DYNAMO"),
    'region' => env("AWS_DEFAULT_REGION")
],
```

You can dispatch your command in this way:

```
$command = new YourCommand($params);

$this->commandBus->dispatch($command);
```
 
To register your service you can create a provider file like this for example:

```
class BroadwayServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->bindEventSourcedRepositories();
        $this->bindReadModelRepositories();
        $this->registerCommandSubscribers();
        $this->registerEventSubscribers();
        $this->registerConsoleCommands();
    }
    public function boot()
    {
    }
    /**
     * Bind repositories
     */
    private function bindEventSourcedRepositories()
    {
        $this->app->bind(YourRepository::class, function ($app) {
            $eventStore = $app[\Broadway\EventStore\EventStore::class];
            $eventBus = $app[\Broadway\EventHandling\EventBus::class];
            return new YourRepository($eventStore, $eventBus);
        });
    }
    /**
     * Bind the read model repositories in the IoC container
     */
    private function bindReadModelRepositories()
    {
        $this->app->bind(YourReadModelRepository::class, function ($app) {
            $connection = $app[DynamoDbClient::class];
            return new YourReadModelRepository($connection);
        });
    }
    /**
     * Register the command handlers on the command bus
     */
    private function registerCommandSubscribers()
    {
        $yourCommandHandler = new YourCommandHandler($this->app[YourRepository::class]);
        $this->app['laravelbroadway.command.registry']->subscribe([
            $yourCommandHandler
        ]);
    }
    /**
     * Register the event listeners on the event bus
     */
    private function registerEventSubscribers()
    {
        $yourProjector = new YourProjector(
            $this->app[YourRepository::class]
        );

        $this->app['laravelbroadway.event.registry']->subscribe([
            $yourProjector
        ]);
    }
```

## Debug

In this library is installed PHPStan, so if you want to check the code you can launch inside your cli:

```
vendor/bin/phpstan analyse 
```

## Tests

It's really important that all tests are green. To run tests you need to have docker up so you need to:

```
docker-compose up -d
./vendor/bin/phpunit
```

In this library is installed Psalm, so if you want to check the code you can launch inside your cli:

```
vendor/bin/psalm
```


