# Laravel File Manager

Gerenciamento de arquivos para aplicações Laravel, com suporte a upload, armazenamento, metadados, compartilhamento, associação de arquivos a modelos Eloquent e otimização automática de imagens e PDFs.

## Requisitos

- PHP 8.2+
- Laravel 11 ou 12
- Extensão `fileinfo`

Para otimização de imagens:

- Extensão `imagick` ou `gd`
- Suporte ao formato de saída configurado

Para otimização de PDFs:

- Ghostscript 10+ recomendado
- Comando `gs` disponível no sistema

A otimização de PDFs requer o Ghostscript quando estiver habilitada.

A otimização de imagens e PDFs pode ser desabilitada individualmente através da configuração.

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

    'pdf' => [
        'optimization' => [
            'enabled' => true,
            'quality' => 'ebook',
            'ghostscript_binary' => 'gs',
        ],
    ],

];
```

## Otimização de imagens

Desde a versão `1.1`, imagens podem ser otimizadas automaticamente durante o upload.

Por padrão:

- As imagens são convertidas para WebP.
- A qualidade é `90`.
- A largura máxima é `1600px`.
- A altura máxima é `1600px`.
- A proporção original é preservada.
- Imagens menores não são ampliadas.
- O arquivo original não é armazenado separadamente; a versão otimizada é armazenada.

Por exemplo, uma imagem de `3000 × 2000` será armazenada aproximadamente com estas dimensões:

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

### Desabilitar a otimização

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

## Otimização de PDFs

A partir da versão `1.2`, arquivos PDF podem ser otimizados automaticamente durante o upload utilizando o Ghostscript.

Por padrão:

- A otimização está habilitada.
- O preset utilizado é `ebook`.
- O arquivo resultante continua sendo PDF.
- O arquivo original somente é substituído quando a versão otimizada fica menor.
- Quando a versão otimizada não reduz o tamanho do arquivo, o PDF original é mantido.

A otimização utiliza o comando `gs` do Ghostscript.

### Ghostscript

O Ghostscript precisa estar instalado no sistema e disponível no `PATH`.

Verifique a instalação:

```bash
gs --version
```

Também é possível informar explicitamente o caminho do executável através da configuração:

```env
FILE_MANAGER_PDF_GHOSTSCRIPT_BINARY=/usr/bin/gs
```

Por padrão:

```env
FILE_MANAGER_PDF_GHOSTSCRIPT_BINARY=gs
```

### Preset de qualidade

O preset padrão é:

```env
FILE_MANAGER_PDF_QUALITY=ebook
```

Os presets disponíveis são:

```text
screen
ebook
printer
prepress
default
```

De forma geral:

- `screen`: prioriza arquivos menores para visualização em tela.
- `ebook`: oferece equilíbrio entre tamanho e qualidade.
- `printer`: é voltado para impressão com maior qualidade.
- `prepress`: é voltado para fluxos de pré-impressão.
- `default`: utiliza as configurações padrão do Ghostscript.

Para a maioria dos documentos destinados à visualização digital, `ebook` é o preset recomendado.

### Desabilitar a otimização

Para manter o PDF sem otimização:

```env
FILE_MANAGER_PDF_OPTIMIZATION_ENABLED=false
```

Quando a otimização estiver desabilitada, o PDF seguirá o fluxo normal de upload.

### Quando a otimização não reduz o arquivo

Nem todo PDF será reduzido pelo Ghostscript.

Quando o arquivo otimizado tiver tamanho igual ou superior ao original, a versão otimizada é descartada e o arquivo original é mantido.

Isso evita que a operação de otimização resulte em um arquivo maior.

## Otimização de arquivos existentes

A versão `1.2` disponibiliza o comando Artisan `file-manager:optimize-existing-files` para processar arquivos que já estão armazenados.

Execute o comando:

```bash
php artisan file-manager:optimize-existing-files
```

O comando processa os formatos suportados pelos otimizadores de imagens e PDFs, respeitando as configurações correspondentes.

### Simulação

Para simular a execução sem substituir os arquivos:

```bash
php artisan file-manager:optimize-existing-files --dry-run
```

### Forçar o reprocessamento

Para forçar o reprocessamento dos arquivos, conforme as regras do comando:

```bash
php artisan file-manager:optimize-existing-files --force
```

O comando considera os arquivos existentes e seus metadados para evitar processamento desnecessário.

Para PDFs, o arquivo original é preservado quando a otimização não reduz o tamanho. O processamento também contempla validação do arquivo otimizado antes da substituição.

Antes de executar o comando em produção, recomenda-se realizar um backup e avaliar o resultado em um ambiente de teste.

## Upload

O upload pode ser realizado através da facade `FileManager`:

```php
use LucasBarros\LaravelFileManager\Facades\FileManager;

