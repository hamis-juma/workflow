<?php

return [
/*
    |--------------------------------------------------------------------------
    | Workflow Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'path' => env('WORKFLOW_PATH','workflow'), //add the WORKFLOW_PATH in .env for security purpose

    'user' => App\Models\User::class //Please change this to locate user model

];