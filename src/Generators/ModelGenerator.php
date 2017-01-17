<?php
namespace Bronco\LaravelGenerators\Generators;

use Illuminate\Filesystem\Filesystem;
use Bronco\LaravelGenerators\Connectors\Connector;

class ModelGenerator extends Generator
{
    /**
     * Database connector used to generate the object
     *
     * @var \Bronco\LaravelGenerators\Connectors\Connector
     */
    protected $connector;

    /**
     * Class name
     *
     * @var string
     */
    protected $className;

    /**
     * Sequence name
     *
     * @var string
     */
    protected $sequenceName;

    /**
     * Table name
     *
     * @var string
     */
    protected $tableName;

    /**
     * Database name
     *
     * @var string
     */
    protected $databaseName;

    /**
     * Uses sequence
     *
     * @var bool
     */
    protected $usesSequence;

    /**
     * Uses soft deletes
     *
     * @var bool
     */
    protected $usesSoftDeletes;

    /**
     * Qualified table name, with database name preceded
     *
     * @var string
     **/
    protected $qualifiedTableName;

    /**
     * Qualified class name, with namespaces
     *
     * @var string
     */
    protected $qualifiedName;

    /**
     * Class's file path
     *
     * @var string
     */
    protected $targetFilePath;

    public function __construct(Filesystem $filesystem, Connector $connector, $qualifiedName, $table)
    {
        parent::__construct($filesystem);


        $qualifiedNameParts = explode("/", $qualifiedName);

        $defaultConnection = config('database.default');

        $this->parameters = $this->loadParameters(config('generators.model_parameter_path'));
        $this->connector = $connector;
        $this->subNamespace = implode('\\', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1));
        $this->className = array_slice($qualifiedNameParts, -1, 1)[0];
        $this->setTargetFilePath(config('generators.model_target_path'), $qualifiedName);
        $this->qualifiedName = "Models\\{$this->subNamespace}\\{$this->className}";
        $this->usesSoftDeletes = config('generators.defaults.uses_soft_deletes');
        $this->usesSequence = is_a($this->connector, "Bronco\LaravelGeneratorsConnectors\OracleConnector");

        if ($table) {
            $tableParts = explode('.', $table);

            if (count($tableParts) == 2) {
                $this->databaseName = $tableParts[0];
                $this->tableName = $tableParts[1];
            } else {
                $this->databaseName = config("database.connections.$defaultConnection.database");
                $this->tableName = $tableParts[0];
            }
        } else {
            $this->tableName = snake_case($this->className).'s';
            $this->databaseName = config("database.connections.$defaultConnection.database");
        }

        $this->qualifiedTableName = "{$this->databaseName}.{$this->tableName}";

        try {
            $this->primaryKey = strtolower($this->connector->getPrimaryKeys($this->databaseName, $this->tableName)[0]->column_name);
        } catch (\ErrorException $e) {
            throw new \RuntimeException("Could not find primary keys in '{$this->qualifiedTableName}'");
        }

