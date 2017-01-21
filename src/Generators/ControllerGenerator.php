<?php
namespace Bronco\LaravelGenerators\Generators;

use Illuminate\Filesystem\Filesystem;

use Bronco\LaravelGenerators\Connectors\Connector;

class ControllerGenerator extends Generator {
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
     * Main model class name
     */
    protected $mainModelName;

    /**
     * Main model class instance
     *
     * @var \Illuminate\Database\Eloquent\Model;
     */
    protected $mainModelInstance;

    /**
     * Main model instance name
     *
     * @var string
     */
    protected $mainModelInstanceName;

    /**
     * Main model qualified name, with namespaces
     */
    protected $mainModelQualifiedName;

    /**
     * Qualified class name, with namespaces
     *
     * @var string
     */
    protected $qualifiedName;

    /**
     * Main model's primary key name
     *
     * @var string
     */
    protected $mainModelPrimaryKeyName;

    /**
     * Primary key column
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $primaryKey;

    /**
     * All collumns of a given table, except primary keys
     *
     * @var array
     */
    protected $nonPrimaryKeyColumns;

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
     * Qualified table name, with database name preceded
     *
     * @var string
     */
    protected $qualifiedTableName;

    public function __construct(Connector $connector, $qualifiedName, $mainModelName)
    {
        parent::__construct($filesystem);

        $qualifiedNameParts = explode('/', $qualifiedName);
        $mainModelNameParts = explode('/', $mainModelName);

        if (count($qualifiedNameParts) < 2)
            throw new \InvalidArgumentException('You must inform the class\'s qualified name');

        $defaultConnection = config('database.default');

        $this->parameters = $this->loadParameters(config('generators.controller_parameter_path'));
        $this->modelParameters = $this->loadParameters(config('generators.model_parameter_path'));
        $this->connector = $connector;
        $this->mainModelName = array_slice($mainModelNameParts, -1, 1)[0];
        $this->subNamespace = implode('\\', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1));
        $this->className = array_slice($qualifiedNameParts, -1, 1)[0];
        $this->filePath = config('generators.controller_target_path') . implode('/', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1)) . "/{$this->className}.php";
        $this->qualifiedName = "Http\\Controllers\\{$this->subNamespace}\\{$this->className}";

        $modelsSubNamespace = app()->getNamespace() . config('generators.defaults.models_sub_namespace');
        $this->mainModelQualifiedName = str_replace('/', '\\', "$modelsSubNamespace$mainModelName");
        $this->mainModelInstanceName = lcfirst(str_replace('Model', '', $this->mainModelName));
        $this->mainModelInstance = new $this->mainModelQualifiedName();
        $this->mainModelPrimaryKeyName = $this->mainModelInstance->primaryKey;
        $tableParts = explode('.', $this->mainModelInstance->table);

        if (!$this->mainModelInstance->table) {
            throw new \RuntimeException("Could not find the 'table' property in '{$this->mainModelQualifiedName}'. Ensure that this property is public." );
        }

        if (count($tableParts) == 2) {
            $this->databaseName = $tableParts[0];
            $this->tableName = $tableParts[1];
        } else {
            $this->databaseName = config("database.connections.$defaultConnection.database");
            $this->tableName = $tableParts[0];
        }

        $this->qualifiedTableName = "{$this->databaseName}.{$this->tableName}";

        try {
            $this->allColumns = $this->connector->getColumns($this->databaseName, $this->tableName);
            $nonPrimaryKeyFilter = function($column) {
                return (strtolower($column->column_name) != strtolower($this->mainModelInstance->primaryKey));
            };
            $this->nonPrimaryKeyColumns = array_filter(
                $this->connector->getColumns($this->databaseName, $this->tableName),
                $nonPrimaryKeyFilter
            );
        } catch (\ErrorException $e) {
            throw new \RuntimeException("Could not find columns in '{$this->qualifiedTableName}'" );
        }
    }

    public function make()
    {
        if (!$this->filesystem->exists(dirname($this->filePath)))
            $this->filesystem->makeDirectory(dirname($this->filePath), 0755, true);

        $content = $this->compileTags();
        $this->filesystem->put($this->filePath, $content);
    }

    public function compileTags()
    {
        $content = $this->filesystem->get(config('generators.controller_template_path'));

        $this->replaceTag('app_namespace', app()->getNamespace(), $content)
             ->replaceTag('sub_namespace', $this->subNamespace, $content)
             ->replaceTag('model_sub_namespace', config('generators.defaults.models_sub_namespace'), $content)
             ->replaceTag('class_name', $this->className, $content)
             ->replaceTag('model_name', $this->mainModelName, $content)
             ->replaceTag('model_qualified_name', $this->mainModelQualifiedName, $content)
             ->replaceTag('model_instance', $this->mainModelInstanceName, $content)
             ->compileModelInstanceAttribution($content);
        return $content;
    }

    public function compileModelInstanceAttribution(&$content)
    {
        $attributesFormatted = '';
        foreach ($this->nonPrimaryKeyColumns as $column) {
            if (preg_match($this->modelParameters['patterns']['created_at_column'], $column->column_name)
              || preg_match($this->modelParameters['patterns']['deleted_at_column'], $column->column_name)
              || preg_match($this->modelParameters['patterns']['updated_at_column'], $column->column_name)) {
                  continue;
            }

            $attribute = $this->parameters['model_instance_attribution'];
            $this->replaceTag('column_name', strtolower($column->column_name), $attribute);
            $this->replaceTag('model_instance', $this->mainModelInstanceName, $attribute);
            $attributesFormatted .= $attribute;
        }
        $primaryKeyAttribute = $this->parameters['model_instance_attribution'];
        $this->replaceTag('column_name', strtolower($this->mainModelInstance->primaryKey), $primaryKeyAttribute);
        $this->replaceTag('model_instance', $this->mainModelInstanceName, $primaryKeyAttribute);

        $this->replaceTag('model_instance_pk_attribution', $primaryKeyAttribute, $content);
        $this->replaceTag('model_instance_attribution', $attributesFormatted, $content);
        return $this;
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
}
