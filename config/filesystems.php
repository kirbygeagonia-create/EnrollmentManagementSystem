<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
        |--------------------------------------------------------------------------
        | The private working folder — deliberately NOT served
        |--------------------------------------------------------------------------
        |
        | This disk used to carry 'serve' => true, which made Laravel register
        | GET|HEAD /storage/{path} and PUT /storage/{path} outside every middleware group, gated
        | only by an HMAC of the path keyed by APP_KEY. Nothing in app/ or resources/ ever minted
        | one, so no desk used them, and the folder they reached is where the uploaded papers and
        | face photos live (§28.3 G-50). Taking the flag off removes the routes rather than
        | trusting their signature: a path that does not exist cannot be probed, and a blank
        | APP_KEY can no longer sign a write into this tree. Reads and writes go through the
        | routes that authorize the record each file belongs to.
        |
        */

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Uploaded applicant papers and student face photos
        |--------------------------------------------------------------------------
        |
        | Deliberately its own disk rather than the default one, and rooted where the
        | default already is so every path already stored stays valid. Uploads written
        | through config('filesystems.default') would move to a publicly served folder
        | on an operator who set FILESYSTEM_DISK=public and ran storage:link — and the
        | desk routes that read them back, the applicant's paper and the student's face
        | photo, are the only thing standing between a stranger and a named student's
        | documents, because a hashed filename is not a secret.
        | Naming the disk here means one line in .env can no longer expose them.
        | No 'serve' and no 'url': nothing may reach these bytes except through a route
        | that authorizes the record they belong to.
        |
        */

        'documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
