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
     * Model namespace
     *
     * @var string
     */
    protected $modelNamespace;

    /**
     * Main model class instance
     *
     * @var \Illuminate\Database\Eloquent\Model
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

    protected $parameters;
    protected $modelParameters;

    /**
     * Filesystem instance, used to get the stub content
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $filesystem;


    public function __construct(Connector $connector, $qualifiedName, $mainModelName)
    {
        $this->connector = $connector;
        $this->filesystem = new Filesystem();

        $qualifiedNameParts = explode('/', $qualifiedName);

        $this->parameters = $this->loadParameters(config('generators.controller_parameter_path'));
        $this->modelParameters = $this->loadParameters(config('generators.model_parameter_path'));

        $this->setNamespace($qualifiedNameParts);
        $this->setClassName($qualifiedNameParts);
        $this->setTargetFilePath(config('generators.controller_target_path'), $qualifiedName);
        $this->setQualifiedName(config('generators.controller_sub_namespace') . "{$this->getNamespace()}\\{$this->getClassName()}");
        $this->setMainModelName($mainModelName);

        $this->setAppNamespace(app()->getNamespace());
        $this->setModelNamespace($this->getAppNamespace() . config('generators.defaults.models_sub_namespace'));
        $this->setMainModelQualifiedName($mainModelName);

        $this->setMainModelInstanceName($this->getMainModelName());
        $this->setMainModelInstance($this->getMainModelQualifiedName());

        $this->mainModelPrimaryKeyName = $this->mainModelInstance->primaryKey;

        $this->setTableName($this->mainModelInstance->table);
        $this->setDatabaseName($this->mainModelInstance->table);
        $this->setQualifiedTableName($this->getDatabaseName(), $this->getTableName());

        $this->allColumns = $this->connector->getColumns($this->databaseName, $this->tableName);

        if (!$this->allColumns) {
            throw new \InvalidArgumentException("Could not find columns in '{$this->getQualifiedTableName()}'");
        }

        $nonPrimaryKeyFilter = function($column) {
            return (strtolower($column->column_name) != strtolower($this->mainModelInstance->primaryKey));
        };
        $this->nonPrimaryKeyColumns = array_filter(
            $this->connector->getColumns($this->databaseName, $this->tableName),
            $nonPrimaryKeyFilter
        );
    }

    public function make()
    {
        $this->write($this->compileTags());
    }

    public function compileTags()
    {
        $content = $this->filesystem->get(config('generators.controller_template_path'));

        $this->replaceTag('app_namespace', $this->getAppNamespace(), $content)
             ->replaceTag('namespace', $this->getNamespace(), $content)
             ->replaceTag('model_sub_namespace', $this->getModelNamespace(), $content)
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

    public function getMainModelName()
    {
        return $this->mainModelName;
    }

    public function setMainModelName($mainModelName)
    {
        $mainModelNameParts = explode('/', $mainModelName);
        $this->mainModelName = array_slice($mainModelNameParts, -1, 1)[0];

        return $this;
    }

    public function setTableName($table)
    {
        if (!$this->getMainModelInstance()->table) {
            throw new \RuntimeException("Could not find the 'table' property in '{$this->mainModelQualifiedName}'. Ensure that this property is public." );
        }

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
        if (!$this->getMainModelInstance()->table) {
            throw new \RuntimeException("Could not find the 'table' property in '{$this->mainModelQualifiedName}'. Ensure that this property is public." );
        }

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

    public function getAllColumns()
    {
        return $this->allColumns;
    }

    public function setAllColumns(array $allColumns)
    {
        $this->allColumns = $allColumns;

        return $this;
    }

    public function getNonPrimaryKeyColumns()
    {
        return $this->nonPrimaryKeyColumns;
    }

    public function setNonPrimaryKeyColumns(array $nonPrimaryKeyColumns)
    {
        $this->nonPrimaryKeyColumns = $nonPrimaryKeyColumns;

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

    public function setClassName($qualifiedNameParts)
    {
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

    public function setNamespace($qualifiedNameParts)
    {
        $this->namespace = implode('\\', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1));
    }

    public function getNamespace()
    {
        return $this->namespace;
    }


    public function getMainModelInstance()
    {
        return $this->mainModelInstance;
    }

    public function setMainModelInstance($mainModelQualifiedName)
    {
        $this->mainModelInstance = new $mainModelQualifiedName();

        return $this;
    }

    public function getMainModelInstanceName()
    {
        return $this->mainModelInstanceName;
    }

    public function setMainModelInstanceName($mainModelName)
    {
        $this->mainModelInstanceName = lcfirst(str_replace('Model', '', $this->mainModelName));

        return $this;
    }

    public function getMainModelQualifiedName()
    {
        return $this->mainModelQualifiedName;
    }

    public function setMainModelQualifiedName($mainModelName)
    {
        $mainModelNameParts = explode('/', $mainModelName);
        $subnamespace = implode('/', array_slice($mainModelNameParts, 0, count($mainModelNameParts) - 1));
        $this->mainModelQualifiedName = str_replace('/', '\\', "{$this->getModelNamespace()}{$mainModelName}");

        return $this;
    }

    public function getModelNamespace()
    {
        return $this->modelNamespace;
    }

    public function setModelNamespace($modelNamespace)
    {
        $this->modelNamespace = $modelNamespace;

        return $this;
    }

    /**
     * Sets the full path where the model will be stored, including namespaces,
     * file name and extension.
     *
     * @param string $path Base folder where models are stored
     * @param array $qualifiedName Qualified name, with namespaces and class
     * name like "Namespace/Subnamespace/Model" (with slashes, not backslashes)
     */
    public function setTargetFilePath($path, $qualifiedName)
    {
        $qualifiedNameParts = explode('/', $qualifiedName);

        if (count($qualifiedNameParts) < 2) {
            throw new \InvalidArgumentException('You must inform the class\'s qualified name');
        }

        if (!$this->getClassName()) {
            throw new \InvalidArgumentException("Invalid class name: {$this->getClassName()}");
        }

        $this->targetFilePath = $path . implode('/', array_slice($qualifiedNameParts, 0, count($qualifiedNameParts) - 1)) . "/{$this->getClassName()}.php";
    }


}