        try {
            $this->allColumns = $this->connector->getColumns($this->databaseName, $this->tableName);
            $nonPrimaryKeyFilter = function ($column) {
                return (strtolower($column->column_name) != strtolower($this->primaryKey));
            };
            $this->nonPrimaryKeyColumns = array_filter(
                $this->connector->getColumns($this->databaseName, $this->tableName),
                $nonPrimaryKeyFilter
            );
        } catch (\ErrorException $e) {
            throw new \RuntimeException("Could not find columns in '{$this->qualifiedTableName}'");
        }
    }

    public function make()
    {
        if (!$this->filesystem->exists(dirname($this->getTargetFilePath()))) {
            $this->filesystem->makeDirectory(dirname($this->getTargetFilePath()), 0755, true);
        }

        $content = $this->compileTags();
        $this->filesystem->put($this->getTargetFilePath(), $content);
    }

    public function compileTags()
    {
        $content = $this->filesystem->get(config('generators.model_template_path'));

        $this->replaceTag('app_namespace', app()->getNamespace(), $content)
             ->replaceTag('sub_namespace', $this->subNamespace, $content)
             ->replaceTag('class_name', $this->className, $content)
             ->replaceTag('database_name', $this->databaseName, $content)
             ->replaceTag('table_name', $this->tableName, $content)
             ->replaceTag('primary_key', $this->primaryKey, $content)
             ->compileSequenceName($content)
             ->compileDateColumns($content)
             ->compileSeachParameters($content)
             ->compileSoftDeletesUsage($content)
             ->compileUpdatedAtColumn($content)
             ->compileCreatedAtColumn($content);
        return $content;
    }

    public function compileDateColumns(&$content)
    {
        $dateColumns = $this->connector->getColumns($this->databaseName, $this->tableName, 'date');
        $columnsFormatted = '';
        foreach ($dateColumns as $column) {
            $columnsFormatted .= "'" . strtolower($column->column_name) ."', ";
        }

        $this->replaceTag('date_columns', rtrim($columnsFormatted, ', '), $content);
        return $this;
    }

    public function compileSoftDeletesUsage(&$content)
    {
        if (!$this->getUsesSoftDeletes()) {
            $this->replaceTag('use_soft_deletes_definition', '', $content)
                 ->replaceTag('use_soft_deletes_trait', '', $content);
        }

        $this->replaceTag('use_soft_deletes_definition',
            $this->parameters['use_soft_deletes_definition'],
            $content)
        ->replaceTag('use_soft_deletes_trait',
            $this->parameters['use_soft_deletes_trait'],
            $content
        );

        $deletedAtFilter = function ($column) {
            if (preg_match('/date|timestamp/i', $column->data_type)
             && preg_match($this->parameters['patterns']['deleted_at_column'], $column->column_name)) {
                return true;
            } else {
                return false;
            }
        };

        // Filter columns that matches $deletedAtFilter
        $deletedAtColumns = array_filter($this->allColumns, $deletedAtFilter);
        if ($deletedAtColumns) {
            // Get the array's first position
            $deletedAtColumn = reset($deletedAtColumns);

            $deletedAtConst = $this->parameters['deleted_at_const'];
            $this->replaceTag('deleted_at_column', strtolower($deletedAtColumn->column_name), $deletedAtConst)
                 ->replaceTag('deleted_at_const', $deletedAtConst, $content);
        } else {
            $this->replaceTag('deleted_at_const', '', $content);
        }

        return $this;
    }

    public function compileCreatedAtColumn(&$content)
    {
        $createdAtFilter = function ($column) {
            if (preg_match('/date|timestamp/i', $column->data_type) && preg_match($this->parameters['patterns']['created_at_column'], $column->column_name)) {
                return true;
            } else {
                return false;
            }
        };

        // Filter columns that matches $createdAtFilter
        $createdAtColumns = array_filter($this->allColumns, $createdAtFilter);
        if ($createdAtColumns) {
            // Get the array's first position
            $createdAtColumn = reset($createdAtColumns);

            $createdAtConst = $this->parameters['created_at_const'];
            $this->replaceTag('created_at_column', strtolower($createdAtColumn->column_name), $createdAtConst)
                 ->replaceTag('created_at_const', $createdAtConst, $content);
        } else {
            $this->replaceTag('created_at_const', '', $content);
        }

        return $this;
    }

    public function compileUpdatedAtColumn(&$content)
    {
        $updatedAtFilter = function ($column) {
            if (preg_match('/date|timestamp/i', $column->data_type) && preg_match($this->parameters['patterns']['updated_at_column'], $column->column_name)) {
                return true;
            } else {
                return false;
            }
        };

        // Filter columns that matches $updatedAtFilter
        $updatedAtColumns = array_filter($this->allColumns, $updatedAtFilter);
        if ($updatedAtColumns) {
            // Get the array's first position
            $updatedAtColumn = reset($updatedAtColumns);


            $updatedAtConst = $this->parameters['updated_at_const'];
            $this->replaceTag('updated_at_column', strtolower($updatedAtColumn->column_name), $updatedAtConst)
                 ->replaceTag('updated_at_const', $updatedAtConst, $content);
        } else {
            $this->replaceTag('updated_at_const', '', $content);
        }

        return $this;
    }

    private function compileSequenceName(&$content)
    {
        if ($this->usesSequence) {
            $sequenceNamePattern = $this->parameters['patterns']['sequence_name'];
            $sequenceName = $this->parameters['patterns']['sequence_name'];
            $sequenceProperty = $this->parameters['sequence_property'];

            $matches = preg_match($sequenceNamePattern, $this->tableName, $sequencePartName);
            $this->replaceTag('sequence_name_pattern', $sequencePartName[0], $sequenceName)
                 ->replaceTag('sequence_name', $sequenceName, $sequenceProperty)
                 ->replaceTag('database_name', $this->databaseName, $sequenceProperty)
                 ->replaceTag('sequence_property', $sequenceProperty, $content);
        } else {
            $this->replaceTag('sequence_property', '', $content);
        }

        return $this;
    }

    private function compileSeachParameters(&$content)
    {
        $parametersFormatted = '';
        $parameterFormatCollection = $this->parameters['search_parameters'];
        foreach ($this->allColumns as $column) {
            foreach ($this->parameters['patterns']['dataTypes'] as $key => $pattern) {
                if (preg_match($pattern, $column->data_type)) {
                    $parameterFormatName = $this->parameters['searchParametersMapping'][$key];
                    $parameter = $parameterFormatCollection[$parameterFormatName];
                    continue;
                }
            }

            $this->replaceTag('column_name', strtolower($column->column_name), $parameter);
            $parametersFormatted .= $parameter;
        }

        $this->replaceTag('search_parameters', $parametersFormatted, $content);
        return $this;
    }

    public function getTargetFilePath()
    {
        return $this->targetFilePath;
    }

    public function setTargetFilePath($path, $qualifiedName)
    {
        $qualifiedNameParts = explode("/", $qualifiedName);

        if (count($qualifiedNameParts) < 2) {
            throw new \InvalidArgumentException('You must inform the class\'s qualified name');
        }

        if (!$this->getClassName()) {
            throw new \InvalidArgumentException("Invalid class name: {$this->getClassName()}");
        }

        if ($this->filesystem->isDirectory($path)) {
            $this->targetFilePath = $path . implode('/', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1)) . "/{$this->getClassName()}.php";
        } else {
            $this->targetFilePath = $path;
        }
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getSequenceName()
    {
        return $this->sequenceName;
    }

    public function getUsesSequence()
    {
        return $this->usesSequence;
    }

    public function setUsesSequence($value)
    {
        $this->usesSequence = $value;
    }

    public function getUsesSoftDeletes()
    {
        return $this->usesSoftDeletes;
    }

    public function setUsesSoftDeletes($value)
    {
        $this->usesSoftDeletes = $value;
    }
}
