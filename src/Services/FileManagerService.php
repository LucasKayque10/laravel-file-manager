<?php

namespace LucasBarros\LaravelFileManager\Services;

use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LucasBarros\LaravelFileManager\Models\File as FileModel;

class FileManagerService
{
    /**
     * Deve permanecer igual à versão utilizada pelo comando
     * OptimizeExistingFiles.
     */
    private const PDF_OPTIMIZATION_VERSION = 1;

    public function __construct(
        private ImageOptimizer $imageOptimizer,
        private PdfOptimizer $pdfOptimizer,
    ) {}

    public function upload(
        UploadedFile $fileTemp,
        ?string $disk = null,
        ?string $visibility = null,
    ): FileModel {
        return DB::transaction(function () use (
            $fileTemp,
            $disk,
            $visibility
        ) {
            $disk = $disk
                ?? config('file-manager.disk', 'local');

            $visibility = $visibility
                ?? config('file-manager.visibility', 'private');

            $uuid = (string) Str::uuid();

            $mimeTypeOriginal = $fileTemp->getMimeType()
                ?? 'application/octet-stream';

            $isImage = str_starts_with(
                $mimeTypeOriginal,
                'image/'
            );

            $isPdf = $mimeTypeOriginal === 'application/pdf';

            $imageOptimizationEnabled = config(
                'file-manager.image.optimization.enabled',
                true
            );

            $pdfOptimizationEnabled = config(
                'file-manager.pdf.optimization.enabled',
                true
            );

            $optimizedFile = null;
            $metadata = [];

            $originalSize = (int) $fileTemp->getSize();

            try {
                if ($isImage && $imageOptimizationEnabled) {
                    $optimizedFile = $this->imageOptimizer->optimize(
                        $fileTemp
                    );
                } elseif ($isPdf && $pdfOptimizationEnabled) {
                    $optimizedFile = $this->pdfOptimizer->optimize(
                        $fileTemp
                    );
                }

                /*
                 * Se o otimizador retornou um arquivo, compara o tamanho
                 * antes de decidir se o resultado deve ser armazenado.
                 */
                if ($optimizedFile !== null) {
                    $optimizedSize = (int) $optimizedFile['size'];

                    /*
                     * Não utiliza o resultado se ele não for menor.
                     * O upload mantém o arquivo original.
                     */
                    if (
                        ($isPdf || $isImage)
                        && $optimizedSize >= $originalSize
                    ) {
                        if ($isPdf) {
                            $metadata = $this->buildPdfOptimizationMetadata(
                                status: 'no_savings',
                                hash: $this->hashLocalFile(
                                    $fileTemp->getRealPath()
                                ),
                                originalSize: $originalSize,
                                finalSize: $optimizedSize,
                                timestampKey: 'attempted_at',
                            );
                        }

                        @unlink($optimizedFile['path']);

                        $optimizedFile = null;
                    }
                }

                if ($optimizedFile !== null) {
                    $directory = $this->getDirectory();

                    $filename = $uuid . '.'
                        . $optimizedFile['extension'];

                    $path = Storage::disk($disk)->putFileAs(
                        $directory,
                        new HttpFile($optimizedFile['path']),
                        $filename,
                        [
                            'visibility' => $visibility,
                        ]
                    );

                    if (! $path) {
                        throw new \RuntimeException(
                            'Failed to store optimized file.'
                        );
                    }

                    $extension = $optimizedFile['extension'];
                    $mimeType = $optimizedFile['mime_type'];
                    $size = (int) $optimizedFile['size'];

                    /*
                     * Calcula o hash do arquivo já armazenado.
                     * Funciona também com discos remotos.
                     */
                    $hash = $this->hashStoredFile(
                        $disk,
                        $path
                    );

                    if ($isPdf) {
                        $metadata = $this->buildPdfOptimizationMetadata(
                            status: 'completed',
                            hash: $hash,
                            originalSize: $originalSize,
                            finalSize: $size,
                            timestampKey: 'optimized_at',
                        );
                    }
                } else {
                    $directory = $this->getDirectory();

                    $filename = $uuid . '.'
                        . $fileTemp->extension();

                    $path = $fileTemp->storeAs(
                        $directory,
                        $filename,
                        [
                            'disk' => $disk,
                            'visibility' => $visibility,
                        ]
                    );

                    if (! $path) {
                        throw new \RuntimeException(
                            'Failed to store uploaded file.'
                        );
                    }

                    $extension = $fileTemp
                        ->getClientOriginalExtension();

                    $mimeType = $fileTemp->getMimeType();

                    $size = (int) Storage::disk($disk)->size($path);

                    $hash = $this->hashStoredFile(
                        $disk,
                        $path
                    );

                    /*
                     * Registra que a otimização de PDF foi tentada,
                     * mas não houve redução útil, inclusive quando
                     * o otimizador retornou null.
                     *
                     * Se a otimização estiver desabilitada, não
                     * registra uma tentativa inexistente.
                     */
                    if (
                        $isPdf
                        && $pdfOptimizationEnabled
                        && $metadata === []
                    ) {
                        $metadata = $this->buildPdfOptimizationMetadata(
                            status: 'no_savings',
                            hash: $hash,
                            originalSize: $size,
                            finalSize: $size,
                            timestampKey: 'attempted_at',
                        );
                    }
                }

                return FileModel::create([
                    'uuid' => $uuid,

                    'disk' => $disk,
                    'path' => $path,
                    'visibility' => $visibility,

                    'original_name' => $fileTemp
                        ->getClientOriginalName(),

                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'hash' => $hash,

                    'metadata' => $metadata === []
                        ? null
                        : $metadata,

                    'creator_type' => auth()->user()?->getMorphClass(),
                    'creator_id' => auth()->id(),
                ]);
            } finally {
                if (
                    $optimizedFile !== null
                    && isset($optimizedFile['path'])
                    && is_file($optimizedFile['path'])
                ) {
                    @unlink($optimizedFile['path']);
                }
            }
        });
    }

