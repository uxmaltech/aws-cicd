<?php

namespace Uxmal\AwsCICD\Command;

use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Uxmal\AwsCICD\Traits\ProcessUtils;

class AddBackOfficeUICommand extends Command
{
    use ProcessUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'uxmaltech:add-backoffice-ui {--dev} {--version=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Backoffice UI';

    /**
     * Execute the console command.
     *
     * @return void
     */
    #[NoReturn] public function handle(): void
    {
        $this->addBackofficeUI();
    }

    /**
     * Build the base docker image.
     *
     * @return void
     */
    #[NoReturn]
    protected function addBackofficeUI(): void
    {
        $this->info('Add Backoffice UI...');
    }
}
