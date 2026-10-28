# Laravel File Manager

Gerenciamento de arquivos para aplicações Laravel, com suporte a upload, armazenamento, metadados, compartilhamento, associação de arquivos a modelos Eloquent, coleções de arquivos, coleções de arquivo único e otimização automática de imagens e PDFs.

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

Exemplo das opções principais:

```php
return [
    'disk' => 'local',
    'visibility' => 'private',
    'path_prefix' => 'files',
    'use_date_directories' => true,
    'hash_algorithm' => 'sha256',

    'auto_delete_orphan_file' => true,
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

### Exclusão automática de arquivos órfãos

A opção `auto_delete_orphan_file` controla a exclusão automática de registros de arquivos que não possuem vínculos na tabela `fileables`.

```php
'auto_delete_orphan_file' => env(
    'FILE_MANAGER_AUTO_DELETE_ORPHAN_FILE',
    true
),
```

Variável de ambiente:

```env
FILE_MANAGER_AUTO_DELETE_ORPHAN_FILE=true
```

Quando habilitada, o pacote pode excluir definitivamente arquivos que ficam órfãos após a remoção de seus vínculos.

A exclusão é agendada para depois do commit da transação, evitando remover arquivos físicos quando a operação que removeu os vínculos sofrer rollback.

### Exclusão do arquivo físico

A opção `auto_delete_physical_file` controla a remoção do arquivo armazenado no disco quando o registro correspondente é excluído definitivamente.

```env
FILE_MANAGER_AUTO_DELETE_PHYSICAL_FILE=true
```

As duas opções possuem responsabilidades diferentes:

- `auto_delete_orphan_file`: controla a exclusão automática de registros sem vínculos.
- `auto_delete_physical_file`: controla a exclusão do arquivo físico durante a exclusão definitiva do registro.

Desabilitar a exclusão automática de órfãos não impede a exclusão manual de registros. Desabilitar a exclusão física permite manter o arquivo no disco mesmo após a exclusão definitiva de seu registro.

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

```env
FILE_MANAGER_IMAGE_OPTIMIZATION_ENABLED=true
FILE_MANAGER_IMAGE_DRIVER=imagick
FILE_MANAGER_IMAGE_FORMAT=webp
FILE_MANAGER_IMAGE_QUALITY=90
FILE_MANAGER_IMAGE_MAX_WIDTH=1600
FILE_MANAGER_IMAGE_MAX_HEIGHT=1600
```

### Desabilitar a otimização

```env
FILE_MANAGER_IMAGE_OPTIMIZATION_ENABLED=false
```

Quando desabilitada, as imagens seguem o fluxo normal de upload.

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
- Quando a versão otimizada não reduz o tamanho, o PDF original é mantido.

### Ghostscript

O Ghostscript precisa estar instalado no sistema e disponível no `PATH`.

Verifique a instalação:

```bash
gs --version
```

É possível informar explicitamente o caminho do executável:

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

```env
FILE_MANAGER_PDF_OPTIMIZATION_ENABLED=false
```

Quando desabilitada, o PDF segue o fluxo normal de upload.

### Quando a otimização não reduz o arquivo

Quando o arquivo otimizado tiver tamanho igual ou superior ao original, a versão otimizada é descartada e o PDF original é mantido.

Isso evita que a otimização resulte em um arquivo maior.

## Otimização de arquivos existentes

A versão `1.2` disponibiliza o comando Artisan `file-manager:optimize-existing-files` para processar arquivos já armazenados.

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

```bash
php artisan file-manager:optimize-existing-files --force
```

O comando considera os arquivos existentes e seus metadados para evitar processamento desnecessário.

Para PDFs, o arquivo original é preservado quando a otimização não reduz o tamanho. O processamento também contempla a validação do arquivo otimizado antes da substituição.

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

## Associação de arquivos a modelos Eloquent

Para associar arquivos a um modelo, utilize o trait `HasFiles`.

```php
use Illuminate\Database\Eloquent\Model;
use LucasBarros\LaravelFileManager\Concerns\HasFiles;

