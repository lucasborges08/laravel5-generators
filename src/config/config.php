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
    'model_template_path' => base_path('vendor/gabrieloliverio/laravel5-generators/src/Stubs/Model.php'),
    'controller_template_path' => base_path('vendor/gabrieloliverio/laravel5-generators/src/Stubs/Controller.php'),
    'model_parameter_path' => base_path('vendor/gabrieloliverio/laravel5-generators/src/Stubs/Parameters/Model.php'),
    'controller_parameter_path' => base_path('vendor/gabrieloliverio/laravel5-generators/src/Stubs/Parameters/Controller.php'),

    /*
    |--------------------------------------------------------------------------
    | Where the generated files will be saved...
    |--------------------------------------------------------------------------
    |
    */
    'model_target_path'   => app_path('Models/'),
    'controller_target_path'   => app_path('Http/Controllers/'),

];
