<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Interfaces\AppBuilderInterface;

class DockerAppBuilderService implements AppBuilderInterface
{
    public function build(string $repository): void
    {
        echo "Building Docker app for repository " . $repository . "...\n";
    }
}