class Documento extends Model
{
    use HasFiles;
}
```

O trait disponibiliza os relacionamentos e métodos necessários para consultar, associar, substituir e remover arquivos.

### Relacionamentos disponíveis

#### `files()`

Retorna um relacionamento polimórfico muitos-para-muitos com os arquivos associados ao modelo.

```php
$documento->files;
```

#### `fileables()`

Retorna os registros de vínculo do modelo na tabela `fileables`.

```php
$documento->fileables;
```

Esse relacionamento permite consultar e manipular os metadados de cada vínculo.

#### `filesByCollection()`

Retorna os arquivos de uma coleção específica.

```php
$documento->filesByCollection('anexos')->get();
```

Para retornar os arquivos ordenados pelo campo `sort_order`:

```php
$documento->filesByCollection(
    'anexos',
    ordered: true,
)->get();
```

### Consultas de arquivos

Verificar se o modelo possui arquivos:

```php
$documento->hasFiles();
```

Verificar se existe algum arquivo em uma coleção:

```php
$documento->hasFiles('anexos');
```

Obter os arquivos de uma coleção:

```php
$documento->getFiles('anexos');
```

Obter todos os arquivos ordenados:

```php
$documento->getFiles(ordered: true);
```

Obter o primeiro arquivo de uma coleção:

```php
$documento->firstFile('anexos');
```

Obter o último arquivo de uma coleção:

```php
$documento->lastFile('anexos');
```

Os métodos `firstFile()` e `lastFile()` retornam um objeto `File` ou `null` quando não há arquivos correspondentes.

## Coleções de arquivos

As coleções permitem organizar os arquivos associados a um modelo, separando, por exemplo, documentos, fotografias e anexos.

### Associar um arquivo

Utilize `attachFile()`:

```php
$documento->attachFile(
    file: $file,
    description: 'Documento assinado',
    collection: 'anexos',
    type: 'documento_assinado',
    sortOrder: 0,
);
```

Parâmetros:

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `file` | `File` | Arquivo a ser associado |
| `description` | `?string` | Descrição do vínculo |
| `collection` | `string` | Nome da coleção; padrão `default` |
| `type` | `?string` | Tipo do vínculo |
| `sortOrder` | `int` | Ordem de exibição; padrão `0` |

O método retorna o próprio modelo, permitindo encadeamento.

### Associar vários arquivos

Utilize `attachFiles()`:

```php
$documento->attachFiles(
    files: $files,
    collection: 'anexos',
    type: 'documento',
);
```

O parâmetro `files` deve ser um iterável contendo instâncias de `File`.

A ordem dos elementos no iterável é utilizada para preencher `sort_order`, começando em zero. Se algum elemento não for uma instância de `File`, o método lança uma `InvalidArgumentException`.

### Reutilização de um vínculo

Chamar `attachFile()` novamente para o mesmo arquivo, coleção e tipo atualiza `description` e `sort_order` do vínculo existente, preservando seu UUID.

A identificação utilizada pelo método é composta por:

- `file_id`;
- `collection`;
- `type`, incluindo a distinção entre `null` e valores definidos.

Isso permite atualizar os metadados de um vínculo sem gerar um novo UUID a cada chamada.

### Atualizar a descrição

Para atualizar a descrição de um arquivo associado ao modelo:

```php
$documento->updateFileDescription(
    file: $file,
    description: 'Documento revisado',
    collection: 'anexos',
);
```

O parâmetro `collection` é opcional. Quando informado, a atualização fica restrita à coleção especificada.

Quando omitido, todos os vínculos daquele arquivo com o modelo são considerados.

O método retorna o próprio modelo.

### Remover um arquivo de todas as coleções do modelo

```php
$documento->detachFile($file);
```

Esse método remove os vínculos do arquivo com o modelo em todas as coleções, sem remover vínculos que o arquivo possua com outros modelos.

Se o arquivo ficar órfão e `auto_delete_orphan_file` estiver habilitado, o registro poderá ser excluído definitivamente após o commit.

### Remover arquivos de uma coleção específica

Para remover todos os arquivos de uma coleção:

```php
$documento->detachFromCollection('anexos');
```

Para remover apenas determinados arquivos da coleção:

```php
$documento->detachFromCollection(
    collection: 'anexos',
    fileIds: [$fileId1, $fileId2],
);
```

O parâmetro `fileIds` aceita um array de identificadores de arquivos. Quando omitido ou definido como `null`, todos os vínculos da coleção são removidos.

As demais coleções do modelo não são afetadas.

### Limpar os arquivos

Para remover os vínculos de uma coleção:

```php
$documento->clearFiles('anexos');
```

Para remover os vínculos de todas as coleções:

```php
$documento->clearFiles();
```

Esse método remove os vínculos do modelo. Quando a exclusão automática de órfãos está habilitada, os arquivos que ficarem sem vínculos poderão ser excluídos definitivamente após o commit.

A exclusão definitiva do arquivo físico continua sujeita à configuração `auto_delete_physical_file`.

## Coleções de arquivo único

Algumas situações exigem que uma coleção possua apenas um arquivo associado por modelo, como uma fotografia de perfil, uma imagem de capa ou um documento principal.

Para esses casos, o pacote disponibiliza a relação `MorphToOneFile`, que se comporta como um relacionamento de arquivo único.

### Definir a relação no modelo

```php
use Illuminate\Database\Eloquent\Model;
use LucasBarros\LaravelFileManager\Concerns\HasFiles;
use LucasBarros\LaravelFileManager\Relations\MorphToOneFile;

