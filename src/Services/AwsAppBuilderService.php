<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Interfaces\AppBuilderInterface;

class AwsAppBuilderService implements AppBuilderInterface
{
    public function build(string $repository): void
    {
        throw new \Exception('Not implemented yet.');
    }
}
