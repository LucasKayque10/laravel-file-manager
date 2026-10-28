<?php

namespace LucasBarros\LaravelFileManager\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LucasBarros\LaravelFileManager\Concerns\HasFiles;
use LucasBarros\LaravelFileManager\Relations\MorphToOneFile;

class HasFilesTestModel extends Model
{
    use HasFiles;
    use SoftDeletes;

    protected $table = 'file_manager_test_owners';

    protected $guarded = [];

    public function profileFile(): MorphToOneFile
    {
        return $this->morphToOneFile('profile');
    }
}