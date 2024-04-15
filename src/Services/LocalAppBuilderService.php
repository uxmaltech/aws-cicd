<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Traits\GeneralUtils;
use Uxmal\Devtools\Interfaces\AppBuilderInterface;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class LocalAppBuilderService implements AppBuilderInterface
{


    use GeneralUtils;

    protected $validRepositories = [];

    function __construct()
    {
        $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
    }
    public function build(string $repository): void
    {
        $repositories = $this->validRepositories;
        if (!in_array($repository, array_keys($repositories))) {
            throw new \Exception('Repository not found in valid repositories.');
        }

        // Get the path of the repository
        $repository_path = $repositories[$repository];
        // Run the build process
        switch ($repository) {
            case 'uxmaltech/backoffice-ui-npm':
                // TODO: Run git pull
                $this->buildUiNpm($repository_path);
                break;
            case 'uxmaltech/backoffice-ui':
                // TODO: Run composer install/update for backoffice-ui
                $this->buildBackofficeUi($repository_path);
                break;
        }
    }

    // Build ui-npm project
    // @param string $repository_path
    // @return void
    // @throws \Exception
    private function buildUiNpm(string $repository_path): void
    {
        try {
            //Run composer install
            $npm_path = '/home/edgardo/./.nvm/versions/node/v20.11.1/bin/';
            $node_path = '/home/edgardo/./.nvm/versions/node/v20.11.1/bin/node';
            $this->runCmd([$npm_path . 'npm', '--prefix' . $repository_path, 'ci'], ['NODE_PATH' => $node_path]);
            $this->runCmd(
                [
                    'npm', '--prefix' . $repository_path, 'ci'
                ],
                [
                    'NPM_HOME' => $npm_path,
                    'NODE_HOME' => $node_path
                ]
            );
        } catch (\Exception $e) {

            Log::error('Error processing backoffice-ui', [
                'message' => $e->getMessage(),
                //'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception($e->getMessage());
        }
    }

    // Build backoffice-ui project
    // @param string $repository_path
    // @return void
    // @throws \Exception
    private function buildBackofficeUi(string $repository_path): void
    {
        try {
            // Run composer install
            $this->runCmd(['git', '-C', $repository_path, 'pull',  'origin']);
            $this->runCmd(['composer', 'install', '--no-interaction', '--optimize-autoloader', '--working-dir=' . $repository_path], ['COMPOSER_HOME' => '$HOME/config/.composer']);
        } catch (\Exception $e) {

            Log::error('Error processing backoffice-ui', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('error processing backoffice-ui');
        }
    }
}
