<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Wraps `pail` so `composer dev` doesn't crash on Windows, where the pcntl
 * extension Pail requires isn't available (it's a Unix-only build, not a
 * php.ini toggle). Falls through to the real Pail command everywhere else.
 */
class TailLogs extends Command
{
    protected $signature = 'logs:tail {--timeout=0}';

    protected $description = 'Tail application logs (Pail), skipped gracefully where pcntl is unavailable';

    public function handle(): int
    {
        if (! extension_loaded('pcntl')) {
            $this->warn('Log tailing (Pail) requires the pcntl extension, which is not available on this PHP build (e.g. Windows).');
            $this->line('Server, queue, and Vite are unaffected. Tail storage/logs/laravel.log directly if you need log output.');

            return self::SUCCESS;
        }

        return $this->call('pail', ['--timeout' => $this->option('timeout')]);
    }
}
