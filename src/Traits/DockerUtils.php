<?php

namespace Uxmal\Devtools\Traits;

use Docker\Docker;

trait DockerUtils
{
    protected string $alpineVersion = '3.19';
    protected string $phpVersion = '8.2.15';
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
            if (! array_key_exists($imageId, $images2Return)) {
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
}
