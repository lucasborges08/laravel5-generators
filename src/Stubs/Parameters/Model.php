<?php
return [
    'sequence_name' => 'sq_{{sequence_name_pattern}}_id',
    'sequence_property' => 'public $sequence = \'{{database_name}}.{{sequence_name}}\';',

    'use_soft_deletes_definition' => "use Illuminate\Database\Eloquent\SoftDeletes;",
    'use_soft_deletes_trait' => "use SoftDeletes;",

    'created_at_const' => "const CREATED_AT = '{{created_at_column}}';",
    'updated_at_const' => "const UPDATED_AT = '{{updated_at_column}}';",
    'deleted_at_const' => "const DELETED_AT = '{{deleted_at_column}}';",


    'search_parameters' => [
        'whereEqual' => '
            if ($this->{{column_name}}) {
                $query->where(\'{{column_name}}\', $this->{{column_name}});
            }
            ',
        'whereInsensitiveLike' => '
            if ($this->{{column_name}}) {
                $query->whereRaw("lower({{column_name}}) LIKE \'%" . strtolower($this->{{column_name}}) . "%\'");
            }
            ',
        'whereRelationship' => '
            if ($this->{{column_name}}\' && $this->{{column_name}} != -1) {
                $query->where(\'{{column_name}}\', $this->{{column_name}});
            }
            ',
    ],

    'patterns' => [
        'created_at_column' => "/created_at/i",
        'updated_at_column' => "/updated_at/i",
        'deleted_at_column' => "/deleted_at/i",
        'sequence_name' => '/(\w{1,3})/',

        'dataTypes' => [
            'character' => '/char/i',
            'numeric' => '/int|numeric|decimal|float|double/i',
            'datetime' => '/date|time/i'
        ],

    ],


    'searchParametersMapping' => [
        'character' => 'whereInsensitiveLike',
        'numeric' => 'whereEqual',
        'datetime' => 'whereEqual'
    ]
];
