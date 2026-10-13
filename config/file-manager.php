<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    |
    | Disk utilizado para armazenar os arquivos.
    |
    */

    'disk' => env('FILE_MANAGER_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Visibility
    |--------------------------------------------------------------------------
    |
    | Visibilidade padrão dos arquivos.
    | Valores comuns: public, private
    |
    */

    'visibility' => env(
        'FILE_MANAGER_VISIBILITY',
        'private'
    ),

    /*
    |--------------------------------------------------------------------------
    | Storage Path Prefix
    |--------------------------------------------------------------------------
    |
    | Diretório base onde os arquivos serão armazenados.
    |
    */

    'path_prefix' => env(
        'FILE_MANAGER_PATH_PREFIX',
        'files'
    ),

    /*
    |--------------------------------------------------------------------------
    | Date Based Directories
    |--------------------------------------------------------------------------
    |
    | Organiza arquivos em:
    |
    | files/2026/08
    |
    | Se false:
    |
    | files
    |
    */

    'use_date_directories' => env(
        'FILE_MANAGER_USE_DATE_DIRECTORIES',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | Hash Algorithm
    |--------------------------------------------------------------------------
    |
    | Algoritmo utilizado para geração do hash do arquivo.
    |
    */

    'hash_algorithm' => env(
        'FILE_MANAGER_HASH_ALGORITHM',
        'sha256'
    ),

    /*
    |--------------------------------------------------------------------------
    | Auto Delete Physical File
    |--------------------------------------------------------------------------
    |
    | Remove o arquivo físico quando o registro File
    | for removido permanentemente.
    |
    */

    'auto_delete_physical_file' => env(
        'FILE_MANAGER_AUTO_DELETE_PHYSICAL_FILE',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | Sharing
    |--------------------------------------------------------------------------
    |
    | Configurações padrão para compartilhamento.
    |
    */

    'sharing' => [

        'enabled' => env(
            'FILE_MANAGER_SHARING_ENABLED',
            true
        ),

        'default_expiration_hours' => env(
            'FILE_MANAGER_SHARE_EXPIRATION_HOURS',
            24
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Imagens
    |--------------------------------------------------------------------------
    |
    | Configurações padrão para imagens.
    |
    */

    'image' => [

        'optimization' => [
            'enabled' => env(
                'FILE_MANAGER_IMAGE_OPTIMIZATION_ENABLED',
                true
            ),

            'driver' => env(
                'FILE_MANAGER_IMAGE_DRIVER',
                'imagick'
            ),

            'format' => env(
                'FILE_MANAGER_IMAGE_FORMAT',
                'webp'
            ),

            'quality' => (int) env(
                'FILE_MANAGER_IMAGE_QUALITY',
                90
            ),

            'max_width' => (int) env(
                'FILE_MANAGER_IMAGE_MAX_WIDTH',
                1600
            ),

            'max_height' => (int) env(
                'FILE_MANAGER_IMAGE_MAX_HEIGHT',
                1600
            ),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | PDFs
    |--------------------------------------------------------------------------
    |
    | Configurações padrão para otimização de arquivos PDF.
    |
    | A otimização utiliza o Ghostscript instalado no sistema.
    |
    */

    'pdf' => [

        'optimization' => [
            'enabled' => env(
                'FILE_MANAGER_PDF_OPTIMIZATION_ENABLED',
                true
            ),

            'quality' => env(
                'FILE_MANAGER_PDF_QUALITY',
                'ebook'
            ),

            'ghostscript_binary' => env(
                'FILE_MANAGER_PDF_GHOSTSCRIPT_BINARY',
                'gs'
            ),
        ],

    ],

];
