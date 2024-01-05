<?php

namespace Uxmal\Devtools\Command\Aws;

class ECRPushToRepositoryCommand extends AWSCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'aws:ecr-push';

    protected $description = 'Hace un push a un repository de AWS ECR';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }
        $this->pushImagesToRepository();
    }

    public function pushImagesToRepository(): void
    {
        $this->info('Hacer push a un repositorio en Amazon ECR...');
        // Revisar si existen los repositorios en AWS ECR
        $reposToCreate = $this->getReposPendingToCreate();
        if (! empty($reposToCreate)) {
            foreach ($reposToCreate as $repositoryName) {
                if ($this->confirm('Crear el repositorio '.$repositoryName.' en AWS ECR?')) {
                    $created_repos[$repositoryName] = $this->ecrCreateRepository($repositoryName)->toArray()['repository']['repositoryUri'];
                }
            }
        }

        $aws_repositories_result = $this->ecrListRepositories();
        $aws_repositories = [];
        foreach ($aws_repositories_result as $repository) {
            $aws_repositories[$repository['repositoryName']] = $repository['repositoryUri'];
        }
        $devtools_config = config('aws-cicd.cluster.aws.ecr.repositories');

        /**
         * $aws_repositories = [
         *      "base-php-fpm" => "{aws_account_id}.dkr.ecr.{aws_region_id}.amazonaws.com/base-php-fpm"
         *      "app-php-fpm" => "{aws_account_id}.dkr.ecr.{aws_region_id}.amazonaws.com/app-php-fpm"
         *      "base-nginx" => "{aws_account_id}.dkr.ecr.{aws_region_id}.amazonaws.com/base-nginx"
         *      "app-nginx" => "{aws_account_id}.dkr.ecr.{aws_region_id}.amazonaws.com/app-nginx"
         * ]
         */
        if ($aws_repositories != $devtools_config) {
            $this->warn('Los repositorios de AWS ECR no coinciden con los de config/devtools.php');
            if ($this->confirm('Deseas actualizar config/devtools.php con los repositorios de AWS ECR?')) {
                $this->replaceConfigKey('cluster.aws.ecr.repositories', $aws_repositories);
            }
        }

        /**
         * Login to AWS ECR
         */
        $this->dockerLoginToECR();

        $dockerImages = $this->dockerImageList();

        // Checar los "tags" correctos de las imágenes para hacer push
        foreach ($dockerImages as $imageId => $imageRepoTags) {
            $found_ecr_tags_for_image_id = false;
            foreach ($imageRepoTags as $repoTag) {
                if (in_array($repoTag['repo_name'], array_values($aws_repositories))) {
                    $found_ecr_tags_for_image_id = true;
                    break;
                }
            }
            // No se encontraron tags para hacer push
            // {aws_account_id}.dkr.ecr.{aws_region_id}.amazonaws.com/base-php-fpm:{version}
            if (! $found_ecr_tags_for_image_id) {
                $this->warn('No se encontraron tags de AWS ECR para la imagen ID:['.$imageId.']');
                if ($this->confirm('Deseas crear los tags de AWS ECR para la imagen ID:['.$imageId.']?')) {
                    foreach ($imageRepoTags as $repoTag) {
                        if (array_key_exists($repoTag['repo_name'], $aws_repositories)) {
                            $this->info('Creando tag de la imagen ID:['.$imageId.'] Tag:['.$aws_repositories[$repoTag['repo_name']].':'.$repoTag['tag'].']');

                            $this->runDockerCmd(['tag', $imageId, $aws_repositories[$repoTag['repo_name']].':'.$repoTag['tag']]);
                            if (! $this->ecrImageExists($aws_repositories[$repoTag['repo_name']], $repoTag['tag'])) {
                                $this->info('Haciendo push de la imagen ID:['.$imageId.'] Tag:['.$aws_repositories[$repoTag['repo_name']].':'.$repoTag['tag'].']');
                                // Crear la imagen en AWS ECR ya que
                                $this->runDockerCmd(['push', $aws_repositories[$repoTag['repo_name']].':'.$repoTag['tag']]);
                            } else {
                                $this->warn('La imagen ID:['.$imageId.'] Tag:['.$repoTag['repo_name'].':'.$repoTag['tag'].'] ya existe en AWS ECR');
                            }
                        }
                    }
                }
            }
        }

        // Hacer push de las imágenes a los repositorios de AWS ECR
        foreach ($dockerImages as $imageId => $imageRepoTags) {
            foreach ($imageRepoTags as $repoTag) {
                if (in_array($repoTag['repo_name'], array_values($aws_repositories))) {
                    if (! $this->ecrImageExists($repoTag['repo_name'], $repoTag['tag'])) {
                        $this->info('Haciendo push de la imagen ID:['.$imageId.'] Tag:['.$repoTag['repo_name'].':'.$repoTag['tag'].']');
                        $this->runDockerCmd(['push', $repoTag['repo_name'].':'.$repoTag['tag']]);
                    } else {
                        $this->warn('La imagen ID:['.$imageId.'] Tag:['.$repoTag['repo_name'].':'.$repoTag['tag'].'] ya existe en AWS ECR');
                    }
                }
            }
        }
    }

    private function getReposPendingToCreate(): array
    {
        $aws_repositories = $this->ecrListRepositories();
        $repos2Create = $this->repositoriesKeys;
        foreach ($this->repositoriesKeys as $repoName) {
            foreach ($aws_repositories as $aws_repo) {
                if ($aws_repo['repositoryName'] === $repoName) {
                    $repos2Create = array_diff($repos2Create, [$repoName]);

                    continue 2;
                }
            }
        }

        return $repos2Create;
    }
}
