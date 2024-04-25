<?php

namespace Uxmal\Devtools\Command\Aws;

use Illuminate\Console\Command;
use Uxmal\Devtools\Traits\AWS\CloudWatchLogUtils;
use Uxmal\Devtools\Traits\AWS\EC2Utils;
use Uxmal\Devtools\Traits\AWS\ECRUtils;
use Uxmal\Devtools\Traits\AWS\ECSUtils;
use Uxmal\Devtools\Traits\AWS\Route53Utils;
use Uxmal\Devtools\Traits\DockerTrait;
use Uxmal\Devtools\Traits\GeneralUtils;

class AWSCommand extends Command
{
    use CloudWatchLogUtils,
        DockerTrait,
        EC2Utils,
        ECRUtils,
        ECSUtils,
        GeneralUtils,
        Route53Utils;

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
        $this->configureAWSDryRun();
    }
}
