<?php

namespace App\Console\Commands;

use App\Support\HomeShelves;
use Illuminate\Console\Command;

class RefreshHomeShelves extends Command
{
    protected $signature = 'home:shelves-refresh';

    protected $description = 'Rebuild the saved copy of the app home page strips (most visited / best offers / newest, stores and medical).';

    public function handle(): int
    {
        $started = microtime(true);
        $locales = HomeShelves::refresh();

        $this->info('Home shelves rebuilt for ['.implode(', ', $locales).'] in '.round(microtime(true) - $started, 2).'s.');

        return self::SUCCESS;
    }
}