    /**
     * Cria os metadados padronizados da otimização de PDF.
     */
    private function buildPdfOptimizationMetadata(
        string $status,
        string $hash,
        int $originalSize,
        int $finalSize,
        string $timestampKey,
    ): array {
        return [
            'optimization' => [
                'pdf' => [
                    'status' => $status,
                    'version' => self::PDF_OPTIMIZATION_VERSION,
                    'hash' => $hash,
                    $timestampKey => now()->toISOString(),
                    'size_before' => $originalSize,
                    'size_after' => $finalSize,
                ],
            ],
        ];
    }

    /**
     * Calcula o hash de um arquivo local sem carregar seu conteúdo
     * inteiro na memória.
     */
    private function hashLocalFile(string $path): string
    {
        $algorithm = $this->getHashAlgorithm();

        $hash = hash_file($algorithm, $path);

        if ($hash === false) {
            throw new \RuntimeException(
                'Não foi possível calcular o hash do arquivo original.'
            );
        }

        return $hash;
    }

    /**
     * Calcula o hash do conteúdo efetivamente armazenado.
     */
    private function hashStoredFile(
        string $diskName,
        string $path,
    ): string {
        $stream = Storage::disk($diskName)->readStream($path);

        if ($stream === false) {
            throw new \RuntimeException(
                "Não foi possível ler o arquivo armazenado: {$path}"
            );
        }

        try {
            $context = hash_init($this->getHashAlgorithm());

            if (hash_update_stream($context, $stream) === false) {
                throw new \RuntimeException(
                    "Não foi possível calcular o hash do arquivo: {$path}"
                );
            }

            return hash_final($context);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Valida e retorna o algoritmo configurado para os hashes.
     */
    private function getHashAlgorithm(): string
    {
        $algorithm = config(
            'file-manager.hash_algorithm',
            'sha256'
        );

        if (! in_array($algorithm, hash_algos(), true)) {
            throw new \RuntimeException(
                "Algoritmo de hash inválido: {$algorithm}"
            );
        }

        return $algorithm;
    }

    private function getDirectory(): string
    {
        $directory = config(
            'file-manager.path_prefix',
            'files'
        );

        if (
            config(
                'file-manager.use_date_directories',
                true
            )
        ) {
            $directory .= '/' . date('Y/m');
        }

        return $directory;
    }
}
