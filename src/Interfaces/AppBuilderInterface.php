<?php

namespace Uxmal\Devtools\Interfaces;

interface AppBuilderInterface
{
    // Build the app
    public function build(string $repository): void;
}
