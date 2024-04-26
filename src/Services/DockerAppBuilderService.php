<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Traits\GitTrait;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Uxmal\Devtools\Interfaces\AppBuilderInterface;
use Uxmal\Devtools\Traits\GeneralUtils;

class DockerAppBuilderService implements AppBuilderInterface
{
    use GitTrait;
    use GeneralUtils;
    public function build(string $repository): void
    {
        Log::info('Building docker images...');



        //$uxmalEnvReplacement = $this->arrayToEnvNotation(config('uxmaltech'), 'UXMALTECH_');
        //Log::info('Replacing env variables in docker-compose.yml', ['env' => $uxmalEnvReplacement]);

        try {
            $prefix = config('uxmaltech.prefix', 'uxtch');

            // $nginx_base_image = $prefix . '-nginx' . $this->nginxVersion . '-alpine' . $this->alpineVersion;
            $uxmal_nginx_base_image = "uxmal-nginx";
            $base_path = __DIR__ . '/../Command/Docker';

            $docker = new DockerService();

            $envVars = config('dockerized.images.nginx', []);

            $entryPointStub = $base_path . '/app-images/nginx/bash/entrypoint.sh';
            $entryPoint = $base_path . '/app-images/nginx/bash/entrypoint.sh';

            $nginxStub = $base_path . '/app-images/nginx/nginx.conf.stub';
            $nginxConf = $base_path . '/app-images/nginx/nginx.conf';

            $dockerfileStub = $base_path . '/app-images/nginx/dockerfile.stub';
            $dockerfile = $base_path . '/app-images/nginx/Dockerfile';


            // Generate Dockerfile from stub
            $this->generateFileFromStub($nginxStub, $nginxConf, $envVars);
            $this->generateFileFromStub($entryPointStub, $entryPoint, $envVars);
            $this->generateFileFromStub($dockerfileStub, $dockerfile, $envVars);


            //   Build image from local Dockerfile
            $response = $docker->buildImage($uxmal_nginx_base_image, '', $dockerfile);
            Log::info('Docker::buildImage', [
                'response' => $response
            ]);

            // Tag existing image
            $response = $docker->tagImage($uxmal_nginx_base_image, '');
            Log::info('Docker::tagImage', [
                'response' => $response
            ]);
            // List all images in the system
            // $list = $docker->listImages();

            // Log::info('Docker images', [
            //     'list' => $list
            // ]);

            // Pull image from docker hub
            // $docker->pullImage('nginx')

            // Build image from local Dockerfile
            // $response =$docker->buildImage($nginx_base_image, 'latest', $path);
            // Log::info('Docker::buildImage', [
            //     'response' => $response
            // ]);

            // Tag existing image
            // $response = $docker->tagImage($nginx_base_image, 'v2');
            // Log::info('Docker::tagImage', [
            //     'response' => $response
            // ]);

            // // Run a container 
            // $response = $docker->runContainer("nginx", [
            //     'N' => 'nginx',
            //     'detach' => true,
            //     'publish' => '8080:80',
            //     'Tty' => true,
            //     'Env' => [
            //         'FOO=bar',
            //         'BAZ=qux'
            //     ],
            //     'HostConfig' => [
            //         'PortBindings' => [
            //             '80/tcp' => [
            //                 [
            //                     'HostIp' => '',
            //                     'HostPort' => '8088'
            //                 ],
            //             ],
            //         ]
            //     ],
            //     'Cmd' => ["nginx", "-g", "daemon off;"]
            // ], true);
        } catch (\Exception $e) {
            Log::error(['error' => $e->getMessage()]);
            throw new \Exception('error building docker images');
        }
    }
    //protected function buildDockerImage(): void
    //{
    //Log::info('Building the base docker image for ' . config('uxmaltech.name') . ' ...');

    //$headers = ['Variable', 'Contenido'];

    //$prefix = config('uxmaltech.prefix', 'uxtch');

    //$currentDate = date('dmY');
    //$hours = date('G');   // Hours since midnight (0-23)
    //$minutes = date('i'); // Minutes (00-59)
    //$seconds = date('s'); // Seconds (00-59)
    //$secondsOfDay = ((int) $hours * 3600) + ((int) $minutes * 60) . (int) $seconds;
    //$versionString = $currentDate . $hours . $minutes . $seconds;

    //$alpineVersion = '3.19';
    //$phpVersion = '8.2.15';
    //$nginxVersion = '1.24';
    //$apacheVersion = '2.4.52';
    //$envTable = [];
    //$apache_php_base_image = $prefix . '-apache' . $this->apacheVersion . '-php' . $this->phpVersion . '-alpine' . $this->alpineVersion;
    //$nginx_base_image = $prefix . '-nginx' . $this->nginxVersion . '-alpine' . $this->alpineVersion;
    //$php_fpm_image = $prefix . '-php-fpm' . $this->phpVersion . '-alpine' . $this->alpineVersion;
    //$php_cli_octane_image = $prefix . '-php-' . $this->phpVersion . '-octane-alpine' . $this->alpineVersion;
    //$php_cli_image = $prefix . '-php-cli-' . $this->phpVersion . '-alpine' . $this->alpineVersion;
    //$dockerizedImages = config('dockerized.images');

    //$envTable = array_merge($envTable, [
    //['Build Version', $versionString],
    //['Apache PHP Image', $apache_php_base_image],
    //['Nginx Image', $nginx_base_image],
    //['PHP-FPM Image', $php_fpm_image],
    //['Octane PHP-Cli Swoole', $php_cli_octane_image],
    //['PHP-Cli Image', $php_cli_image],
    //new TableSeparator(),
    //['Images To Build', implode(',', array_keys($dockerizedImages))],
    //]);
    //}

    private function buildAndTagImage(string $name, string $version = 'latest'): void
    {
        $workDir = __DIR__ . '/../Command/Docker/base-images/uxtch-nginx/';
        if (!is_dir($workDir)) {
            throw new \Exception('Directory not found: ' . $workDir);
        }
        if (empty($name)) {
            throw new \Exception('Name is required');
        }
        if (empty($version)) {
            $version = 'latest';
        }
        // Define the docker build command
        $dockerBuildCommand = [
            'docker',
            'build',
            '-t',
            $name,
            '.'
        ];


        $process = new Process($dockerBuildCommand);
        $process->setWorkingDirectory($workDir);
        $process->setTimeout(3600)->setIdleTimeout(3600);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > ' . $buffer;
            } else {
                echo 'OUT > ' . $buffer;
            }
        });
    }


    private function arrayToEnvNotation($array, $prefix = ''): array
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
}