<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ProcessScheduledPosts;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register the ProcessScheduledPosts command
Artisan::command('posts:process', function () {
    $command = new ProcessScheduledPosts();
    $command->handle();
})->purpose('Process scheduled and queue posts');
