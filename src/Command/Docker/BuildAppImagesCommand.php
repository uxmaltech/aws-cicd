<?php

namespace Uxmal\Devtools\Command\Docker;

use Illuminate\Console\Command;
use Random\RandomException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildAppImagesCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     *
     * --build
     * --run
     * --check
     * ? --push
     * ? --pull
     * ? --tag=latest
     * ? --tag=dev
     * ? --tag=prod
     * ? --tag=staging
     * ? --tag=testing
     * ? --tag=qa
     * ? --push-ecr=tag
     */
    protected $signature = 'docker:build-app-images 
                        {--release= : The release tag to use for the images}';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    public function handle(): void
    {
        try {
            if ($this->checkEnvironment() === false) {
                exit(1);
            }
        } catch (ProcessFailedException $exception) {
            $this->warn('An error occurred: ' . $exception->getMessage());
        } catch (RandomException $e) {
            $this->warn('An error occurred: ' . $e->getMessage());
        }

        system('clear');
        $this->composeBuild();
    }

    private function composeBuild(): void
    {
        $uxmalEnvReplacement = $this->arrayToEnvNotation(config('uxmaltech'), 'UXMALTECH_');

        $dockerizedImages = config('dockerized.images');

        $this->line('Building the docker images for ' . config('uxmaltech.name') . ' ...');

        $release = $this->option('release') ?? 'latest';

        /** @var Prepare Build Directory * */
        $buildDir = $this->laravel->basePath('../uxmaltech-build') . '/' . $release . '/BUILD';

        $this->initDir($buildDir);
        $this->copyLaravelApp($buildDir);
        $this->initLaravelApp($buildDir);

        foreach (array_keys($dockerizedImages) as $imageToBuild) {
            switch ($imageToBuild) {
                case 'apache-php':
                    $this->initDir($buildDir . '/conf');
                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/apache/httpd.conf.stub > $buildDir/conf/httpd.conf";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement);

                    $phpIniVars = [
                        'PHP_MEMORY_LIMIT' => '128M',
                        'PHP_EXPOSE_PHP' => 'On',
                        'PHP_SESSION_GC_MAXLIFETIME' => '1440',
                    ];

                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/apache/php.ini.stub > $buildDir/conf/php.ini";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $phpIniVars);

                    $this->initDir($buildDir . '/bash');

                    $cmd = 'cp ' . __DIR__ . "/app-images/apache/bash/entrypoint.sh $buildDir/bash/entrypoint.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/apache/bash/envsubst.sh $buildDir/bash/envsubst.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/apache/default-env-stub $buildDir/default-env-stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $dockerizedEnvReplacement = $this->arrayToEnvNotation(config('dockerized.images.apache-php'), 'DOCKERIZED_APACHE_PHP_');
                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/apache/dockerfile.stub > $buildDir/Dockerfile";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $dockerizedEnvReplacement);

                    $imageName = $this->buildImage($buildDir, $release, 'apache-php');
                    $this->replaceConfigKey('images.apache-php.app-image', $imageName, true, config_path('dockerized.php'));

                    $this->resetBuildDir($buildDir);
                    break;
                case 'nginx':
                    $this->initDir($buildDir . '/conf');

                    $nginxVars = $this->arrayToEnvNotation(config('dockerized.images.nginx'), 'DOCKERIZED_NGINX_');
                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/nginx/nginx.conf.stub > $buildDir/conf/nginx.conf";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $nginxVars);

                    $this->initDir($buildDir . '/bash');

                    $cmd = 'cp ' . __DIR__ . "/app-images/nginx/bash/entrypoint.sh $buildDir/bash/entrypoint.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/nginx/bash/envsubst.sh $buildDir/bash/envsubst.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/nginx/dockerfile.stub > $buildDir/Dockerfile";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $nginxVars);

                    $imageName = $this->buildImage($buildDir, $release, 'nginx');
                    $this->replaceConfigKey('images.nginx.app-image', $imageName, true, config_path('dockerized.php'));

                    $this->resetBuildDir($buildDir);
                    break;
                case 'php-fpm':
                    $this->initDir($buildDir . '/conf');

                    $phpFpmVars = $this->arrayToEnvNotation(config('dockerized.images.php-fpm'), 'DOCKERIZED_PHP_FPM_');

                    $this->initDir($buildDir . '/bash');

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-fpm/bash/entrypoint.sh $buildDir/bash/entrypoint.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-fpm/bash/envsubst.sh $buildDir/bash/envsubst.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-fpm/default-php.ini.stub $buildDir/conf/default-php.ini.stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-fpm/php-fpm.conf.stub $buildDir/conf/php-fpm.conf.stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-fpm/www.conf.stub $buildDir/conf/www.conf.stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/php-fpm/dockerfile.stub > $buildDir/Dockerfile";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $phpFpmVars);

                    $imageName = $this->buildImage($buildDir, $release, 'php-fpm');
                    $this->replaceConfigKey('images.php-fpm.app-image', $imageName, true, config_path('dockerized.php'));

                    $this->resetBuildDir($buildDir);
                    break;
                case 'php-octane':
                    $buildDirOctane = $this->laravel->basePath('../uxmaltech-build') . '/' . $release . '/BUILD_OCTANE';
                    $this->initDir($buildDirOctane);
                    $this->copyLaravelApp($buildDirOctane);
                    $this->initLaravelAppOctane($buildDirOctane);

                    $this->initDir($buildDirOctane . '/conf');

                    $phpOctaneVars = $this->arrayToEnvNotation(config('dockerized.images.php-octane'), 'DOCKERIZED_PHP_OCTANE_');

                    $this->initDir($buildDirOctane . '/bash');

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-octane/bash/entrypoint.sh $buildDirOctane/bash/entrypoint.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-octane/bash/envsubst.sh $buildDirOctane/bash/envsubst.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-octane/default-php.ini.stub $buildDirOctane/conf/default-php.ini.stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/php-octane/dockerfile.stub > $buildDirOctane/Dockerfile";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $phpOctaneVars);

                    $imageName = $this->buildImage($buildDirOctane, $release, 'php-octane');
                    $this->replaceConfigKey('images.php-octane.app-image', $imageName, true, config_path('dockerized.php'));

                    $this->runProcess("rm -rf $buildDirOctane");
                    break;
                case 'php-cli':
                    $this->initDir($buildDir . '/conf');

                    $phpCliVars = $this->arrayToEnvNotation(config('dockerized.images.php-cli'), 'DOCKERIZED_PHP_CLI_');

                    $this->initDir($buildDir . '/bash');

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-cli/bash/entrypoint.sh $buildDir/bash/entrypoint.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-cli/bash/envsubst.sh $buildDir/bash/envsubst.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp ' . __DIR__ . "/app-images/php-cli/default-php.ini.stub $buildDir/conf/default-php.ini.stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    if (array_key_exists('DOCKERIZED_PHP_CLI_EXPOSED_PORT', $phpCliVars)) {
                        $phpCliVars['DOCKERIZED_PHP_CLI_EXPOSED_PORT'] = 'EXPOSE ' . $phpCliVars['DOCKERIZED_PHP_CLI_EXPOSED_PORT'];
                    }

                    $cmd = 'envsubst < ' . __DIR__ . "/app-images/php-cli/dockerfile.stub > $buildDir/Dockerfile";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $phpCliVars);

                    $imageName = $this->buildImage($buildDir, $release, 'php-cli');
                    $this->replaceConfigKey('images.php-cli.app-image', $imageName, true, config_path('dockerized.php'));
                    break;
            }
        }

        $this->runCmd(['bash', '-c', "rm -fr $buildDir"]);
        exit(0);
    }

    public function arrayToEnvNotation($array, $prefix = ''): array
    {
        $results = ['DOLLAR' => '$'];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, $this->arrayToEnvNotation($value, $prefix . $key . '_'));
            } else {
                $results[str_replace(['-'], ['_'], strtoupper($prefix . $key))] = str_replace(['/tcp'], [''], $value);
            }
        }

        return $results;
    }

    public function arrayToDotNotation($array, $prefix = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, $this->arrayToDotNotation($value, $prefix . $key . '.'));
            } else {
                $results['{' . $prefix . $key . '}'] = $value;
            }
        }

        return $results;
    }

    public function resetBuildDir($directory): void
    {
        $this->runProcess("rm -rf $directory/conf");
        $this->runProcess("rm -rf $directory/bash");
        $this->runProcess("rm -rf $directory/Dockerfile");
        $this->runProcess("rm -rf $directory/default-env-stub");
    }

    public function initDir($directory): void
    {
        if (is_dir($directory)) {
            $this->runProcess("rm -rf $directory");
            $this->line('Removing old build directory ' . $directory . '        ' . "\t\t" . '[<comment>OK</comment>]');
        }

        if (mkdir($directory, 0777, true)) {
            $this->line('Build directory created ' . $directory . '        ' . "\t\t" . '[<comment>OK</comment>]');
        } else {
            $this->error('Build directory could not be created.');
            exit(1);
        }
    }

    public function copyLaravelApp($directory): void
    {
        $appBuildDir = $directory . '/laravelApp';
        $this->initDir($appBuildDir);
        $this->line('Copying Laravel Application to build directory ' . $appBuildDir . '        ' . "\t\t" . '[<comment>OK</comment>]');
        $this->output->write('Copying files to build directory...' . "\t\t");

        if (!is_dir($this->laravel->basePath('.git'))) { // Si no existe el directorio .git, se copian los archivos con rsync
            $cmd = sprintf('rsync -av %s %s', escapeshellarg($this->laravel->basePath() . '/'), escapeshellarg($appBuildDir . '/'));
        } elseif ($this->devMode) { // Si existe el directorio .git y estamos en devMode, se copian los archivos con git ls-files
            $cmd = "git ls-files -z | tar --null -T - -cz | tar -xz -C $appBuildDir";
            //$cmd = sprintf('rsync -av %s %s', escapeshellarg($this->laravel->basePath().'/'), escapeshellarg($appBuildDir.'/'));
        } else { // Si existe el directorio .git y NO estamos en devMode, se copian los archivos con git archive (current branch)
            $cmd = "git archive $this->gitCommit | tar -x -C $appBuildDir";
        }
        $this->runCmd(['bash', '-c', $cmd]);

        if ($this->devMode) {
            // Copy the .env file to the build directory
            $cmd = 'cp ' . $this->laravel->basePath('.env') . " $directory/laravelApp/.env";
            $this->runCmd(['bash', '-c', $cmd]);
        }

        $this->line('[<comment>OK</comment>]');
    }

    public function initLaravelAppOctane($directory): void
    {
        $this->checkProtectedPackages();
        if (!empty($this->pathRepositoriesToCopy)) {
            foreach ($this->pathRepositoriesToCopy as $pathRepositoryToCopy) {
                $this->line("Copying $pathRepositoryToCopy to $directory/laravelApp/$pathRepositoryToCopy\t\t" . '[<comment>OK</comment>]');
                $this->runCmd(['bash', '-c', "cp -r $pathRepositoryToCopy $directory/laravelApp/" . $pathRepositoryToCopy]);
            }
        }

        $this->runComposerCmd(['require', 'laravel/octane', '--no-update'], $directory . '/laravelApp');

        $composer_command = 'install';
        if ($this->devMode) {
            $cmd = "sed -i'' -e 's/\"symlink\": true/\"symlink\": false/g' $directory/laravelApp/composer.json";
            $this->runCmd(['bash', '-c', $cmd]);
            $composer_command = 'update';
        } else {
            if (str_contains('"symlink": true', file_get_contents($directory . '/laravelApp/composer.json'))) {
                $this->error('Symlink is not allowed in production mode. Please remove "symlink": true from composer.json file.');
                exit(1);
            }
        }
        $this->output->write("Running composer '$composer_command' in build directory...\t\t");

        $this->runCmd(['bash', '-c', "rm -fr $directory/laravelApp/vendor"]);

        $this->runComposerCmd([
            $composer_command,
            '--optimize-autoloader',
            '--no-interaction',
            '--no-progress',
            '--no-scripts',
            '--prefer-dist',
            '--no-cache',
            '--no-dev',
        ], $directory . '/laravelApp');

        $cmd = 'php artisan octane:install --server=swoole';
        $this->runCmd(['bash', '-c', "cd $directory/laravelApp && $cmd"]);

        $this->line('[<comment>OK</comment>]');
    }

    public function initLaravelApp($directory): void
    {
        $this->checkProtectedPackages();
        if (!empty($this->pathRepositoriesToCopy)) {
            foreach ($this->pathRepositoriesToCopy as $pathRepositoryToCopy) {
                $this->line("Copying $pathRepositoryToCopy to $directory/laravelApp/$pathRepositoryToCopy\t\t" . '[<comment>OK</comment>]');
                $this->runCmd(['bash', '-c', "cp -r $pathRepositoryToCopy $directory/laravelApp/" . $pathRepositoryToCopy]);
            }
        }

        $composer_command = 'install';
        if ($this->devMode) {
            $cmd = "sed -i'' -e 's/\"symlink\": true/\"symlink\": false/g' $directory/laravelApp/composer.json";
            $this->runCmd(['bash', '-c', $cmd]);
            $composer_command = 'update';
        } else {
            if (str_contains('"symlink": true', file_get_contents($directory . '/laravelApp/composer.json'))) {
                $this->error('Symlink is not allowed in production mode. Please remove "symlink": true from composer.json file.');
                exit(1);
            }
        }

        $this->runCmd(['bash', '-c', "rm -fr $directory/laravelApp/vendor"]);

        $this->runComposerCmd([
            $composer_command,
            '--optimize-autoloader',
            '--no-interaction',
            '--no-progress',
            '--no-scripts',
            '--prefer-dist',
            '--no-cache',
            '--no-dev',
        ], $directory . '/laravelApp');

        $this->output->write("Running composer $composer_command in build directory...\t\t");

        $this->line('[<comment>OK</comment>]');
    }

    public function buildImage($directory, $tag, $imageType): string
    {
        $imageName = config('uxmaltech.prefix', 'uxtch') . '-' . config('uxmaltech.name') . '-' . $imageType;
        $this->output->write("Building image...\t\t");
        $this->runDockerCmd(['build', '-t', $imageName . ':' . $tag, $directory]);
        $this->runDockerCmd(['tag', $imageName . ':' . $tag, $imageName . ':latest']);
        // TODO: Condition if registry is AWS ECR
        // $this->runDockerCmd(['tag', config('uxmaltech.name').':'.$tag, config('dockerized.registry').'/'.config('uxmaltech.name').':'.$tag]);
        $this->line('[<comment>OK</comment>]');

        return $imageName . ':' . $tag;
    }
}
