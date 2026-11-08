<?php

use App\Support\TrustedHostRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lms:trusted-host {host? : Exact hostname or URL} {--remove : Remove the hostname} {--list : List effective trusted hosts}', function () {
    /** @var TrustedHostRegistry $registry */
    $registry = app(TrustedHostRegistry::class);

    if ($this->option('list')) {
        $this->table(['Trusted host'], array_map(
            static fn (string $host): array => [$host],
            $registry->hosts()
        ));

        return Command::SUCCESS;
    }

    $host = $this->argument('host');

    if (! is_string($host) || trim($host) === '') {
        $this->error('Berikan hostname exact atau gunakan --list.');

        return Command::FAILURE;
    }

    try {
        if ($this->option('remove')) {
            $normalized = $registry->remove($host);
            $this->info("Trusted host dihapus: {$normalized}");
        } else {
            $normalized = $registry->register($host);
            $this->info("Trusted host terdaftar: {$normalized}");
        }
    } catch (InvalidArgumentException|RuntimeException $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    return Command::SUCCESS;
})->purpose('Register an exact LMS hostname without changing source code');
