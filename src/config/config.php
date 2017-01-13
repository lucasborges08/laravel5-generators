<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Generator default values
    |--------------------------------------------------------------------------
    |
    */
    'defaults' => [
        'uses_soft_deletes' => false,
        'models_sub_namespace' => 'Models\\',
    ],

    /*
    |--------------------------------------------------------------------------
    | Where the stubs for the generators are stored...
    |--------------------------------------------------------------------------
    |
    */
    'model_template_path' => app_path('Generators/Stubs/Model.php'),
    'controller_template_path' => app_path('Generators/Stubs/Controller.php'),
    'model_parameter_path' => app_path('Generators/Stubs/Parameters/Model.php'),
    'controller_parameter_path' => app_path('Generators/Stubs/Parameters/Controller.php'),
    /*
    |--------------------------------------------------------------------------
    | Where the generated files will be saved...
    |--------------------------------------------------------------------------
    |
    */
    'model_target_path'   => app_path('Models/'),
    'controller_target_path'   => app_path('Http/Controllers/'),

];
