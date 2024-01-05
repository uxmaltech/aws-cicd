# uxmaltech/devtools

## Overview

This repository contains the code for the AWS CI/CD pipeline. The pipeline is used to deploy.


## Prerequisites


## Usage

php artisan devtools:install

php artisan docker:build-base-images

php artisan docker:compose-build 

php artisan docker:compose-up

php artisan aws:ecr-push

php artisan aws:route53-create-domains --create-fqdn=true --create-intranet=true

php artisan aws:route53-delete-domains

php artisan aws:ecs-create-cluster

php artisan aws:ecs-create-service

php artisan aws:ecs-create-tasks



php artisan devtools:aws:create --type=resilient-infrastructure --name={deployment_name}
php artisan devtools:aws:deploy --name={deployment_name}




## Contributing
