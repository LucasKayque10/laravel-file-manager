<?php

namespace LucasBarros\LaravelFileManager\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class PdfOptimizer
{
    /**
     * Presets de qualidade aceitos pelo Ghostscript.
     */
    private const QUALITY_PRESETS = [
        'screen',
        'ebook',
        'printer',
        'prepress',
        'default',
    ];

    /**
     * Otimiza um PDF e retorna o arquivo temporário otimizado.
     *
     * Retorna null quando o PDF otimizado não fica menor
     * que o arquivo original.
     *
     * @return array{
     *     path: string,
     *     extension: string,
     *     mime_type: string,
     *     size: int
     * }|null
     */
    public function optimize(UploadedFile $file): ?array
    {
        $originalPath = $file->getRealPath();

        if (
            $originalPath === false
            || ! is_file($originalPath)
        ) {
            throw new \RuntimeException(
                'Failed to access uploaded PDF.'
            );
        }

        $quality = strtolower(
            (string) config(
                'file-manager.pdf.optimization.quality',
                'ebook'
            )
        );

        if (! in_array($quality, self::QUALITY_PRESETS, true)) {
            throw new \InvalidArgumentException(
                'Unsupported PDF optimization quality.'
            );
        }

        $ghostscriptBinary = (string) config(
            'file-manager.pdf.optimization.ghostscript_binary',
            'gs'
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'file-manager-'
        );

        if ($temporaryPath === false) {
            throw new \RuntimeException(
                'Failed to create temporary file for PDF optimization.'
            );
        }

        try {
            $process = new Process([
                $ghostscriptBinary,

                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.7',
                '-dPDFSETTINGS=/' . $quality,

                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',

                '-sOutputFile=' . $temporaryPath,

                $originalPath,
            ]);

            $process->run();

            if (! $process->isSuccessful()) {
                $error = trim(
                    $process->getErrorOutput()
                    ?: $process->getOutput()
                );

                throw new \RuntimeException(
                    'PDF optimization failed.'
                    . ($error !== '' ? ' ' . $error : '')
                );
            }

            if (
                ! is_file($temporaryPath)
                || filesize($temporaryPath) === false
                || filesize($temporaryPath) === 0
            ) {
                throw new \RuntimeException(
                    'Ghostscript did not generate a valid PDF.'
                );
            }

            $mimeType = mime_content_type($temporaryPath);

            if ($mimeType !== 'application/pdf') {
                throw new \RuntimeException(
                    'Ghostscript generated an invalid PDF.'
                );
            }

            $originalSize = filesize($originalPath);
            $optimizedSize = filesize($temporaryPath);

            if (
                $originalSize === false
                || $optimizedSize === false
            ) {
                throw new \RuntimeException(
                    'Failed to determine PDF file size.'
                );
            }

            /*
             * Se a otimização não produzir redução real,
             * mantém-se o arquivo original.
             */
            if ($optimizedSize >= $originalSize) {
                @unlink($temporaryPath);

                return null;
            }

            return [
                'path' => $temporaryPath,
                'extension' => 'pdf',
                'mime_type' => $mimeType,
                'size' => $optimizedSize,
            ];
        } catch (\Throwable $exception) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $exception;
        }
    }
}