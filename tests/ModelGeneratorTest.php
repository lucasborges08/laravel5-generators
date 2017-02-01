<?php namespace Bronco\LaravelGenerators\Tests;

use PHPUnit_Framework_TestCase;

use Bronco\LaravelGenerators\Generators\ModelGenerator;
use Bronco\LaravelGenerators\Connectors\Connector;
use Bronco\LaravelGenerators\Connectors\ConnectorManager;

class ModelGeneratorTest extends PHPUnit_Framework_TestCase
{
    protected $generator;

    public function setUp()
    {
        $connector = ConnectorManager::getConnector(env('DB_CONNECTION'));
        $connector->setHost(env('DB_HOST'));
        $connector->setUsername(env('DB_USERNAME'));
        $connector->setPassword(env('DB_PASSWORD'));
        $connector->setDatabase(env('DB_DATABASE'));
        $connector->setConnectionName(env('DB_DATABASE'));

        $this->generator = new ModelGenerator($connector, 'Foo/Bar/Baz', 'test.TES_TESTE');
    }

    public function testNamespace()
    {
        $this->assertEquals('Foo\Bar', $this->getNamespace());
    }
}
