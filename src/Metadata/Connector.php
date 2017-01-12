<?php namespace Bronco\LaravelGenerators\Metadata;

interface Connector {
    public function getColumns($databaseName, $tableTable);
    public function getPrimaryKeys($databaseName, $tableTable);
    public function getForeignKeys($databaseName, $tableTable);
}
