<?php namespace {{app_namespace}}Models\{{namespace}};

{{use_soft_deletes_definition}}

use Jackhammer\Database\Eloquent\Model;

class {{class_name}} extends Model
{
    {{use_soft_deletes_trait}}
    {{sequence_property}}
    public $table = '{{database_name}}.{{table_name}}';
    public $primaryKey = '{{primary_key}}';
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $dates = [
        {{date_columns}}
    ];

    {{created_at_const}}
    {{updated_at_const}}
    {{deleted_at_const}}

    /**
     * Exibe registros que casam com os parâmetros fornecidos.
     *
     * @return array
     */
    public function search()
    {
        $result = self::where(function($query) {
            {{search_parameters}}
        });

        return $result->get();
    }
}
