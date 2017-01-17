<?php namespace Bronco\LaravelGenerators\Connectors;

use DB;

class MySQLConnector implements Connector
{
    private static $instance;
    private $connection;

    public function __construct()
    {
        $this->connection = 'mysql';
    }

    public static function getInstance()
    {
        if (!self::$instance)
            self::$instance = new self;
        return self::$instance;
    }

    public function getColumns($databaseName, $tableName, $type = null)
    {
        $query = DB::table('information_schema.columns')
            ->select(
                'column_name',
                'data_type',
                'numeric_precision',
                'numeric_scale',
                'character_maximum_length',
                'is_nullable')
            ->whereRaw("lower(table_schema) = '" . strtolower($databaseName) . "'")
            ->whereRaw("lower(table_name) = '" . strtolower($tableName) . "'");

        if ($type)
            $query->whereRaw("data_type like '$type%'");

        return $query
            ->orderBy('column_name')
            ->get();
    }

    public function getPrimaryKeys($databaseName, $tableName)
    {
        return DB::table('information_schema.columns')
            ->select(
                'column_name',
                'data_type',
                'numeric_precision',
                'numeric_scale',
                'character_maximum_length',
                'is_nullable')
            ->whereRaw("lower(table_schema) = '" . strtolower($databaseName) . "'")
            ->whereRaw("lower(table_name) = '" . strtolower($tableName) . "'")
            ->where("COLUMN_KEY", "PRI")
            ->orderBy('column_name')
            ->get();
    }

    public function getForeignKeys($databaseName, $tableName)
    {
        return DB::table('information_schema.key_column_usage as usa')
            ->join('information_schema.columns as col', function ($join) {
                $join->on('col.table_name', '=', 'usa.table_name');
                $join->on('col.column_name', '=', 'usa.column_name');
            })
            ->select(
                'col.column_name',
                'col.data_type',
                'col.numeric_precision',
                'col.numeric_scale',
                'col.character_maximum_length',
                'col.is_nullable',
                'usa.referenced_table_schema',
                'usa.referenced_table_name')
            ->whereRaw("lower(col.table_schema) = '" . strtolower($databaseName) . "'")
            ->whereRaw("lower(col.table_name) = '" . strtolower($tableName) . "'")
            ->whereNotNull('usa.referenced_table_name')
            ->orderBy('col.column_name')
            ->get();
    }

    public function getHost()
    {
        return config("database.connections.{$this->getConnection()}.host");
    }

    public function setHost($host)
    {
        config(["database.connections.{$this->getConnection()}.host" => $host]);
    }

    public function getDatabase()
    {
        return config("database.connections.{$this->getConnection()}.database");
    }

    public function setDatabase($database)
    {
        config(["database.connections.{$this->getConnection()}.database" => $database]);
    }

    public function getUsername()
    {
        return config("database.connections.{$this->getConnection()}.username");
    }

    public function setUsername($username)
    {
        config(["database.connections.{$this->getConnection()}.username" => $username]);
    }

    public function getPassword()
    {
        return config("database.connections.{$this->getConnection()}.password");
    }

    public function setPassword($password)
    {
        config(["database.connections.{$this->getConnection()}.password" => $password]);
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

}
