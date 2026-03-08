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

    /*
    |--------------------------------------------------------------------------
    | GitHub API (optional)
    |--------------------------------------------------------------------------
    |
    | If set, the panel update page uses the GitHub API to show the latest
    | commit on the branch and whether an update is available, instead of
    | relying on git fetch (which may fail on the server). Set to "owner/repo"
    | or leave null to auto-detect from git remote origin URL.
    |
    */

    'github_repo' => env('UPDATE_GITHUB_REPO'),

    'github_token' => env('UPDATE_GITHUB_TOKEN'),

];
