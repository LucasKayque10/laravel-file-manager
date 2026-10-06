# Laravel File Manager

Gerenciamento de arquivos para aplicações Laravel, com suporte a upload, armazenamento, metadados, compartilhamento e otimização automática de imagens.

## Requisitos

* PHP 8.2+
* Laravel 11 ou 12
* Extensão `fileinfo`
* Para otimização de imagens:

  * `imagick` ou `gd`
  * suporte ao formato de saída configurado

## Instalação

Instale o pacote via Composer:

```bash
composer require lucas-barros/laravel-file-manager
```

O service provider é registrado automaticamente pelo Laravel.

### Publicar a configuração

```bash
php artisan vendor:publish --tag=file-manager-config
```

Isso publicará:

```text
config/file-manager.php
```

## Configuração

As principais opções estão disponíveis em `config/file-manager.php`.

Exemplo:

```php
return [

    'disk' => 'local',

    'visibility' => 'private',

    'path_prefix' => 'files',

    'use_date_directories' => true,

    'hash_algorithm' => 'sha256',

    'auto_delete_physical_file' => true,

    'image' => [

        'optimization' => [

            'enabled' => true,

            'driver' => 'imagick',

            'format' => 'webp',

            'quality' => 90,

            'max_width' => 1600,

            'max_height' => 1600,

        ],

    ],

];
```

## Otimização de imagens

A partir da versão `1.1`, imagens podem ser otimizadas automaticamente durante o upload.

Por padrão:

* imagens são convertidas para WebP;
* a qualidade é `90`;
* a largura máxima é `1600px`;
* a altura máxima é `1600px`;
* a proporção original é preservada;
* imagens menores não são ampliadas;
* o arquivo original não é armazenado; a versão otimizada é armazenada.

Por exemplo, uma imagem de `3000 × 2000` será armazenada como aproximadamente:

```text
1600 × 1067
```

Uma imagem de `800 × 600` permanecerá:

```text
800 × 600
```

### Variáveis de ambiente

As configurações podem ser sobrescritas através do `.env`:

```env
FILE_MANAGER_IMAGE_OPTIMIZATION_ENABLED=true
FILE_MANAGER_IMAGE_DRIVER=imagick
FILE_MANAGER_IMAGE_FORMAT=webp
FILE_MANAGER_IMAGE_QUALITY=90
FILE_MANAGER_IMAGE_MAX_WIDTH=1600
FILE_MANAGER_IMAGE_MAX_HEIGHT=1600
```

### Desabilitar otimização

Para manter o comportamento original dos uploads de imagens:

```env
FILE_MANAGER_IMAGE_OPTIMIZATION_ENABLED=false
```

Quando a otimização estiver desabilitada, as imagens seguirão o fluxo normal de upload.

### Driver

O driver padrão é `imagick`:

```env
FILE_MANAGER_IMAGE_DRIVER=imagick
```

Também é possível utilizar GD:

```env
FILE_MANAGER_IMAGE_DRIVER=gd
```

O driver escolhido precisa estar disponível na instalação do PHP.

## Upload

O upload pode ser realizado através da Facade `FileManager`:

```php
use LucasBarros\LaravelFileManager\Facades\FileManager;

$file = FileManager::upload($uploadedFile);
```

Também é possível informar o disk e a visibilidade:

```php
$file = FileManager::upload(
    $uploadedFile,
    'local',
    'private',
);
```

A assinatura do método `upload()` permanece compatível com as versões anteriores:

```php
public function upload(
    UploadedFile $fileTemp,
    ?string $disk = null,
    ?string $visibility = null,
): FileModel
```

## Arquivo armazenado

Por padrão, os arquivos são armazenados utilizando a estrutura:

```text
files/YYYY/MM/
```

O nome físico do arquivo é baseado em UUID.

Exemplo:

```text
files/2026/10/550e8400-e29b-41d4-a716-446655440000.webp
```

Para imagens otimizadas, os metadados registrados correspondem ao arquivo físico armazenado.

Por exemplo:

```text
original_name: foto.jpg
extension:     webp
mime_type:     image/webp
size:          tamanho do WebP
hash:          hash do WebP
```

O nome original enviado pelo usuário permanece disponível através de `original_name`.

## Arquivos que não são imagens

A otimização é aplicada somente a arquivos identificados como imagens.

Arquivos como:

* PDF
* documentos
* planilhas
* arquivos de texto
* outros formatos não-imagem

continuam utilizando o fluxo normal de upload.

## Hash

O pacote calcula um hash do arquivo armazenado.

O algoritmo padrão é:

```php
'hash_algorithm' => 'sha256',
```

O algoritmo pode ser alterado na configuração.

## Exclusão automática

O comportamento de exclusão do arquivo físico pode ser configurado através de:

```php
'auto_delete_physical_file' => true,
```

Quando habilitado, a exclusão definitiva do registro pode remover também o arquivo físico correspondente.

## Desenvolvimento

O projeto utiliza [Orchestra Testbench](https://packages.tools/testbench) para os testes do pacote.

Instale as dependências de desenvolvimento:

```bash
composer install
```

Execute os testes:

```bash
vendor/bin/phpunit tests
```

A suíte cobre, entre outros casos:

* otimização de imagens;
* conversão para WebP;
* redimensionamento mantendo proporção;
* prevenção de upscale;
* upload de imagens sem otimização;
* upload de arquivos não-imagem;
* metadados do arquivo otimizado;
* hash do arquivo armazenado.

## Compatibilidade

| Versão | PHP  | Laravel     |
| ------ | ---- | ----------- |
| 1.x    | ^8.2 | 11.x / 12.x |

## Licença

Este pacote é distribuído sob a licença MIT.
