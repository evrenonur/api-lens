<?php

namespace ApiLens\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'api-lens:install';

    protected $description = 'Install API Lens configuration and assets';

    public function handle(): int
    {
        $this->info('🔧 Installing API Lens...');
        $this->newLine();

        // 1. Publish config
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'api-lens-config',
            '--force' => true,
        ]);

        // 2. Publish assets
        $this->info('Publishing frontend assets...');
        $this->call('vendor:publish', [
            '--tag' => 'api-lens-assets',
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('✅ API Lens installed successfully!');
        $this->newLine();

        $url = config('app.url', 'http://localhost') . '/' . config('api-lens.url', 'api-lens');
        $this->info("📖 Access your API documentation at: {$url}");
        $this->info("⚙️  Edit configuration at: config/api-lens.php");
        $this->newLine();

        $this->info('Quick start:');
        $this->info('  1. Make sure APP_DEBUG=true in your .env');
        $this->info('  2. Visit ' . config('api-lens.url', 'api-lens') . ' in your browser');
        $this->info('  3. Export docs: php artisan api-lens:export');
        $this->newLine();

        return self::SUCCESS;
    }
}
