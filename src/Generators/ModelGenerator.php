<?php namespace Bronco\LaravelGenerators\Generators;

use Illuminate\Filesystem\Filesystem;

use Bronco\LaravelGenerators\Connectors\Connector;

class ModelGenerator extends Generator
{
    use FileWriter;

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
     */
    protected $qualifiedTableName;

    /**
     * Qualified class name, with namespaces
     *
     * @var string
     */
    protected $qualifiedName;

    /**
     * Primary key name
     *
     * @var string
     */
    protected $primaryKeyName;

    /**
     * Primary key column
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $primaryKey;

    /**
     * Class's namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Application's namespace
     *
     * @var string
     */
    protected $appNamespace;

    /**
     * All collumns of a given table
     *
     * @var array
     */
    protected $allColumns;

    /**
     * All collumns of a given table, except primary keys
     *
     * @var array
     */
    protected $nonPrimaryKeyColumns;

    /**
     * Filesystem instance, used to get the stub content
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $filesystem;

    public function __construct(Connector $connector, $qualifiedName, $table)
    {
        $this->filesystem = new Filesystem();
        $this->parameters = $this->loadParameters(config('generators.model_parameter_path'));
        $this->connector = $connector;
        $this->setNamespace($qualifiedName);
        $this->setClassName($qualifiedName);
        $this->setTargetFilePath(config('generators.model_target_path'), $qualifiedName);

        $this->setQualifiedName(config('generators.models_sub_namespace') . "{$this->namespace}\\{$this->className}");
        $this->setUsesSoftDeletes(config('generators.defaults.uses_soft_deletes'));
        $this->setUsesSequence(is_a($this->connector, "Bronco\LaravelGeneratorsConnectors\OracleConnector"));
        $this->setTableName($table);
        $this->setDatabaseName($table);
        $this->setQualifiedTableName($this->getDatabaseName(), $this->getTableName());
        $this->setAppNamespace(app()->getNamespace());

        $this->setAllColumns($this->connector->getColumns($this->getDatabaseName(), $this->getTableName()));
        $this->setPrimaryKey($this->connector->getPrimaryKeys($this->getDatabaseName(), $this->getTableName()));
        $this->setPrimaryKeyName($this->getPrimaryKey());
        $this->setNonPrimaryKeyColumns($this->getAllColumns());
    }

    public function make()
    {
        $this->write($this->compileTags());
    }

    public function compileTags()
    {
        $content = $this->filesystem->get(config('generators.model_template_path'));

        $this->replaceTag('app_namespace', $this->getAppNamespace(), $content)
             ->replaceTag('namespace', $this->getNamespace(), $content)
             ->replaceTag('class_name', $this->getClassName(), $content)
             ->replaceTag('database_name', $this->getDatabaseName(), $content)
             ->replaceTag('table_name', $this->getTableName(), $content)
             ->replaceTag('primary_key', $this->getPrimaryKeyName(), $content)
             ->compileSequenceName($content)
             ->compileDateColumns($content)
             ->compileSeachParameters($content)
             ->compileSoftDeletesUsage($content)
             ->compileDeletedAtColumns($content)
             ->compileUpdatedAtColumn($content)
             ->compileCreatedAtColumn($content);
        return $content;
    }

    public function compileDateColumns(&$content)
    {
        $dateColumnsFilter = function($column) {
            if (preg_match('/date|timestamp/i', $column->data_type)) {
                return true;
            }

            return false;
        };

        $dateColumns = array_filter($this->getAllColumns(), $dateColumnsFilter);

        $columnsFormatted = '';
        foreach ($dateColumns as $column) {
            $columnsFormatted .= "'" . strtolower($column->column_name) ."', ";
        }

        $this->replaceTag('date_columns', rtrim($columnsFormatted, ', '), $content);
        return $this;
    }

    public function compileDeletedAtColumns(&$content)
    {
        $deletedAtFilter = function ($column) {
            if (preg_match('/date|timestamp/i', $column->data_type)
             && preg_match($this->parameters['patterns']['deleted_at_column'], $column->column_name)) {
                return true;
            }

            return false;
        };

        // Filter columns that matches $deletedAtFilter
        $deletedAtColumns = array_filter($this->getAllColumns(), $deletedAtFilter);
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

        return $this;
    }

    public function compileCreatedAtColumn(&$content)
    {
        $createdAtFilter = function ($column) {
            if (preg_match('/date|timestamp/i', $column->data_type) && preg_match($this->parameters['patterns']['created_at_column'], $column->column_name)) {
                return true;
            }

            return false;
        };

        // Filter columns that matches $createdAtFilter
        $createdAtColumns = array_filter($this->getAllColumns(), $createdAtFilter);
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
            }

            return false;
        };

        // Filter columns that matches $updatedAtFilter
        $updatedAtColumns = array_filter($this->getAllColumns(), $updatedAtFilter);
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
        $allColumns = $this->getAllColumns();
        foreach ($allColumns as $column) {
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

    /**
     * Sets the full path where the model will be stored, including namespaces,
     * file name and extension.
     *
     * @param string $path Base folder where models are stored
     * @param string $qualifiedName Qualified name, with namespaces and class
     * name like "Namespace/Subnamespace/Model" (with slashes, not backslashes).
     */
    public function setTargetFilePath($path, $qualifiedName)
    {
        $qualifiedNameParts = explode("/", $qualifiedName);

        if (count($qualifiedNameParts) < 2) {
            throw new \InvalidArgumentException('You must inform the class\'s qualified name');
        }

        if (!$this->getClassName()) {
            throw new \InvalidArgumentException("Invalid class name: {$this->getClassName()}");
        }

        $this->targetFilePath = $path . implode('/', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1)) . "/{$this->getClassName()}.php";
    }

    /**
     * Sets the class name, based on the qualified name
     *
     * @param string $qualifiedName Qualified name, with namespaces and class
     * name like "Namespace/Subnamespace/Model" (with slashes, not backslashes).
     */
    public function setClassName($qualifiedName)
    {
        $qualifiedNameParts = explode("/", $qualifiedName);
        $this->className = array_slice($qualifiedNameParts, -1, 1)[0];
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getConnector()
    {
        return $this->connector;
    }

    public function setConnector($connector)
    {
        $this->connector = $connector;
    }

    /**
     * Sets the namespace, based on the qualified name
     *
     * @param string $qualifiedName Qualified name, with namespaces and class
     * name like "Namespace/Subnamespace/Model" (with slashes, not backslashes).
     */
    public function setNamespace($qualifiedName)
    {
        $qualifiedNameParts = explode("/", $qualifiedName);

        $this->namespace = implode('\\', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1));
    }

    public function getNamespace()
    {
        return $this->namespace;
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

    public function setTableName($table)
    {
        if ($table) {
            $tableParts = explode('.', $table);

            if (count($tableParts) == 2) {
                $this->tableName = $tableParts[1];
            } else {
                $this->tableName = $tableParts[0];
            }
        } else {
            $this->tableName = snake_case($this->className).'s';
        }
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function setDatabaseName($table)
    {
        if ($table) {
            $tableParts = explode('.', $table);

            if (count($tableParts) == 2) {
                $this->databaseName = $tableParts[0];
            } else {
                $this->databaseName = $this->getConnector()->getDatabase();
            }
        } else {
            $this->databaseName = $this->getConnector()->getDatabase();
        }
    }

    public function getDatabaseName()
    {
        return $this->databaseName;
    }

    public function getPrimaryKeyName()
    {
        return $this->primaryKeyName;
    }

    public function setPrimaryKeyName($primaryKey)
    {
        $this->primaryKeyName = strtolower($this->primaryKey->column_name);

        return $this;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function setPrimaryKey($primaryKeys)
    {
        if (!isset($primaryKeys[0])) {
            throw new \InvalidArgumentException("Could not find primary keys in '{$this->getQualifiedTableName()}'");
        }

        $this->primaryKey = $primaryKeys[0];

        return $this;
    }

    /**
     * Get the value of All collumns of a given table
     *
     * @return array
     */
    public function getAllColumns()
    {
        return $this->allColumns;
    }

    /**
     * Set the value of All collumns of a given table
     *
     * @param array allColumns
     *
     * @return self
     */
    public function setAllColumns(array $allColumns)
    {
        if (empty($allColumns)) {
            throw new \InvalidArgumentException("Could not find columns in '{$this->getQualifiedTableName()}'");
        }

        $this->allColumns = $allColumns;

        return $this;
    }

    public function getNonPrimaryKeyColumns()
    {
        return $this->nonPrimaryKeyColumns;
    }

    public function setNonPrimaryKeyColumns(array $columns)
    {
        $nonPrimaryKeyFilter = function ($column) {
            return (strtolower($column->column_name) != strtolower($this->getPrimaryKeyName()));
        };
        $this->nonPrimaryKeyColumns = array_filter($columns, $nonPrimaryKeyFilter);

        return $this;
    }

    public function getQualifiedTableName()
    {
        return $this->qualifiedTableName;
    }

    public function setQualifiedTableName($databaseName, $tableName)
    {
        $this->qualifiedTableName = "{$databaseName}.{$tableName}";

        return $this;
    }

    public function getAppNamespace()
    {
        return $this->appNamespace;
    }

    public function setAppNamespace($appNamespace)
    {
        $this->appNamespace = $appNamespace;

        return $this;
    }

    public function getQualifiedName()
    {
        return $this->qualifiedName;
    }

    public function setQualifiedName($qualifiedName)
    {
        $this->qualifiedName = $qualifiedName;

        return $this;
    }

}
