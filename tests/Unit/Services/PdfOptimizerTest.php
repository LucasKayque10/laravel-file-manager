<?php

namespace LucasBarros\LaravelFileManager\Tests\Unit\Services;

use Illuminate\Http\UploadedFile;
use LucasBarros\LaravelFileManager\Services\PdfOptimizer;
use LucasBarros\LaravelFileManager\Tests\Support\PdfFixture;
use LucasBarros\LaravelFileManager\Tests\TestCase;

class PdfOptimizerTest extends TestCase
{
    public function test_it_optimizes_pdf(): void
    {
        config()->set(
            'file-manager.pdf.optimization.quality',
            'ebook'
        );

        config()->set(
            'file-manager.pdf.optimization.ghostscript_binary',
            'gs'
        );

        $sourcePath = PdfFixture::create();

        $uploadedFile = new UploadedFile(
            $sourcePath,
            'pdf_teste.pdf',
            'application/pdf',
            null,
            true
        );

        $result = null;

        try {
            $result = app(PdfOptimizer::class)->optimize(
                $uploadedFile
            );

            $this->assertNotNull($result);

            $this->assertFileExists(
                $result['path']
            );

            $this->assertSame(
                'pdf',
                $result['extension']
            );

            $this->assertSame(
                'application/pdf',
                $result['mime_type']
            );

            $this->assertGreaterThan(
                0,
                $result['size']
            );

            $this->assertLessThan(
                filesize($sourcePath),
                $result['size']
            );
        } finally {
            if (
                $result !== null
                && isset($result['path'])
                && is_file($result['path'])
            ) {
                @unlink($result['path']);
            }

            PdfFixture::cleanup($sourcePath);
        }
    }

    public function test_it_returns_null_when_optimization_does_not_reduce_pdf(): void
    {
        config()->set(
            'file-manager.pdf.optimization.quality',
            'printer'
        );

        config()->set(
            'file-manager.pdf.optimization.ghostscript_binary',
            'gs'
        );

        $sourcePath = PdfFixture::createMinimal();

        $uploadedFile = new UploadedFile(
            $sourcePath,
            'pdf_teste.pdf',
            'application/pdf',
            null,
            true
        );

        try {
            $result = app(PdfOptimizer::class)->optimize(
                $uploadedFile
            );

            $this->assertNull($result);
        } finally {
            PdfFixture::cleanup($sourcePath);
        }
    }

    public function test_it_rejects_unsupported_quality(): void
    {
        config()->set(
            'file-manager.pdf.optimization.quality',
            'invalid'
        );

        $sourcePath = PdfFixture::create();

        $uploadedFile = new UploadedFile(
            $sourcePath,
            'pdf_teste.pdf',
            'application/pdf',
            null,
            true
        );

        try {
            $this->expectException(
                \InvalidArgumentException::class
            );

            $this->expectExceptionMessage(
                'Unsupported PDF optimization quality.'
            );

            app(PdfOptimizer::class)->optimize(
                $uploadedFile
            );
        } finally {
            PdfFixture::cleanup($sourcePath);
        }
    }
}