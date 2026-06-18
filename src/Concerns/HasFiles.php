<?php

namespace LucasBarros\LaravelFileManager\Concerns;

use LucasBarros\LaravelFileManager\Models\File;
use LucasBarros\LaravelFileManager\Models\Fileable;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Collection;

use InvalidArgumentException;

trait HasFiles
{
    protected static function bootHasFiles(): void
    {
        static::deleting(function ($model) {

            if (
                method_exists($model, 'isForceDeleting')
                && !$model->isForceDeleting()
            ) {
                return;
            }

            $model->files()->detach();
        });
    }

    public function hasFiles(?string $collection = null): bool
    {
        return $collection
            ? $this->filesByCollection($collection)->exists()
            : $this->files()->exists();
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

    public function filesByCollection(string $collection, bool $ordered = false): MorphToMany
    {
        $relation = $this->files()
            ->wherePivot('collection', $collection);

        return $ordered
            ? $relation->orderByPivot('sort_order')
            : $relation;
    }

    public function attachFiles(
        iterable $files,
        string $collection = 'default',
        ?string $type = null
    ): void {
        foreach ($files as $file) {
            if (! $file instanceof File) {
                throw new InvalidArgumentException(
                    'All items must be instances of File.'
                );
            }
            
            $this->attachFile(
                file: $file,
                collection: $collection,
                type: $type
            );
        }
    }

    public function attachFile(
        File $file,
        ?string $description = null,
        string $collection = 'default',
        ?string $type = null,
        int $sortOrder = 0
    ): static {
        $this->files()->syncWithoutDetaching([
            $file->id => [
                'description' => $description,
                'collection' => $collection,
                'type' => $type,
                'sort_order' => $sortOrder,
            ]
        ]);

        return $this;
    }

    public function detachFile(File $file): static
    {
        $this->files()->detach($file->id);

        return $this;
    }

    public function clearFiles(
        ?string $collection = null
    ): static
    {
        if ($collection) {

            $ids = $this->filesByCollection($collection)
                ->pluck('files.id');

            $this->files()->detach($ids);

            return $this;
        }

        $this->files()->detach();

        return $this;
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
            ? $this->filesByCollection($collection)->first()
            : $this->files()->first();
    }

    public function lastFile(?string $collection = null): ?File
    {
        return $collection
            ? $this->filesByCollection($collection)
                ->orderByPivot('created_at', 'desc')
                ->first()
            : $this->files()
                ->orderByPivot('created_at', 'desc')
                ->first();
    }
}