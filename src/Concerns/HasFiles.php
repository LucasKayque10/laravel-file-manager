<?php

namespace LucasBarros\LaravelFileManager\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LucasBarros\LaravelFileManager\Models\File;
use LucasBarros\LaravelFileManager\Models\Fileable;
use LucasBarros\LaravelFileManager\Relations\MorphToOneFile;

trait HasFiles
{
    protected static function bootHasFiles(): void
    {
        static::deleting(function ($model) {
            if (
                method_exists($model, 'isForceDeleting')
                && ! $model->isForceDeleting()
            ) {
                return;
            }

            $model->fileables()->delete();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relações
    |--------------------------------------------------------------------------
    */

    public function fileables(): MorphMany
    {
        return $this->morphMany(Fileable::class, 'fileable');
    }

    public function files(): MorphToMany
    {
        return $this->morphToMany(
            File::class,
            'fileable'
        )
            ->using(Fileable::class)
            ->withPivot(Fileable::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    public function orderedFiles(): MorphToMany
    {
        return $this->files()
            ->orderByPivot('sort_order');
    }

    public function filesByCollection(
        string $collection,
        bool $ordered = false
    ): MorphToMany {
        $relation = $this->files()
            ->wherePivot('collection', $collection);

        return $ordered
            ? $relation->orderByPivot('sort_order')
            : $relation;
    }

    /**
     * Relação "hasOne" (com pivot) para uma coleção específica.
     *
     * Uso no model:
     *
     *   public function fotoPerfil(): MorphToOneFile
     *   {
     *       return $this->morphToOneFile('foto_perfil');
     *   }
     */
    public function morphToOneFile(
        string $collection,
        ?string $type = null
    ): MorphToOneFile {
        $instance = $this->newRelatedInstance(File::class);

        $relation = new MorphToOneFile(
            $instance->newQuery(),
            $this,
            'fileable',                     // nome do morph
            (new Fileable())->getTable(),   // tabela pivot
            'fileable_id',                  // FK pivot -> este model
            'file_id',                      // FK pivot -> files
            $this->getKeyName(),
            $instance->getKeyName(),
            null,
            false
        );

        $relation
            ->using(Fileable::class)
            ->withPivot(Fileable::PIVOT_COLUMNS)
            ->withTimestamps()
            ->wherePivot('collection', $collection);

        if ($type !== null) {
            $relation->wherePivot('type', $type);
        }

        // Se existir mais de um por algum motivo, o mais recente vence.
        return $relation->orderByPivot('id', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas
    |--------------------------------------------------------------------------
    */

    public function hasFiles(?string $collection = null): bool
    {
        return $collection
            ? $this->filesByCollection($collection)->exists()
            : $this->files()->exists();
    }

    public function getFiles(
        ?string $collection = null,
        bool $ordered = false
    ): Collection {
        if ($collection) {
            return $this->filesByCollection(
                $collection,
                $ordered
            )->get();
        }

        return $ordered
            ? $this->orderedFiles()->get()
            : $this->files()->get();
    }

    public function firstFile(?string $collection = null): ?File
    {
        return $collection
            ? $this->filesByCollection($collection)
                ->orderByPivot('id', 'asc')
                ->first()
            : $this->files()
                ->orderByPivot('id', 'asc')
                ->first();
    }

    public function lastFile(?string $collection = null): ?File
    {
        return $collection
            ? $this->filesByCollection($collection)
                ->orderByPivot('id', 'desc')
                ->first()
            : $this->files()
                ->orderByPivot('id', 'desc')
                ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Vínculo (coleções)
    |--------------------------------------------------------------------------
    */

    /**
     * Adiciona um arquivo a uma coleção.
     *
     * O vínculo é identificado por file_id + collection + type.
     * Chamar novamente para o mesmo vínculo apenas atualiza
     * description e sort_order, preservando o uuid.
     */
    public function attachFile(
        File $file,
        ?string $description = null,
        string $collection = 'default',
        ?string $type = null,
        int $sortOrder = 0
    ): static {
        $query = $this->fileables()
            ->where('file_id', $file->getKey())
            ->where('collection', $collection);

        $type === null
            ? $query->whereNull('type')
            : $query->where('type', $type);

        $updated = $query->update([
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        if ($updated === 0) {
            $this->fileables()->create([
                'file_id' => $file->getKey(),
                'collection' => $collection,
                'type' => $type,
                'description' => $description,
                'sort_order' => $sortOrder,
            ]);
        }

        $this->unsetRelation('files');

        return $this;
    }

    public function attachFiles(
        iterable $files,
        string $collection = 'default',
        ?string $type = null
    ): void {
        $order = 0;

        foreach ($files as $file) {
            if (! $file instanceof File) {
                throw new InvalidArgumentException(
                    'All items must be instances of File.'
                );
            }

            $this->attachFile(
                file: $file,
                collection: $collection,
                type: $type,
                sortOrder: $order++
            );
        }
    }

    /**
     * Remove o arquivo do model em TODAS as coleções.
     * Para remover de uma coleção específica, use detachFromCollection().
     */

    public function detachFile(File $file): static
    {
        $fileId = $file->getKey();

        $this->fileables()
            ->where('file_id', $fileId)
            ->delete();

        $this->unsetRelation('files');

        $this->forceDeleteOrphansAfterCommit([$fileId]);

        return $this;
    }

    /**
     * Remove vínculos de uma coleção, sem afetar as demais.
     *
     * @param  array<int>|null  $fileIds  null = a coleção inteira
     */
    public function detachFromCollection(
        string $collection,
        ?array $fileIds = null
    ): static {
        $query = $this->fileables()
            ->where('collection', $collection);

        if ($fileIds !== null) {
            $query->whereIn('file_id', $fileIds);
        }

        $removedFileIds = (clone $query)
            ->pluck('file_id')
            ->unique()
            ->values()
            ->all();

        $query->delete();

        $this->unsetRelation('files');

        $this->forceDeleteOrphansAfterCommit($removedFileIds);

        return $this;
    }

    /**
     * Remove os vínculos de uma coleção (ou de todas).
     * Com $deleteFiles, apaga definitivamente os arquivos que
     * ficarem órfãos, somente após o commit.
     */
    public function clearFiles(
        ?string $collection = null
    ): static {
        $fileIds = DB::transaction(function () use ($collection) {
            $query = $this->fileables();

            if ($collection !== null) {
                $query->where('collection', $collection);
            }

            $ids = (clone $query)
                ->pluck('file_id')
                ->unique()
                ->values()
                ->all();

            $query->delete();

            return $ids;
        });

        $this->unsetRelation('files');

        $this->forceDeleteOrphansAfterCommit($fileIds);
        
        return $this;
    }

    public function updateFileDescription(
        File $file,
        ?string $description,
        ?string $collection = null
    ): static {
        $query = $this->fileables()
            ->where('file_id', $file->getKey());

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        $query->update(['description' => $description]);

        $this->unsetRelation('files');

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Vínculo (arquivo único)
    |--------------------------------------------------------------------------
    */

    /**
     * Define o único arquivo de uma coleção, substituindo o anterior.
     *
     * Outras coleções do mesmo arquivo não são afetadas.
     *
     * @param  string|null  $relation  nome da relação carregada (ex.: 'fotoPerfil')
     *                                 a ser descartada do cache do model.
     */
    public function setFile(
        File $file,
        string $collection,
        ?string $description = null,
        ?string $type = null,
        ?string $relation = null,
    ): static {
        $replacedIds = DB::transaction(function () use (
            $file,
            $collection,
            $description,
            $type,
        ) {
            $query = $this->fileables()
                ->where('collection', $collection);

            if ($type === null) {
                $query->whereNull('type');
            } else {
                $query->where('type', $type);
            }

            $stale = $query
                ->get()
                ->reject(
                    fn (Fileable $row): bool =>
                        (int) $row->file_id === (int) $file->getKey()
                );

            if ($stale->isNotEmpty()) {
                $this->fileables()
                    ->whereIn('id', $stale->pluck('id')->all())
                    ->delete();
            }

            $this->attachFile(
                file: $file,
                description: $description,
                collection: $collection,
                type: $type,
            );

            return $stale
                ->pluck('file_id')
                ->unique()
                ->values()
                ->all();
        });

        $this->unsetRelation('files');

        if ($relation !== null) {
            $this->unsetRelation($relation);
        }

        $this->forceDeleteOrphansAfterCommit($replacedIds);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Internos
    |--------------------------------------------------------------------------
    */

    /**
     * Exclui definitivamente os registros de arquivos que ficaram órfãos,
     * incluindo o arquivo físico somente se a configuração correspondente
     * estiver habilitada.
     *
     * Executa após o commit para evitar exclusões em caso de rollback.
     *
     * @param array<int|string> $fileIds
     */
    protected function forceDeleteOrphansAfterCommit(array $fileIds): void
    {
        if ($fileIds === [] || ! config('file-manager.auto_delete_orphan_file')) {
            return;
        }

        DB::afterCommit(function () use ($fileIds) {
            File::query()
                ->whereIn('id', $fileIds)
                ->get()
                ->each(function (File $file) {
                    if ($file->isOrphan()) {
                        $file->forceDelete();
                    }
                });
        });
    }
}