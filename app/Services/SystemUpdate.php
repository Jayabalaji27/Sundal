<?php

namespace App\Services;

use App\Actions\ActionException;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * The deploy steps for a server without a terminal (shared hosting), run by
 * the superadmin from the System update page after uploading a new version:
 * the pending migrations, the permission and role seeders, the permission
 * cache and the other caches. The same steps as the DEV-LOG deploy notes.
 *
 * Never the full db:seed: on a live site it would bring back the default
 * accounts and reset plans, landing page and email templates.
 */
class SystemUpdate
{
    /** Migrations not run yet, by name, oldest first. */
    public function pending(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }

    /** public/storage: "link" (right), "folder" (uploads are not shown) or "missing". */
    public function storageLink(): string
    {
        $path = public_path('storage');

        return match (true) {
            // readlink also reads a Windows junction, which is_link does not see.
            is_link($path) || @readlink($path) !== false => 'link',
            is_dir($path) => 'folder',
            default => 'missing',
        };
    }

    /** What the last run did, or null. */
    public function lastRun(): ?array
    {
        $path = $this->resultPath();

        return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    }

    /**
     * Runs every step in order and stops at the first that fails.
     *
     * @return array{at: string, ok: bool, steps: array<int, array{label: string, ok: bool, output: string}>, pending_after: array}
     */
    public function run(int $userId): array
    {
        $lock = fopen(storage_path('framework/system-update.lock'), 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new ActionException(__('An update is already running. Wait a minute, then reload this page.'));
        }

        // A closed browser tab or a short time limit must not stop a migration halfway.
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            $planned = $this->steps();
            $steps = [];
            foreach ($planned as [$label, $command, $arguments]) {
                $output = new BufferedOutput;
                try {
                    $ok = Artisan::call($command, $arguments, $output) === 0;
                    $text = $output->fetch();
                } catch (Throwable $e) {
                    report($e);
                    $ok = false;
                    $text = $output->fetch() . "\n" . $e->getMessage();
                }
                $steps[] = ['label' => $label, 'ok' => $ok, 'output' => trim($text)];
                if (!$ok) {
                    break;
                }
            }

            $result = [
                'at' => now()->toDateTimeString(),
                'ok' => count($steps) === count($planned) && collect($steps)->every('ok'),
                'steps' => $steps,
                'pending_after' => $this->pending(),
            ];
            file_put_contents($this->resultPath(), json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::info('System update run', ['user_id' => $userId, 'ok' => $result['ok'], 'steps' => array_column($steps, 'ok', 'label')]);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<int, array{0: string, 1: string, 2: array}> label, command, arguments */
    private function steps(): array
    {
        return array_values(array_filter([
            [__('Database changes'), 'migrate', ['--force' => true]],
            // Both only add or update rows, so running them again is safe.
            [__('Permissions'), 'db:seed', ['--class' => PermissionSeeder::class, '--force' => true]],
            [__('Roles'), 'db:seed', ['--class' => RoleSeeder::class, '--force' => true]],
            [__('Permission cache'), 'permission:cache-reset', []],
            [__('Caches'), 'optimize:clear', []],
            // Last: some hosts turn symlinks off, and that must not stop the rest.
            $this->storageLink() === 'missing' ? [__('Storage link'), 'storage:link', []] : null,
        ]));
    }

    private function resultPath(): string
    {
        return storage_path('framework/system-update-last.json');
    }
}
