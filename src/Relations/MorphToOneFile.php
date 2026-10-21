<?php

namespace LucasBarros\LaravelFileManager\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * MorphToMany que se comporta como "hasOne":
 * $model->relacao devolve um único File (com pivot) ou null.
 */
class MorphToOneFile extends MorphToMany
{
    public function getResults()
    {
        return ! is_null($this->parent->{$this->parentKey})
            ? $this->first()
            : null;
    }

    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation)
    {
        // Preenche cada model com uma Collection (já ordenada pela query)...
        parent::match($models, $results, $relation);

        // ...e reduz para o primeiro item (ou null).
        foreach ($models as $model) {
            $value = $model->getRelation($relation);

            $model->setRelation(
                $relation,
                $value instanceof Collection ? $value->first() : null
            );
        }

        return $models;
    }
}