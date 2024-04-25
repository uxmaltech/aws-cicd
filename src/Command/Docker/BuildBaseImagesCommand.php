<?php

namespace Uxmal\Devtools\Command\Docker;

use Illuminate\Console\Command;
use Random\RandomException;
use Symfony\Component\Console\Helper\TableSeparator;
use Uxmal\Devtools\Traits\DockerTrait;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildBaseImagesCommand extends Command
{
    use DockerTrait, GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'docker:build-base-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build the base docker image for CI/CD.';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    /**
     * @throws RandomException
     */
    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }
        system('clear');
        $this->buildDockerImage();
    }

    /**
     * Build the base docker image.
     */
    protected function buildDockerImage(): void
    {
        $this->line('Building the base docker image for '.config('uxmaltech.name').' ...');
        $this->newLine();

        $headers = ['Variable', 'Contenido'];

        $prefix = config('uxmaltech.prefix', 'uxtch');

        $currentDate = date('dmY');
        $hours = date('G');   // Hours since midnight (0-23)
        $minutes = date('i'); // Minutes (00-59)
        $seconds = date('s'); // Seconds (00-59)
        $secondsOfDay = ((int) $hours * 3600) + ((int) $minutes * 60).(int) $seconds;
        $versionString = $currentDate.$hours.$minutes.$seconds;

        /**
         * $alpineVersion = '3.19';
         * $phpVersion = '8.2.15';
         * $nginxVersion = '1.24';
         * $apacheVersion = '2.4.52';
         */
        $envTable = [];
        $apache_php_base_image = $prefix.'-apache'.$this->apacheVersion.'-php'.$this->phpVersion.'-alpine'.$this->alpineVersion;
        $nginx_base_image = $prefix.'-nginx'.$this->nginxVersion.'-alpine'.$this->alpineVersion;
        $php_fpm_image = $prefix.'-php-fpm'.$this->phpVersion.'-alpine'.$this->alpineVersion;
        $php_cli_octane_image = $prefix.'-php-'.$this->phpVersion.'-octane-alpine'.$this->alpineVersion;
        $php_cli_image = $prefix.'-php-cli-'.$this->phpVersion.'-alpine'.$this->alpineVersion;
        $dockerizedImages = config('dockerized.images');

        $envTable = array_merge($envTable, [
            ['Build Version', $versionString],
            ['Apache PHP Image', $apache_php_base_image],
            ['Nginx Image', $nginx_base_image],
            ['PHP-FPM Image', $php_fpm_image],
            ['Octane PHP-Cli Swoole', $php_cli_octane_image],
            ['PHP-Cli Image', $php_cli_image],
            new TableSeparator(),
            ['Images To Build', implode(',', array_keys($dockerizedImages))],
        ]);

        $this->table($headers, $envTable);

        if (! $this->confirm('¿Proceder a la creación de las imágenes Bases?')) {
            $this->error('Abortando...');
            exit(1);
        }

        foreach (array_keys($dockerizedImages) as $imageToBuild) {
            switch ($imageToBuild) {
                case 'apache-php':
                    $this->buildApachePhpImage($apache_php_base_image, $versionString);
                    break;
                case 'nginx':
                    $this->buildNginxImage($nginx_base_image, $versionString);
                    break;
                case 'php-fpm':
                    $this->buildPhpFpmImage($php_fpm_image, $versionString);
                    break;
                case 'php-octane':
                    $this->buildPhpCliOctaneImage($php_cli_octane_image, $versionString);
                    break;
                case 'php-cli':
                    $this->buildPhpCliImage($php_cli_image, $versionString);
                    break;
            }
        }
        exit(0);
    }

    private function buildApachePhpImage($image, $versionString): void
    {
        $workDir = __DIR__.'/base-images/uxtch-apache-php/';
        if (! is_dir($workDir)) {
            $this->error('The directory docker-images/base-images/uxtch-apache-php does not exists.');
            exit(1);
        }
        $this->info("Build Image: $image Tag: $versionString");

        $this->runDockerCmd(['build', '.', '-t', $image.':'.$versionString], $workDir);

        $this->runDockerCmd(['tag', $image.':'.$versionString, $image.':latest'], $workDir);

        $this->info("Docker image built $image:$versionString successfully.");

        $this->replaceConfigKey('images.apache-php.base-image', $image.':'.$versionString, true, config_path('dockerized.php'));
    }

    private function buildNginxImage($image, $versionString): void
    {
        $workDir = __DIR__.'/base-images/uxtch-nginx/';
        if (! is_dir($workDir)) {
            $this->error('The directory docker-images/base-images/uxtch-nginx does not exists.');
            exit(1);
        }
        $this->info("Build Image: $image Tag: $versionString");

        $this->runDockerCmd(['build', '.', '-t', $image.':'.$versionString], $workDir);

        $this->runDockerCmd(['tag', $image.':'.$versionString, $image.':latest'], $workDir);

        $this->info("Docker image built $image:$versionString successfully.");

        $this->replaceConfigKey('images.nginx.base-image', $image.':'.$versionString, true, config_path('dockerized.php'));
    }

    private function buildPhpFpmImage($image, $versionString): void
    {
        $workDir = __DIR__.'/base-images/uxtch-php-fpm/';
        if (! is_dir($workDir)) {
            $this->error('The directory docker-images/base-images/uxtch-php-fpm does not exists.');
            exit(1);
        }
        $this->info("Build Image: $image Tag: $versionString");

        $this->runDockerCmd(['build', '.', '-t', $image.':'.$versionString], $workDir);

        $this->runDockerCmd(['tag', $image.':'.$versionString, $image.':latest'], $workDir);

        $this->info("Docker image built $image:$versionString successfully.");

        $this->replaceConfigKey('images.php-fpm.base-image', $image.':'.$versionString, true, config_path('dockerized.php'));
    }

    private function buildPhpCliOctaneImage($image, $versionString): void
    {
        $workDir = __DIR__.'/base-images/uxtch-php-octane/';
        if (! is_dir($workDir)) {
            $this->error('The directory docker-images/base-images/uxtch-php-cli-octane does not exists.');
            exit(1);
        }
        $this->info("Build Image: $image Tag: $versionString");

        $this->runDockerCmd(['build', '.', '-t', $image.':'.$versionString], $workDir);

        $this->runDockerCmd(['tag', $image.':'.$versionString, $image.':latest'], $workDir);

        $this->info("Docker image built $image:$versionString successfully.");

        $this->replaceConfigKey('images.php-octane.base-image', $image.':'.$versionString, true, config_path('dockerized.php'));
    }

    private function buildPhpCliImage($image, $versionString): void
    {
        $workDir = __DIR__.'/base-images/uxtch-php-cli/';
        if (! is_dir($workDir)) {
            $this->error('The directory docker-images/base-images/uxtch-php-cli does not exists.');
            exit(1);
        }
        $this->info("Build Image: $image Tag: $versionString");

        $this->runDockerCmd(['build', '.', '-t', $image.':'.$versionString], $workDir);

        $this->runDockerCmd(['tag', $image.':'.$versionString, $image.':latest'], $workDir);

        $this->info("Docker image built $image:$versionString successfully.");

        $this->replaceConfigKey('images.php-cli.base-image', $image.':'.$versionString, true, config_path('dockerized.php'));
    }
}
