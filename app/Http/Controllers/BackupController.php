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

        $pgDumpPath = $this->resolvePgDumpPath();
        if ($pgDumpPath === null) {
            return back()->with(
                'error',
                'No se encontró pg_dump. Instala las herramientas de PostgreSQL o configura PG_DUMP_PATH con la ruta completa a pg_dump.exe.'
            );
        }
        $timestamp = now()->format('Ymd_His');
        $database = (string) ($dbConfig['database'] ?? '');
        $filename = "db_{$database}_{$timestamp}.backup";
        $fullPath = $backupDir.DIRECTORY_SEPARATOR.$filename;

        @set_time_limit(0);

        $process = new Process([
            $pgDumpPath,
            '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
            '-p', (string) ($dbConfig['port'] ?? '5432'),
            '-U', (string) ($dbConfig['username'] ?? ''),
            '-F', 'c',
            '--no-owner',
            '--no-acl',
            '-f', $fullPath,
            $database,
        ]);

        $process->setEnv([
            'PGPASSWORD' => (string) ($dbConfig['password'] ?? ''),
        ]);

        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            $fallback = [
                'success' => false,
                'exit_code' => null,
                'error_output' => '',
                'output' => '',
            ];
            $primaryOutput = trim($process->getErrorOutput()."\n".$process->getOutput());

            // Fallback for some Windows web SAPIs where direct process args can fail silently.
            if ($primaryOutput === '') {
                File::delete($fullPath);
                $fallback = $this->runPgDumpWithShell(
                    $pgDumpPath,
                    (string) ($dbConfig['host'] ?? '127.0.0.1'),
                    (string) ($dbConfig['port'] ?? '5432'),
                    (string) ($dbConfig['username'] ?? ''),
                    (string) ($dbConfig['password'] ?? ''),
                    $fullPath,
                    $database
                );

                if ($fallback['success'] === true && File::exists($fullPath) && File::size($fullPath) > 0) {
                    return back()
                        ->with('success', "Backup generado: {$filename}. La descarga comenzará automáticamente.")
                        ->with('download_backup', $filename);
                }
            }

            File::delete($fullPath);

            $errorLines = collect([
                $process->getErrorOutput(),
                $process->getOutput(),
                $fallback['error_output'] ?? '',
                $fallback['output'] ?? '',
            ])
                ->flatMap(fn (string $output): array => preg_split('/\R+/', trim($output), -1, PREG_SPLIT_NO_EMPTY) ?: [])
                ->map(fn (string $line): string => trim($this->sanitizeOutput($line)))
                ->filter()
                ->unique()
                ->values();
            $exitCode = $process->getExitCode();
            $fallbackExitCode = $fallback['exit_code'] ?? null;
            $combinedMessage = $errorLines->implode(' | ');
            $combinedMessage = $combinedMessage !== '' && ! preg_match('/^pg_dump:\s*error:\s*$/i', $combinedMessage)
                ? $combinedMessage
                : 'pg_dump no devolvió detalles. Revisa los permisos del usuario y la conexión del servidor PostgreSQL.';

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

        if (! File::exists($fullPath) || File::size($fullPath) === 0) {
            File::delete($fullPath);

            return back()->with('error', 'pg_dump terminó sin errores, pero no se creó un archivo de backup válido.');
        }

        return back()
            ->with('success', "Backup generado: {$filename}. La descarga comenzará automáticamente.")
            ->with('download_backup', $filename);
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

    private function resolvePgDumpPath(): ?string
    {
        $configuredPath = trim((string) config('database.pg_dump_path', ''), "\"' \t\n\r\0\x0B");
        if ($configuredPath !== '' && File::isFile($configuredPath)) {
            return $configuredPath;
        }

        $binaryNames = PHP_OS_FAMILY === 'Windows' ? ['pg_dump.exe', 'pg_dump'] : ['pg_dump'];
        $pathDirectories = explode(PATH_SEPARATOR, (string) (getenv('PATH') ?: ''));

        foreach ($pathDirectories as $directory) {
            $directory = trim($directory, "\" \t");
            if ($directory === '') {
                continue;
            }

            foreach ($binaryNames as $binaryName) {
                $candidate = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$binaryName;
                if (File::isFile($candidate)) {
                    return $candidate;
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $programFilesRoots = array_unique(array_filter([
                getenv('ProgramW6432') ?: null,
                getenv('ProgramFiles') ?: null,
                getenv('ProgramFiles(x86)') ?: null,
            ]));

            foreach ($programFilesRoots as $programFilesRoot) {
                foreach ([
                    rtrim(str_replace('\\', '/', $programFilesRoot), '/').'/PostgreSQL/*/bin/pg_dump.exe',
                    rtrim(str_replace('\\', '/', $programFilesRoot), '/').'/PostgreSQL/*/pgAdmin 4/runtime/pg_dump.exe',
                ] as $pattern) {
                    $candidates = glob($pattern) ?: [];
                    rsort($candidates, SORT_NATURAL | SORT_FLAG_CASE);

                    foreach ($candidates as $candidate) {
                        if (File::isFile($candidate)) {
                            return $candidate;
                        }
                    }
                }
            }
        } else {
            $candidates = array_merge(
                glob('/usr/lib/postgresql/*/bin/pg_dump') ?: [],
                ['/usr/bin/pg_dump', '/usr/local/bin/pg_dump']
            );
            rsort($candidates, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($candidates as $candidate) {
                if (File::isFile($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function runPgDumpWithShell(
        string $pgDumpPath,
        string $host,
        string $port,
        string $username,
        string $password,
        string $fullPath,
        string $database
    ): array {
        $cmd = sprintf(
            '"%s" -h "%s" -p "%s" -U "%s" -F c --no-owner --no-acl -f "%s" "%s"',
            str_replace('"', '\"', $pgDumpPath),
            str_replace('"', '\"', $host),
            str_replace('"', '\"', $port),
            str_replace('"', '\"', $username),
            str_replace('"', '\"', $fullPath),
            str_replace('"', '\"', $database)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setEnv([
            'PGPASSWORD' => $password,
        ]);
        $process->setTimeout(1800);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'error_output' => trim($process->getErrorOutput()),
            'output' => trim($process->getOutput()),
        ];
    }
}

