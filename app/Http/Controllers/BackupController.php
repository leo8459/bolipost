<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
            return back()->with('error', 'El respaldo automatico solo esta habilitado para PostgreSQL.');
        }

        $pgDumpPath = env('PG_DUMP_PATH', 'pg_dump');
        $timestamp = now()->format('Ymd_His');
        $database = (string) ($dbConfig['database'] ?? '');
        $filename = "db_{$database}_{$timestamp}.sql";
        $fullPath = $backupDir.DIRECTORY_SEPARATOR.$filename;
        $restrictKey = $this->buildRestrictKey();

        $process = new Process([
            $pgDumpPath,
            '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
            '-p', (string) ($dbConfig['port'] ?? '5432'),
            '-U', (string) ($dbConfig['username'] ?? ''),
            '-F', 'p',
            '--restrict-key='.$restrictKey,
            '-f', $fullPath,
            $database,
        ]);

        $process->setEnv([
            'PGPASSWORD' => (string) ($dbConfig['password'] ?? ''),
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            // Fallback for some Windows web SAPIs where direct process args can fail silently.
            $fallback = $this->runPgDumpWithShell(
                $pgDumpPath,
                (string) ($dbConfig['host'] ?? '127.0.0.1'),
                (string) ($dbConfig['port'] ?? '5432'),
                (string) ($dbConfig['username'] ?? ''),
                (string) ($dbConfig['password'] ?? ''),
                $restrictKey,
                $fullPath,
                $database
            );

            if ($fallback['success'] === true) {
                return back()->with('success', "Respaldo de base de datos generado: {$filename}");
            }

            $errorOutputRaw = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            $errorOutput = $this->sanitizeOutput($errorOutputRaw);
            $fallbackOutput = $this->sanitizeOutput((string) ($fallback['output'] ?? ''));
            $exitCode = $process->getExitCode();
            $fallbackExitCode = $fallback['exit_code'] ?? null;
            $combinedMessage = trim($errorOutput.' '.$fallbackOutput);
            $combinedMessage = $combinedMessage !== '' ? $combinedMessage : 'Sin detalles de error.';

            Log::error('Error al generar backup de base de datos', [
                'message' => $combinedMessage,
                'exit_code' => $exitCode,
                'fallback_exit_code' => $fallbackExitCode,
                'pg_dump_path' => $pgDumpPath,
                'pg_dump_exists' => File::exists($pgDumpPath),
                'target_file' => $fullPath,
            ]);

            return back()->with(
                'error',
                'No se pudo generar el respaldo de la base de datos (codigo '.
                ($fallbackExitCode ?? $exitCode ?? 'N/A').
                '). '.$combinedMessage
            );
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

    private function sanitizeOutput(string $value): string
    {
        if ($value === '') {
            return 'Sin detalles de error.';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    private function buildRestrictKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid((string) mt_rand(), true));
        }
    }

    private function runPgDumpWithShell(
        string $pgDumpPath,
        string $host,
        string $port,
        string $username,
        string $password,
        string $restrictKey,
        string $fullPath,
        string $database
    ): array {
        $cmd = sprintf(
            '"%s" -h "%s" -p "%s" -U "%s" -F p --restrict-key="%s" -f "%s" "%s"',
            str_replace('"', '\"', $pgDumpPath),
            str_replace('"', '\"', $host),
            str_replace('"', '\"', $port),
            str_replace('"', '\"', $username),
            str_replace('"', '\"', $restrictKey),
            str_replace('"', '\"', $fullPath),
            str_replace('"', '\"', $database)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setEnv([
            'PGPASSWORD' => $password,
        ]);
        $process->setTimeout(300);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => trim($process->getErrorOutput().' '.$process->getOutput()),
        ];
    }
}

