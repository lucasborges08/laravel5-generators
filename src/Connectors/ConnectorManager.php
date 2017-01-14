<?php namespace Bronco\LaravelGenerators\Connectors;

use Bronco\LaravelGenerators\Connectors\MySQLConnector;

class ConnectorManager {
    public static function getValidDatabaseEngines()
    {
        return ['mysql'];
    }

    public static function getConnector($engine = null)
    {
        $defaultDatabaseConnection = config('database.default');
        if ($engine) {
            if (!in_array($engine, self::getValidDatabaseEngines())) {
                throw new InvalidArgumentException('Invalid database engine. Valid options: ' . implode(', ', self::getValidDatabaseEngines(), -1));
                exit;
            }
        } else {
            $engine = config("database.connections.$defaultDatabaseConnection.driver");
        }

        switch ($engine) {
            case 'mysql':
                return MySQLConnector::getInstance();
        }

        throw new Exception("Could not find a database connector for your default connection: $defaultDatabaseConnection", -2);
    }
}
