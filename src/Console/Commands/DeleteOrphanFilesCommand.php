<?php

namespace LucasBarros\LaravelFileManager\Console\Commands;

use Illuminate\Console\Command;
use LucasBarros\LaravelFileManager\Models\File;

class DeleteOrphanFilesCommand extends Command
{
    protected $signature = 'file-manager:delete-orphan-files
                            {--dry-run : Apenas lista os arquivos órfãos, sem excluí-los}
                            {--force : Executa a exclusão sem solicitar confirmação}';

    protected $description = 'Lista ou exclui definitivamente os registros de arquivos órfãos';

    public function handle(): int
    {
        $query = File::withTrashed()
            ->whereDoesntHave('fileables');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nenhum arquivo órfão encontrado.');

            return self::SUCCESS;
        }

        $this->info("Arquivos órfãos encontrados: {$total}");

        if ($this->option('dry-run')) {
            $this->warn('Modo dry-run: nenhum registro ou arquivo físico será excluído.');

            $this->displayOrphans($query);

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Deseja excluir definitivamente os {$total} registros órfãos?"
        )) {
            $this->info('Operação cancelada.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;

        $query->chunkById(200, function ($files) use (&$deleted, &$skipped) {
            foreach ($files as $file) {
                // Revalida antes da exclusão para evitar apagar um arquivo
                // que tenha recebido uma nova relação durante a execução.
                if (! $file->isOrphan()) {
                    $skipped++;

                    continue;
                }

                if ($file->forceDelete()) {
                    $deleted++;
                }
            }
        });

        $this->newLine();
        $this->info("Registros excluídos: {$deleted}");

        if ($skipped > 0) {
            $this->warn("Registros ignorados porque deixaram de ser órfãos: {$skipped}");
        }

        return self::SUCCESS;
    }

    private function displayOrphans($query): void
    {
        $this->table(
            ['ID', 'Disco', 'Caminho', 'Excluído logicamente'],
            $query->orderBy('id')->get()->map(fn (File $file) => [
                $file->getKey(),
                $file->disk,
                $file->path,
                $file->trashed() ? 'Sim' : 'Não',
            ])->all()
        );
    }
}