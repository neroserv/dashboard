<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panel Update Path
    |--------------------------------------------------------------------------
    |
    | The filesystem path to the project root (where .git lives). Use base_path()
    | for default, or set e.g. /var/www/praxishosting in production.
    |
    */

    'path' => env('UPDATE_PATH', base_path()),

    /*
    |--------------------------------------------------------------------------
    | Git Branch
    |--------------------------------------------------------------------------
    |
    | The remote branch to compare against and pull from (e.g. main).
    |
    */

    'branch' => env('UPDATE_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Git Remote
    |--------------------------------------------------------------------------
    |
    | The Git remote name (e.g. origin).
    |
    */

    'remote' => env('UPDATE_REMOTE', 'origin'),

];
