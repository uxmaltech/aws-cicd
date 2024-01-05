<?php

namespace Uxmal\Devtools\Traits;

use Docker\Docker;

trait DockerUtils
{
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
