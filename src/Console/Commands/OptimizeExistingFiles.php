<?php

namespace LucasBarros\LaravelFileManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LucasBarros\LaravelFileManager\Models\File;
use LucasBarros\LaravelFileManager\Services\ImageOptimizer;
use LucasBarros\LaravelFileManager\Services\PdfOptimizer;

class OptimizeExistingFiles extends Command
{
    protected $signature = 'file-manager:optimize-existing-files
        {--dry-run : Apenas mostra o que seria processado}
        {--force : Reprocessa arquivos já otimizados}
        {--type=all : Tipo de arquivo: all, image ou pdf}';

    protected $description = 'Otimiza imagens e PDFs existentes no Laravel File Manager';

    /**
     * Incrementar quando a estratégia de otimização de PDFs mudar.
     */
    private const PDF_OPTIMIZATION_VERSION = 1;

    public function handle(
        ImageOptimizer $imageOptimizer,
        PdfOptimizer $pdfOptimizer,
    ): int {
        $type = strtolower((string) $this->option('type'));

        if (! in_array($type, ['all', 'image', 'pdf'], true)) {
            $this->error(
                'Tipo inválido. Utilize --type=all, --type=image ou --type=pdf.'
            );

            return self::INVALID;
        }

        $query = File::query()
            ->whereNotNull('path')
            ->whereNotNull('mime_type')
            ->where(function ($query) use ($type) {
                if ($type === 'image') {
                    $query->where('mime_type', 'like', 'image/%');

                    return;
                }

                if ($type === 'pdf') {
                    $query->where('mime_type', 'application/pdf');

                    return;
                }

                $query->where('mime_type', 'like', 'image/%')
                    ->orWhere('mime_type', 'application/pdf');
            });

        $files = $query->get();

        if ($files->isEmpty()) {
            $this->info('Nenhum arquivo encontrado.');

            return self::SUCCESS;
        }

        $this->info("Arquivos encontrados: {$files->count()}");

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $hashAlgorithm = config(
            'file-manager.hash_algorithm',
            'sha256'
        );

        if (! in_array($hashAlgorithm, hash_algos(), true)) {
            $this->error(
                "Algoritmo de hash inválido: {$hashAlgorithm}"
            );

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->newLine();
            $this->line("Arquivo #{$file->id}: {$file->path}");

            $mimeType = strtolower((string) $file->mime_type);
            $isImage = str_starts_with($mimeType, 'image/');
            $isPdf = $mimeType === 'application/pdf';

            if (! $isImage && ! $isPdf) {
                $this->comment('Ignorado: tipo não suportado.');
                $skipped++;

                continue;
            }

            if (
                $isImage
                && ! $this->option('force')
                && strtolower((string) $file->extension) === 'webp'
            ) {
                $this->comment('Ignorado: imagem já é WebP.');
                $skipped++;

                continue;
            }

            $disk = Storage::disk($file->disk);
            $oldPath = $file->path;

            if (! $disk->exists($oldPath)) {
                $this->error('Arquivo físico não encontrado.');
                $failed++;

                continue;
            }

            $originalHash = null;

            if ($isPdf) {
                try {
                    $originalHash = $this->hashStoredFile(
                        $disk,
                        $oldPath,
                        $hashAlgorithm
                    );
                } catch (\Throwable $exception) {
                    $this->error(
                        'Não foi possível calcular o hash do PDF: '
                        . $exception->getMessage()
                    );

                    $failed++;

                    continue;
                }

                $optimization = data_get(
                    $file->metadata,
                    'optimization.pdf'
                );

                if (
                    ! $this->option('force')
                    && is_array($optimization)
                    && ($optimization['version'] ?? null)
                        === self::PDF_OPTIMIZATION_VERSION
                    && ($optimization['hash'] ?? null) === $originalHash
                    && in_array(
                        $optimization['status'] ?? null,
                        ['completed', 'no_savings'],
                        true
                    )
                ) {
                    $this->comment(
                        'Ignorado: PDF já processado e sem alterações no conteúdo.'
                    );

                    $skipped++;

                    continue;
                }
            }

            if ($this->option('dry-run')) {
                $this->comment(
                    'Seria otimizado: ' . ($isImage ? 'imagem' : 'PDF') . '.'
                );

                $skipped++;

                continue;
            }

            $temporaryInput = null;
            $temporaryOutput = null;
            $stagedPath = null;
            $backupOldPath = null;
            $backupDestinationPath = null;

            $stagedWritten = false;
            $oldMoved = false;
            $destinationMoved = false;
            $finalWritten = false;
            $databaseUpdated = false;

            $newPath = null;

            try {
                $originalSize = (int) $disk->size($oldPath);

                /*
                 * Copia o original para um arquivo temporário local,
                 * sem carregar todo o conteúdo na memória.
                 */
                $temporaryInput = tempnam(
                    sys_get_temp_dir(),
                    'file-manager-input-'
                );

                if ($temporaryInput === false) {
                    throw new \RuntimeException(
                        'Não foi possível criar arquivo temporário.'
                    );
                }

                $stream = $disk->readStream($oldPath);

                if ($stream === false) {
                    throw new \RuntimeException(
                        'Não foi possível ler o arquivo original.'
                    );
                }

                try {
                    $output = fopen($temporaryInput, 'wb');

                    if ($output === false) {
                        throw new \RuntimeException(
                            'Não foi possível abrir o arquivo temporário.'
                        );
                    }

                    try {
                        if (
                            stream_copy_to_stream($stream, $output) === false
                        ) {
                            throw new \RuntimeException(
                                'Não foi possível copiar o arquivo original.'
                            );
                        }
                    } finally {
                        fclose($output);
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $uploadedFile = new UploadedFile(
                    $temporaryInput,
                    basename($oldPath),
                    $mimeType,
                    null,
                    true
                );

                $optimized = $isImage
                    ? $imageOptimizer->optimize($uploadedFile)
                    : $pdfOptimizer->optimize($uploadedFile);

                if ($optimized === null) {
                    if ($isPdf) {
                        $this->savePdfOptimizationMetadata(
                            $file,
                            [
                                'status' => 'no_savings',
                                'version' => self::PDF_OPTIMIZATION_VERSION,
                                'hash' => $originalHash,
                                'attempted_at' => now()->toISOString(),
                                'size_before' => $originalSize,
                                'size_after' => $originalSize,
                            ]
                        );
                    }

                    $this->comment(
                        'Ignorado: a otimização não reduziu o tamanho.'
                    );

                    $skipped++;

                    continue;
                }

                $temporaryOutput = $optimized['path'];
                $optimizedSize = (int) $optimized['size'];

                /*
                 * Confere o arquivo produzido pelo otimizador antes
                 * de fazer qualquer alteração no armazenamento.
                 */
                if (
                    ! is_file($temporaryOutput)
                    || ! is_readable($temporaryOutput)
                ) {
                    throw new \RuntimeException(
                        'O arquivo otimizado não existe ou não pode ser lido.'
                    );
                }

                $actualOptimizedSize = filesize($temporaryOutput);

                if (
                    $actualOptimizedSize === false
                    || $actualOptimizedSize !== $optimizedSize
                ) {
                    throw new \RuntimeException(
                        'O tamanho informado pelo otimizador não corresponde ao arquivo gerado.'
                    );
                }

                /*
                 * Sem economia, o original permanece intacto.
                 */
                if ($optimizedSize >= $originalSize) {
                    if ($isPdf) {
                        $this->savePdfOptimizationMetadata(
                            $file,
                            [
                                'status' => 'no_savings',
                                'version' => self::PDF_OPTIMIZATION_VERSION,
                                'hash' => $originalHash,
                                'attempted_at' => now()->toISOString(),
                                'size_before' => $originalSize,
                                'size_after' => $optimizedSize,
                            ]
                        );
                    }

                    $this->comment(
                        'Ignorado: o arquivo otimizado não ficou menor.'
                    );

                    $skipped++;

                    continue;
                }

                /*
                 * O nome definitivo é sempre o UUID do registro.
                 * O diretório original é preservado.
                 */
                $directory = dirname($oldPath);
                $directoryPrefix = $directory === '.'
                    ? ''
                    : $directory . '/';

                $extension = strtolower(
                    ltrim((string) $optimized['extension'], '.')
                );

                if ($extension === '') {
                    throw new \RuntimeException(
                        'O otimizador retornou uma extensão inválida.'
                    );
                }

                $newPath = $directoryPrefix
                    . $file->uuid
                    . '.'
                    . $extension;

                /*
                 * Grava primeiro em um caminho temporário no mesmo disco.
                 * Nenhum arquivo original é removido nesta etapa.
                 */
                $stagedPath = $this->uniqueStoragePath(
                    $disk,
                    $directoryPrefix,
                    'tmp',
                    $extension
                );

                $optimizedStream = fopen($temporaryOutput, 'rb');

                if ($optimizedStream === false) {
                    throw new \RuntimeException(
                        'Não foi possível abrir o arquivo otimizado.'
                    );
                }

                try {
                    if (! $disk->put($stagedPath, $optimizedStream)) {
                        throw new \RuntimeException(
                            'Não foi possível gravar o arquivo temporário otimizado.'
                        );
                    }

                    $stagedWritten = true;
                } finally {
                    fclose($optimizedStream);
                }

                /*
                 * Valida o conteúdo efetivamente gravado no disco.
                 */
                if (
                    ! $disk->exists($stagedPath)
                    || (int) $disk->size($stagedPath) !== $optimizedSize
                ) {
                    throw new \RuntimeException(
                        'O arquivo otimizado não foi gravado corretamente.'
                    );
                }

                $optimizedHash = $this->hashStoredFile(
                    $disk,
                    $stagedPath,
                    $hashAlgorithm
                );

                /*
                 * Se já existir um destino com o UUID definitivo,
                 * ele também será preservado temporariamente.
                 *
                 * Isso permite recuperar os arquivos anteriores caso
                 * a substituição ou a atualização do banco falhe.
                 */
                if (
                    $newPath !== $oldPath
                    && $disk->exists($newPath)
                ) {
                    $backupDestinationPath = $this->uniqueStoragePath(
                        $disk,
                        $directoryPrefix,
                        'backup',
                        $extension
                    );

                    if (! $disk->move(
                        $newPath,
                        $backupDestinationPath
                    )) {
                        throw new \RuntimeException(
                            'Não foi possível preservar o arquivo existente no destino.'
                        );
                    }

                    $destinationMoved = true;
                }

                /*
                 * Move o original para backup somente depois de validar
                 * completamente o arquivo otimizado.
                 */
                $backupOldPath = $this->uniqueStoragePath(
                    $disk,
                    $directoryPrefix,
                    'backup',
                    pathinfo($oldPath, PATHINFO_EXTENSION)
                );

                if (! $disk->move($oldPath, $backupOldPath)) {
                    throw new \RuntimeException(
                        'Não foi possível preservar o arquivo original antes da substituição.'
                    );
                }

                $oldMoved = true;

                /*
                 * O arquivo temporário passa a ocupar o caminho definitivo:
                 * {diretório}/{uuid}.{extensão}
                 */
                if (! $disk->move($stagedPath, $newPath)) {
                    throw new \RuntimeException(
                        'Não foi possível mover o arquivo otimizado para o caminho definitivo.'
                    );
                }

                $stagedWritten = false;
                $finalWritten = true;

                /*
                 * Confirma que o arquivo definitivo está íntegro antes
                 * de apontar o registro para ele.
                 */
                if (
                    ! $disk->exists($newPath)
                    || (int) $disk->size($newPath) !== $optimizedSize
                ) {
                    throw new \RuntimeException(
                        'O arquivo definitivo não passou na validação de tamanho.'
                    );
                }

                $finalHash = $this->hashStoredFile(
                    $disk,
                    $newPath,
                    $hashAlgorithm
                );

                if (! hash_equals($optimizedHash, $finalHash)) {
                    throw new \RuntimeException(
                        'O hash do arquivo definitivo não corresponde ao arquivo validado.'
                    );
                }

                $attributes = [
                    'path' => $newPath,
                    'extension' => $extension,
                    'mime_type' => $optimized['mime_type'],
                    'size' => $optimizedSize,
                    'hash' => $finalHash,
                ];

                if ($isPdf) {
                    $metadata = $file->metadata ?? [];

                    data_set($metadata, 'optimization.pdf', [
                        'status' => 'completed',
                        'version' => self::PDF_OPTIMIZATION_VERSION,
                        'hash' => $finalHash,
                        'optimized_at' => now()->toISOString(),
                        'size_before' => $originalSize,
                        'size_after' => $optimizedSize,
                    ]);

                    $attributes['metadata'] = $metadata;
                }

                /*
                 * Só atualiza o registro depois de o arquivo definitivo
                 * ter sido gravado e validado.
                 */
                $file->update($attributes);

                $databaseUpdated = true;

                /*
                 * A atualização foi concluída. Agora remove os backups.
                 * Uma falha na limpeza não desfaz o arquivo já validado.
                 */
                foreach ([
                    $backupOldPath,
                    $backupDestinationPath,
                ] as $backupPath) {
                    if ($backupPath === null) {
                        continue;
                    }

                    try {
                        if (
                            $disk->exists($backupPath)
                            && ! $disk->delete($backupPath)
                        ) {
                            $this->warn(
                                "Não foi possível remover o backup: {$backupPath}"
                            );
                        }
                    } catch (\Throwable $exception) {
                        $this->warn(
                            "Não foi possível remover o backup {$backupPath}: "
                            . $exception->getMessage()
                        );
                    }
                }

                $difference = $originalSize - $optimizedSize;

                $percentage = $originalSize > 0
                    ? ($difference / $originalSize) * 100
                    : 0;

                $this->info(
                    "Otimizado com sucesso: {$oldPath} → {$newPath}"
                );

                $this->line(sprintf(
                    'Tamanho: %s → %s (economia de %s bytes, %.2f%%)',
                    number_format($originalSize, 0, ',', '.'),
                    number_format($optimizedSize, 0, ',', '.'),
                    number_format($difference, 0, ',', '.'),
                    $percentage
                ));

                $processed++;
            } catch (\Throwable $exception) {
                /*
                 * Antes de o banco ser atualizado, tenta restaurar
                 * o estado anterior do armazenamento.
                 */
                if (! $databaseUpdated) {
                    try {
                        if (
                            $finalWritten
                            && $newPath !== null
                            && $disk->exists($newPath)
                        ) {
                            $disk->delete($newPath);
                        }

                        if (
                            $oldMoved
                            && $backupOldPath !== null
                            && $disk->exists($backupOldPath)
                        ) {
                            if (! $disk->move($backupOldPath, $oldPath)) {
                                throw new \RuntimeException(
                                    "Não foi possível restaurar o original: {$oldPath}"
                                );
                            }

                            $oldMoved = false;
                        }

                        if (
                            $destinationMoved
                            && $backupDestinationPath !== null
                            && $newPath !== null
                            && $disk->exists($backupDestinationPath)
                        ) {
                            if (! $disk->move(
                                $backupDestinationPath,
                                $newPath
                            )) {
                                throw new \RuntimeException(
                                    "Não foi possível restaurar o arquivo anterior do destino: {$newPath}"
                                );
                            }

                            $destinationMoved = false;
                        }
                    } catch (\Throwable $rollbackException) {
                        $this->error(
                            'ATENÇÃO: houve uma falha ao restaurar os arquivos: '
                            . $rollbackException->getMessage()
                        );
                    }
                }

                $this->error('Erro: ' . $exception->getMessage());
                $failed++;
            } finally {
                /*
                 * Remove eventuais arquivos temporários que ainda existam.
                 */
                foreach ([
                    $stagedWritten ? $stagedPath : null,
                ] as $storageTemporaryPath) {
                    if ($storageTemporaryPath === null) {
                        continue;
                    }

                    try {
                        if ($disk->exists($storageTemporaryPath)) {
                            $disk->delete($storageTemporaryPath);
                        }
                    } catch (\Throwable) {
                        // Não substitui o erro original por falha de limpeza.
                    }
                }

                /*
                 * Backups restantes são mantidos se a restauração falhou,
                 * evitando apagar a única cópia recuperável.
                 */
                if (
                    $temporaryInput !== null
                    && file_exists($temporaryInput)
                ) {
                    @unlink($temporaryInput);
                }

                if (
                    $temporaryOutput !== null
                    && is_file($temporaryOutput)
                ) {
                    @unlink($temporaryOutput);
                }
            }
        }

        $this->newLine();
        $this->info("Processados: {$processed}");
        $this->comment("Ignorados: {$skipped}");

        if ($failed > 0) {
            $this->error("Falhas: {$failed}");

            return self::FAILURE;
        }

        $this->info('Processamento concluído.');

        return self::SUCCESS;
    }

    /**
     * Gera um caminho temporário exclusivo no mesmo diretório do arquivo.
     */
    private function uniqueStoragePath(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $directoryPrefix,
        string $prefix,
        string $extension,
    ): string {
        do {
            $path = $directoryPrefix
                . '.'
                . $prefix
                . '-'
                . Str::uuid()
                . '.'
                . $extension;
        } while ($disk->exists($path));

        return $path;
    }

    /**
     * Calcula o hash do conteúdo sem carregar todo o arquivo na memória.
     */
    private function hashStoredFile(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $path,
        string $algorithm,
    ): string {
        $stream = $disk->readStream($path);

        if ($stream === false) {
            throw new \RuntimeException(
                "Não foi possível ler o arquivo para calcular o hash: {$path}"
            );
        }

        try {
            $context = hash_init($algorithm);

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
     * Atualiza os metadados de PDF sem apagar os demais metadados.
     */
    private function savePdfOptimizationMetadata(
        File $file,
        array $optimization,
    ): void {
        $metadata = $file->metadata ?? [];

        data_set($metadata, 'optimization.pdf', $optimization);

        $file->update([
            'metadata' => $metadata,
        ]);
    }
}