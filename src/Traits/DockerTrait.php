<?php

namespace Uxmal\Devtools\Traits;

use Docker\Docker;
use Docker\API\Model\BuildInfo;
use Illuminate\Support\Facades\Log;

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
                //$dockerfilePath = __DIR__ . '/../Command/Docker/base-images/uxtch-nginx/';
            }
            if (empty($name)) {
                throw new \Exception('Name is required');
            }
            if (empty($version)) {
                $version = 'latest';
            }


            $context = new \Docker\Context\Context($dockerfilePath);
            $stream = $context->toStream();
            $docker = Docker::create();

            $buildStream = $docker->imageBuild($stream);
            Log::debug('1');
            $body = $buildStream->getBody();
            Log::debug('2');
            Log::debug('response', [
                'body' => $body
            ]);
            while (!$body->eof()) {
                $line = $body->read(1024);
                Log::debug('Image build : ' . $line);
            }

            //$buildStream->onFrame(function (BuildInfo $buildInfo) {

            //echo $buildInfo->getStream();
            //});

            //$buildStream->wait();
            //$context = new \Docker\Context\Context($dockerfilePath);
            //$stream = $context->toStream();

            //$docker = Docker::create();
            //Log::debug('Context created', [
            //'context' => $context,
            //'stream' => $stream,
            //'docker' => $docker,
            //'name' => $name,
            //'version' => $version,
            //'dockerfilePath' => $dockerfilePath
            //]);


            //$out = $docker->imageBuild($stream, [

            //'t' => $name . ':' . $version,
            //'remote' => 'http://localhost:2375',
            //'q' => true,
            //]);
            //Read from the stream until the end of data
            //while (!feof($out)) {
            //Read a line from the stream
            //$line = fgets($out);

            //Process the line (example: print it)
            //echo $line;
            //Log::debug('Image build', [
            //'line' => $line
            //]);
            //}

            //Close the stream
            //fclose($stream);
            //Log::debug('Image built', [
            //'out' => $out
            //]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error building image');
        }
    }
}
