<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View File Extension
    |--------------------------------------------------------------------------
    |
    | The file extension used for Smarty templates. The package registers
    | this as the highest-priority extension on the view finder, so that
    | `view('welcome')` resolves to `welcome.tpl` before `welcome.blade.php`.
    |
    */

    'extension' => 'tpl',

    /*
    |--------------------------------------------------------------------------
    | Compile / Cache Directories
    |--------------------------------------------------------------------------
    |
    | Smarty needs writable directories for its compiled templates and
    | cached output. Defaults live under storage/framework/smarty.
    |
    */

    'compile_path' => storage_path('framework/smarty/compile'),
    'cache_path' => storage_path('framework/smarty/cache'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Whether Smarty should cache rendered output. Mirrors the
    | Smarty::CACHING_OFF / CACHING_LIFETIME_CURRENT constants.
    |
    */

    'caching' => false,
    'cache_lifetime' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Force Compile
    |--------------------------------------------------------------------------
    |
    | When true, Smarty recompiles templates on every request. Useful in
    | development; disable in production for performance.
    |
    */

    'force_compile' => false,

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    */

    'debugging' => false,

    /*
    |--------------------------------------------------------------------------
    | Plugins Directories
    |--------------------------------------------------------------------------
    |
    | Additional directories to scan for custom Smarty plugins.
    |
    */

    'plugins_paths' => [],

];