$file = FileManager::upload($uploadedFile);
```

Também é possível informar o disco e a visibilidade:

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

A otimização é aplicada automaticamente de acordo com o tipo do arquivo e as configurações correspondentes.

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

Para arquivos otimizados, os metadados registrados correspondem ao arquivo físico armazenado.

Exemplo de metadados de uma imagem convertida para WebP:

```text
original_name: foto.jpg
extension:     webp
mime_type:     image/webp
size:          tamanho do WebP
hash:          hash do WebP
```

Exemplo de metadados de um PDF otimizado:

```text
original_name: documento.pdf
extension:     pdf
mime_type:     application/pdf
size:          tamanho do PDF otimizado
hash:          hash do PDF otimizado
```

O nome original enviado pelo usuário permanece disponível através de `original_name`.

## UUID dos vínculos de arquivos

A partir da versão `1.2`, os registros da tabela `fileables` possuem um UUID único, além do identificador numérico (`id`).

Cada UUID identifica individualmente um vínculo entre um arquivo e um modelo Eloquent.

O UUID não substitui o identificador numérico nem altera as chaves utilizadas internamente nos relacionamentos existentes.

Ao atualizar uma instalação que já possui registros na tabela `fileables`, a nova migration adiciona a coluna `uuid` e preenche os UUIDs dos vínculos existentes.

## Arquivos que não possuem otimização

A otimização automática é aplicada somente aos formatos suportados pelo pacote.

Atualmente:

- Imagens podem ser otimizadas.
- PDFs podem ser otimizados.
- Outros formatos seguem o fluxo normal de upload.

Exemplos de arquivos que continuam sem otimização automática:

- Documentos.
- Planilhas.
- Arquivos de texto.
- Arquivos compactados.
- Outros formatos não suportados pelos otimizadores.

## Hash

O pacote calcula um hash do arquivo armazenado.

O algoritmo padrão é:

```php
'hash_algorithm' => 'sha256',
```

O algoritmo pode ser alterado na configuração.

Quando um arquivo é otimizado, o hash corresponde ao arquivo otimizado efetivamente armazenado.

## Exclusão automática

O comportamento de exclusão do arquivo físico pode ser configurado através de:

```php
'auto_delete_physical_file' => true,
```

Quando habilitado, a exclusão definitiva do registro pode remover também o arquivo físico correspondente.

## Desenvolvimento

O projeto utiliza [Orchestra Testbench](https://packages.tools/testbench) para executar os testes do pacote.

Instale as dependências:

```bash
composer install
```

Execute os testes:

```bash
vendor/bin/phpunit tests
```

A suíte contempla casos relacionados a:

- Otimização de imagens.
- Conversão para WebP.
- Redimensionamento mantendo a proporção.
- Prevenção de ampliação de imagens menores.
- Upload de imagens sem otimização.
- Otimização de PDFs.
- Preservação do PDF original quando a otimização não reduz o tamanho.
- Upload de PDFs sem otimização.
- Upload de arquivos que não são imagens nem PDFs.
- Metadados do arquivo otimizado.
- Hash do arquivo armazenado.

## Compatibilidade

| Versão | PHP | Laravel |
|---|---|---|
| 1.x | ^8.2 | 11.x / 12.x |

## Licença

Este pacote é distribuído sob a licença MIT.