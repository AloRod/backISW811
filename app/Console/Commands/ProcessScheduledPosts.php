<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\HistoryController;

class ProcessScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:process {--type=all : Type of posts to process (scheduled, queue, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled and queue posts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $historyController = new HistoryController();

        $this->info('Starting post processing...');

        if ($type === 'all' || $type === 'scheduled') {
            $this->info('Processing scheduled posts...');
            $historyController->sendScheduledPosts();
        }

        if ($type === 'all' || $type === 'queue') {
            $this->info('Processing queue posts...');
            $historyController->sendQueuePosts();
        }

        $this->info('Post processing completed!');
    }
}
