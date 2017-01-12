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
    | Where the templates for the generators are stored...
    |--------------------------------------------------------------------------
    |
    */
    'model_template_path' => app_path('Generators/Templates/Model.php'),
    'controller_template_path' => app_path('Generators/Templates/Controller.php'),
    'model_parameter_path' => app_path('Generators/Templates/Parameters/Model.php'),
    'controller_parameter_path' => app_path('Generators/Templates/Parameters/Controller.php'),
    /*
    |--------------------------------------------------------------------------
    | Where the generated files will be saved...
    |--------------------------------------------------------------------------
    |
    */
    'model_target_path'   => app_path('Models/'),
    'controller_target_path'   => app_path('Http/Controllers/'),

];
