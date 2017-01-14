<?php namespace Bronco\LaravelGenerators\Connectors;

interface Connector {
    public function getColumns($databaseName, $tableTable);
    public function getPrimaryKeys($databaseName, $tableTable);
    public function getForeignKeys($databaseName, $tableTable);
}
