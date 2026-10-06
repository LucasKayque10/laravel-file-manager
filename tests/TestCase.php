<?php

namespace LucasBarros\LaravelFileManager\Tests;

use LucasBarros\LaravelFileManager\LaravelFileManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('file-manager')]
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelFileManagerServiceProvider::class,
        ];
    }
}