class Usuario extends Model
{
    use HasFiles;

    public function fotoPerfil(): MorphToOneFile
    {
        return $this->morphToOneFile('foto_perfil');
    }
}
```

O método `morphToOneFile()` recebe:

- `collection`: nome da coleção que contém o arquivo único;
- `type`: tipo opcional utilizado para filtrar os vínculos.

Exemplo com tipo:

```php
public function documentoPrincipal(): MorphToOneFile
{
    return $this->morphToOneFile(
        'documento_principal',
        'identidade',
    );
}
```

### Consultar o arquivo único

A relação retorna um objeto `File` ou `null`, em vez de uma coleção de arquivos.

```php
$usuario->fotoPerfil;
```

Verificar se existe uma fotografia:

```php
if ($usuario->fotoPerfil !== null) {
    // A fotografia está associada.
}
```

A relação também pode ser utilizada em consultas com carregamento antecipado:

```php
$usuarios = Usuario::with('fotoPerfil')->get();
```

### Substituir o arquivo da coleção

Utilize `setFile()` para definir o arquivo único:

```php
$usuario->setFile(
    file: $file,
    collection: 'foto_perfil',
    description: 'Fotografia de perfil',
    relation: 'fotoPerfil',
);
```

O método:

1. Remove os vínculos anteriores da coleção, exceto o vínculo correspondente ao mesmo arquivo e tipo.
2. Associa o novo arquivo.
3. Mantém as demais coleções do modelo.
4. Invalida o relacionamento `files` em cache e, se informado, o relacionamento indicado em `relation`.
5. Agenda a verificação dos arquivos substituídos para exclusão automática, quando habilitada.

O parâmetro `relation` é o nome do método de relacionamento no model, utilizado para invalidar seu valor em cache após a substituição.

Se o arquivo anterior ficar órfão, ele poderá ser excluído definitivamente após o commit, conforme as configurações do pacote.

**Importante:** `setFile()` trabalha com os vínculos da coleção informada. Se forem utilizados diferentes valores de `type` na mesma coleção, a substituição também removerá os vínculos de outros tipos daquela coleção, exceto o vínculo correspondente ao mesmo arquivo e tipo informado.

## UUID dos vínculos de arquivos

A partir da versão `1.2`, os registros da tabela `fileables` possuem um UUID único, além do identificador numérico (`id`).

Cada UUID identifica individualmente um vínculo entre um arquivo e um modelo Eloquent.

O UUID não substitui o identificador numérico nem altera as chaves utilizadas internamente nos relacionamentos existentes.

Ao atualizar uma instalação que já possui registros na tabela `fileables`, a migration correspondente adiciona a coluna `uuid` e preenche os UUIDs dos vínculos existentes.

Novos vínculos recebem UUID automaticamente.

Ao atualizar um vínculo existente através de `attachFile()`, o UUID é preservado.

## Exclusão de arquivos órfãos

Um arquivo é considerado órfão quando não possui vínculos na tabela `fileables`.

O model `File` disponibiliza o método:

```php
$file->isOrphan();
```

O retorno é um booleano:

- `true`: o arquivo não possui vínculos.
- `false`: o arquivo ainda está associado a pelo menos um modelo.

### Exclusão automática

Quando `auto_delete_orphan_file` está habilitado, operações de remoção e substituição de vínculos podem agendar a exclusão definitiva dos arquivos que ficarem órfãos.

A verificação ocorre após o commit e considera os vínculos existentes no momento da exclusão.

Quando um registro é excluído definitivamente, o comportamento de remoção do arquivo físico depende de `auto_delete_physical_file`.

### Comando Artisan

A partir desta atualização, o pacote disponibiliza o comando:

```bash
php artisan file-manager:delete-orphan-files
```

O comando localiza os registros sem vínculos, incluindo registros que tenham sido excluídos logicamente.

Antes de excluir os registros, o comando solicita confirmação, salvo quando a opção `--force` é utilizada.

#### Simulação com `--dry-run`

Para listar os arquivos órfãos sem excluir registros ou arquivos físicos:

```bash
php artisan file-manager:delete-orphan-files --dry-run
```

A listagem apresenta:

- ID do arquivo;
- Disco de armazenamento;
- Caminho do arquivo;
- Indicação de exclusão lógica.

O modo de simulação não executa a exclusão.

#### Excluir sem confirmação

```bash
php artisan file-manager:delete-orphan-files --force
```

O comando exclui definitivamente os registros que continuarem órfãos no momento da verificação.

Antes da exclusão, cada arquivo é revalidado para evitar excluir um registro que tenha recebido um novo vínculo durante a execução.

Registros que deixarem de ser órfãos são ignorados.

A quantidade de registros excluídos e de registros ignorados é apresentada ao final da execução.

#### Comportamento da exclusão

O comando executa `forceDelete()` nos registros órfãos. Portanto, a opção `auto_delete_physical_file` determina se os arquivos físicos correspondentes também serão removidos.

A opção `auto_delete_orphan_file` controla a exclusão automática associada às operações normais do pacote; o comando de limpeza pode ser executado explicitamente mesmo quando essa opção está desabilitada.

**Recomendação:** execute primeiro o modo `--dry-run`, confira os caminhos listados e faça um backup antes de realizar a exclusão definitiva em produção.

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

## Exclusão de arquivos

O model `File` utiliza exclusão lógica por meio de `SoftDeletes`.

### Exclusão lógica

```php
$file->delete();
```

A exclusão lógica marca o registro como excluído, sem disparar o evento `forceDeleted` responsável pela remoção física.

### Exclusão definitiva

```php
$file->forceDelete();
```

A exclusão definitiva remove o registro e, quando `auto_delete_physical_file` está habilitado, também remove o arquivo físico correspondente ao disco e caminho registrados.

### Verificar a existência física

```php
$file->fileExists();
```

O método verifica se o arquivo existe no disco configurado para aquele registro.

### Obter a URL

```php
$file->url();
```

O método retorna a URL gerada pelo disco de armazenamento.

O método `downloadUrl()` também está disponível:

```php
$file->downloadUrl();
```

A URL gerada depende da configuração do disco. Discos privados podem exigir uma estratégia específica de acesso, conforme a configuração da aplicação.

### Excluir o arquivo através do model

```php
$file->deleteFile();
```

Esse método remove o arquivo físico, caso exista, e em seguida realiza a exclusão lógica do registro.

Diferentemente de `forceDelete()`, esse método não exclui definitivamente o registro.

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
