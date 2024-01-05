<?php

namespace Uxmal\Devtools\Command\Uxmaltech;

use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Uxmal\Devtools\Traits\GeneralUtils;

class AddBackOfficeUICommand extends Command
{
    use GeneralUtils;

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
     */
    #[NoReturn]
    public function handle(): void
    {
        $this->addBackofficeUI();
    }

    /**
     * Build the base docker image.
     */
    #[NoReturn]
    protected function addBackofficeUI(): void
    {
        $this->info('Add Backoffice UI...');
    }
}
