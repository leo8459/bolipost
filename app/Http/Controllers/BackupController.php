<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupController extends Controller
{
    public function index(): View
    {
        $backupDir = storage_path('app/backups');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $files = collect(File::files($backupDir))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(function ($file) {
                $name = $file->getFilename();

                return [
                    'name' => $name,
                    'size' => $this->formatBytes($file->getSize()),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    'type' => str_starts_with($name, 'db_') ? 'Base de Datos' : 'Sistema',
                ];
            })
            ->values();

        return view('backups.index', compact('files'));
    }

    public function backupDatabase(Request $request): RedirectResponse
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        if (! is_array($dbConfig) || ($dbConfig['driver'] ?? null) !== 'pgsql') {
            return back()->with('error', 'El respaldo automático solo está habilitado para PostgreSQL.');
        }

        $pgDumpPath = env('PG_DUMP_PATH', 'pg_dump');
        $timestamp = now()->format('Ymd_His');
        $database = (string) ($dbConfig['database'] ?? '');
        $filename = "db_{$database}_{$timestamp}.sql";
        $fullPath = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $process = new Process([
            $pgDumpPath,
            '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
            '-p', (string) ($dbConfig['port'] ?? '5432'),
            '-U', (string) ($dbConfig['username'] ?? ''),
            '-F', 'p',
            '-f', $fullPath,
            $database,
        ]);

        $process->setEnv([
            'PGPASSWORD' => (string) ($dbConfig['password'] ?? ''),
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            return back()->with('error', 'No se pudo generar el respaldo de la base de datos. '.$errorOutput);
        }

        return back()->with('success', "Respaldo de base de datos generado: {$filename}");
    }

    public function backupSystem(): RedirectResponse
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Ymd_His');
        $filename = "system_{$timestamp}.zip";
        $zipPath = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo crear el archivo ZIP del sistema.');
        }

        $basePath = base_path();
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $fullPath = $item->getPathname();
            $relativePath = ltrim(str_replace($basePath, '', $fullPath), DIRECTORY_SEPARATOR);

            if ($relativePath === '' || ! $this->shouldIncludePath($relativePath)) {
                continue;
            }

            if ($item->isDir()) {
                $zip->addEmptyDir(str_replace('\\', '/', $relativePath));
                continue;
            }

            if ($item->isFile() && $item->isReadable()) {
                $zip->addFile($fullPath, str_replace('\\', '/', $relativePath));
            }
        }

        $zip->close();

        return back()->with('success', "Respaldo del sistema generado: {$filename}");
    }

    public function download(string $file)
    {
        $safeFile = basename($file);
        $fullPath = storage_path('app/backups'.DIRECTORY_SEPARATOR.$safeFile);

        abort_unless(File::exists($fullPath), 404, 'Respaldo no encontrado.');

        return response()->download($fullPath);
    }

    private function shouldIncludePath(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        $excluded = [
            '.git/',
            'node_modules/',
            'vendor/',
            'storage/logs/',
            'storage/framework/cache/',
            'storage/framework/sessions/',
            'storage/framework/views/',
            'storage/app/backups/',
        ];

        foreach ($excluded as $path) {
            if (str_starts_with($normalized, $path)) {
                return false;
            }
        }

        return true;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
