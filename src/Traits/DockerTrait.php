<?php

namespace Uxmal\Devtools\Traits;

use Docker\API\Model\BuildInfo;
use Docker\Docker;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

trait DockerTrait
{
    protected string $alpineVersion = '3.19';

    protected string $phpVersion = '8.2.15';

    protected string $kafkaVersion = '7.5.3';

    protected string $nginxVersion = '1.24';

    protected string $apacheVersion = '2.4.52';

    protected function getPHPFpmImage(): string
    {
        return "php:{$this->phpVersion}-fpm-alpine:{$this->alpineVersion}";
    }

    protected function getNginxImage(): string
    {
        return "nginx:{$this->nginxVersion}-alpine:{$this->alpineVersion}";
    }

    protected function getApacheImage(): string
    {
        return "httpd:{$this->apacheVersion}-alpine:{$this->alpineVersion}";
    }

    protected function getPhpCliImage(): string
    {
        return "php:{$this->phpVersion}-cli-alpine:{$this->alpineVersion}";
    }

    // Validate docker engine exists
    // @return bool
    protected function validateDockerEngine(): bool
    {
        $command = ['docker', '-v'];
        $process = new Process($command);
        try {
            $process->setTimeout(60)->setIdleTimeout(15)->mustRun();
            if ($process->isSuccessful()) {
                return true;
            }
            return false;
        } catch (ProcessFailedException $exception) {
            // Log::error('Docker engine not found');
            return false;
        }
    }

    protected function dockerImageList(): array
    {
        $docker = Docker::create();
        $images = $docker->imageList();

        $images2Return = [];
        foreach ($images as $image) {
            $repoTags = $image->getRepoTags();
            $imageId = substr($image->getId(), 7, 12); // substr($image->getId(), 7, 12
            if (!array_key_exists($imageId, $images2Return)) {
                $images2Return[$imageId] = [];
            }

            foreach ($repoTags as $repoTag) {
                $imageStr = explode(':', $repoTag);
                [$image_name, $image_tag] = $imageStr;

                $images2Return[$imageId][] = [
                    'repo_name' => $image_name,
                    'tag' => $image_tag,
                ];
            }
        }

        return $images2Return;
    }

    // Create a new docker images base on the dockerfile 
    // @param string $name The name of the image
    // @param string $version The version of the image, latest by default
    // @param string $dockerfilePath The path to the dockerfile
    // @return void
    // @throws \Exception
    protected function buildImage(string $name, string $version = 'latest', string $dockerfilePath = '')
    {

        try {
            if (!is_dir($dockerfilePath)) {
                throw new \Exception('The directory ' . $dockerfilePath . ' does not exists.');
            }
            if (!is_file($dockerfilePath . '/Dockerfile')) {
                throw new \Exception('The file ' . $dockerfilePath . '/Dockerfile does not exists.');
            }

            if (empty($name)) {
                throw new \Exception('Name is required');
            }
            if (empty($version)) {
                $version = 'latest';
            }


            // $docker = Docker::create();
            $docker = Docker::create();
            $context = new \Docker\Context\Context($dockerfilePath);
            $stream = $context->toStream();

            $buildStream  = $docker->imageBuild($stream, [

                't' => $name . ':' . $version,
                'nocache' => true,

            ]);


            // $buildStream->onFrame(function (BuildInfo $buildInfo) {
            //     Log::info([
            //         'build' => $buildInfo->getStream()
            //     ]);
            // });

            $buildStream->wait();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error building image -> ' . $e->getMessage());
        }
    }


    protected function buildAndTagImage(){

        

    }
}
