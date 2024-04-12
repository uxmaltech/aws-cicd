<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Interfaces\AppBuilderInterface;

class AwsAppBuilderService implements AppBuilderInterface
{
    public function build(string $repository): void
    {
        echo "Building AWS app for repository " . $repository . "...\n";
    }
}
