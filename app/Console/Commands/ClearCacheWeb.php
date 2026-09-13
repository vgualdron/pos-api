<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClearCacheWeb extends Command
{
    protected $signature = 'web:clear-cache';
    protected $description = 'Clear config, route and app cache from web';

    public function handle()
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('cache:clear');

        $this->info('Cache cleared successfully!');
    }
}
