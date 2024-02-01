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
            $this->warn('An error occurred: '.$exception->getMessage());
        } catch (RandomException $e) {
            $this->warn('An error occurred: '.$e->getMessage());
        }

        system('clear');
        $this->composeBuild();
    }

    private function composeBuild(): void
    {
        $uxmalTechReplacement = $this->arrayToDotNotation(config('uxmaltech'), 'uxmaltech.');
        $uxmalEnvReplacement = $this->arrayToEnvNotation(config('uxmaltech'), 'UXMALTECH_');

        $dockerizedImages = config('dockerized.images');

        $this->line('Building the docker images for '.config('uxmaltech.name').' ...');

        $release = $this->option('release') ?? 'latest';
        foreach (array_keys($dockerizedImages) as $imageToBuild) {
            switch ($imageToBuild) {
                case 'apache-php':
                    $buildDir = $this->laravel->basePath('uxmaltech-build').'/'.$release.'/apache-php';
                    $this->initDir($buildDir);
                    $this->copyLaravelApp($buildDir);
                    $this->initLaravelApp($buildDir);

                    $this->initDir($buildDir.'/conf');
                    $cmd = 'envsubst < '.__DIR__."/app-images/apache/httpd.conf.stub > $buildDir/conf/httpd.conf";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement);

                    $phpIniVars = [
                        'PHP_MEMORY_LIMIT' => '128M',
                        'PHP_EXPOSE_PHP' => 'On',
                        'PHP_SESSION_GC_MAXLIFETIME' => '1440',
                    ];

                    $cmd = 'envsubst < '.__DIR__."/app-images/apache/php.ini.stub > $buildDir/conf/php.ini";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $phpIniVars);

                    $this->initDir($buildDir.'/bash');

                    $cmd = 'cp '.__DIR__."/app-images/apache/bash/entrypoint.sh $buildDir/bash/entrypoint.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp '.__DIR__."/app-images/apache/bash/envsubst.sh $buildDir/bash/envsubst.sh";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $cmd = 'cp '.__DIR__."/app-images/apache/default-env-stub $buildDir/default-env-stub";
                    $this->runCmd(['bash', '-c', $cmd]);

                    $dockerizedEnvReplacement = $this->arrayToEnvNotation(config('dockerized'), 'UXMALTECH_');
                    $cmd = 'envsubst < '.__DIR__."/app-images/apache/dockerfile.stub > $buildDir/Dockerfile";
                    $this->runCmd(['bash', '-c', $cmd], $uxmalEnvReplacement + $dockerizedEnvReplacement);

                    $this->buildImage($buildDir, $release, 'apache-php');

                    break;
                case 'nginx':

                    break;
                case 'php-fpm':

                    break;
                case 'php-cli-octane':

                    break;
                case 'php-cli':

                    break;

            }
        }
        exit(0);

    }

    public function arrayToEnvNotation($array, $prefix = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, $this->arrayToEnvNotation($value, $prefix.$key.'_'));
            } else {
                $results[str_replace(['-'], ['_'], strtoupper($prefix.$key))] = $value;
            }
        }

        return $results;
    }

    public function arrayToDotNotation($array, $prefix = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, $this->arrayToDotNotation($value, $prefix.$key.'.'));
            } else {
                $results['{'.$prefix.$key.'}'] = $value;
            }
        }

        return $results;
    }

    public function initDir($buildDir): void
    {
        if (is_dir($buildDir)) {
            $this->runProcess("rm -rf $buildDir");
            $this->line('Removing old build directory '.$buildDir.'        '."\t\t".'[<comment>OK</comment>]');
        }

        if (mkdir($buildDir, 0777, true)) {
            $this->line('Build directory created '.$buildDir.'        '."\t\t".'[<comment>OK</comment>]');
        } else {
            $this->error('Build directory could not be created.');
            exit(1);
        }
    }

    public function copyLaravelApp($buildDir): void
    {
        $appBuildDir = $buildDir.'/laravelApp';
        $this->initDir($appBuildDir);
        $this->line('Copying Laravel Application to build directory '.$appBuildDir.'        '."\t\t".'[<comment>OK</comment>]');
        $this->output->write('Copying files to build directory...'."\t\t");
        if (! is_dir($this->laravel->basePath('.git'))) { // Si no existe el directorio .git, se copian los archivos con rsync
            $cmd = sprintf('rsync -av --exclude=%s %s %s', escapeshellarg('docker-images'), escapeshellarg($this->laravel->basePath().'/'), escapeshellarg($appBuildDir.'/'));
        } elseif ($this->devMode) { // Si existe el directorio .git y estamos en devMode, se copian los archivos con git ls-files
            $cmd = "git ls-files -z | tar --null -T - -cz | tar -xz -C $appBuildDir";
        } else { // Si existe el directorio .git y NO estamos en devMode, se copian los archivos con git archive (current branch)
            $cmd = "git archive $this->gitCommit | tar -x -C $appBuildDir";
        }
        $this->runCmd(['bash', '-c', $cmd]);

        if ($this->devMode) {
            // Copy the .env file to the build directory
            $cmd = 'cp '.$this->laravel->basePath('.env')." $buildDir/laravelApp/.env";
            $this->runCmd(['bash', '-c', $cmd]);
        }

        $this->line('[<comment>OK</comment>]');
    }

    public function initLaravelApp($buildDir): void
    {
        $this->checkProtectedPackages();
        if (! empty($this->pathRepositoriesToCopy)) {
            foreach ($this->pathRepositoriesToCopy as $pathRepositoryToCopy) {
                $this->line("Copying $pathRepositoryToCopy to $buildDir/laravelApp/$pathRepositoryToCopy\t\t".'[<comment>OK</comment>]');
                $this->runCmd(['bash', '-c', "cp -r $pathRepositoryToCopy $buildDir/laravelApp/".$pathRepositoryToCopy]);
            }
        }

        $composer_command = 'install';
        if ($this->devMode) {
            $cmd = "sed -i'' -e 's/\"symlink\": true/\"symlink\": false/g' $buildDir/laravelApp/composer.json";
            $this->runCmd(['bash', '-c', $cmd]);
            $composer_command = 'update';
        } else {
            if (str_contains('"symlink": true', file_get_contents($buildDir.'/laravelApp/composer.json'))) {
                $this->error('Symlink is not allowed in production mode. Please remove "symlink": true from composer.json file.');
                exit(1);
            }
        }

        $this->runComposerCmd([
            $composer_command,
            '--optimize-autoloader',
            '--no-interaction',
            '--no-progress',
            '--no-scripts',
            '--prefer-dist',
            '--no-cache',
            '--no-dev',
        ], $buildDir.'/laravelApp');

        $this->output->write("Running composer $composer_command in build directory...\t\t");

        try {
            $this->runCmd(['bash', '-c', "cd $buildDir/laravelApp && npm run build"]);
        } catch (ProcessFailedException $exception) {
            $this->error('An error occurred: '.$exception->getMessage());
            exit(1);
        }

        $this->line('[<comment>OK</comment>]');
    }

    public function buildImage($buildDir, $tag, $imageType): void
    {
        $imageName = config('uxmaltech.prefix', 'uxtch').'-'.config('uxmaltech.name').$imageType;
        $this->output->write("Building image...\t\t");
        $this->runDockerCmd(['build', '-t', $imageName.':'.$tag, $buildDir]);
        $this->runDockerCmd(['tag', $imageName.':'.$tag, $imageName.':latest']);
        // $this->runDockerCmd(['tag', config('uxmaltech.name').':'.$tag, config('dockerized.registry').'/'.config('uxmaltech.name').':'.$tag]);
        $this->line('[<comment>OK</comment>]');
    }
}
