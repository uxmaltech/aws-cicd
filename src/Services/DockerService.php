<?php

namespace Uxmal\Devtools\Services;

use AWS\CRT\HTTP\Response;
use Exception;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as Psr7Response;

class DockerService
{

    protected $client;

    private $regisrty = 'docker.io';
    public $socketMode = true;
    private const DOCKER_SOCKET = 'unix:///var/run/docker.sock';
    private const BASE_URL = 'http://localhost:2375';

    public const DOCKER_API_VERSION = 'v1.41';

    function __construct(bool $socketMode = true)
    {
        $this->socketMode = $socketMode;

        // 'base_uri' => $this->dockerMode == 'socket' ? self::DOCKER_SOCKET : self::BASE_URL,
        // 'base_uri' => 'http:/v1.41',

        $baseUri = self::BASE_URL;
        if ($this->socketMode) {
            $baseUri = 'http://localhost/' . SELF::DOCKER_API_VERSION;
        }

        $this->client = new Client([
            'base_uri' =>   $baseUri,
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'curl' => $this->socketMode ? [
                CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock',
            ] : [],
        ]);
    }

    private function makeRequest($method, $uri, $options = []): Psr7Response
    {
        try {

            $response = $this->client->request($method, $uri, $options);
            return $response;
        } catch (Exception $e) {
            Log::error('DockerService::makeRequest', [
                'method' => $method,
                'uri' => $uri,
                'options' => $options,
                'error' => $e->getMessage()
            ]);
            // Handle errors here, for example:
            // echo 'Caught exception: ',  $e->getMessage(), "\n";
            return null;
        }
    }

    private function dockerFileToTar(string $dockerFilePath): string
    {
        $sourceFile = $dockerFilePath . '/Dockerfile';


        if (!is_dir($dockerFilePath)) {
            throw new \Exception('The directory ' . $dockerFilePath . ' does not exists.');
        }
        if (!is_file($sourceFile)) {
            throw new \Exception('The file ' . $sourceFile . ' does not exists.');
        }

        $tarFile = tempnam(sys_get_temp_dir(), 'docker-build-') . '.tar';
        $tar = new \PharData($tarFile);
        $tar->addFile($sourceFile, 'Dockerfile');
        // $tar->buid($sourceFile);
        // $tar->compress(\Phar::GZ);
        return $tarFile;
    }
    private function validateDockerEngine(): bool
    {
        // $command = ['docker', '-v'];

        // try {
        //     $this->runCmd($command);
        // } catch (ProcessFailedException $exception) {

        //     throw new Exception("Docker engine installed or not running");
        // }
        return false;
    }

    public function listImages(): array
    {

        $images = [];
        $response = $this->makeRequest('get', 'images/json');


        $body = json_decode($response->getBody(), true);
        foreach ($body as $image) {
            if (isset($image['RepoTags']) && is_array($image['RepoTags']) && sizeof($image['RepoTags']) > 0) {

                $image = explode(':', $image['RepoTags'][0]);
                $images[$image[0]][] = $image[1];
            }
        }

        return $images;
    }

    public function getImageByName(string $name): array
    {
        $response = $this->makeRequest('get', 'images/' . $name . '/json');
        $body = json_decode($response->getBody(), true);
        return $body;
    }

    public function pullImage(string $image, string $tag = 'latest'): bool
    {


        $url = 'images/create?fromImage=' . $image . '&tag=' . $tag;
        $response = $this->makeRequest('post', $url);

        // Log::debug('DockerService::pullImage', [
        //     'image' => $image,
        //     'tag' => $tag,
        //     'response' => $response->getBody()
        // ]);
        return $response->getStatusCode() == 200;
    }

    public function buildImage(string $name, string $version = 'latest', $dockerfilePath = ''): bool
    {
        try {
            // Converty the dockerfile to a tar file in the tmp dir
            $dockeFile = fopen($this->dockerFileToTar($dockerfilePath), 'r');
            $opts = [
                'headers' => [
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $dockeFile,
                'query' => [
                    't' => $name . ':' . $version,
                    'nocache' => 'true',
                    'platform' => 'linux/amd64'
                ]
            ];

            $response = $this->makeRequest('post', 'build', $opts);
            return $response->getStatusCode() == 200;
        } catch (Exception $e) {
            Log::debug('DockerService::buildImage', [
                'e' => $e->getMessage(),
                'name' => $name,
                'version' => $version,
                'dockerfilePath' => $dockerfilePath,
            ]);
            throw new Exception($e->getMessage());
        }
    }

    public function tagImage(string $image, string $tag): void
    {
        $opts = [
            'query' => [
                'repo' => $image,
                'tag' => $tag
            ]
        ];
        $url = 'images/' . $image . '/tag';

        $exisist = $this->getImageByName($image);
        if (sizeof($exisist) == 0) {
            throw new Exception('Image ' . $image . ' does not exist');
        }

        $this->makeRequest('post', $url, $opts);
    }

    private function createContainer($imageName, array $args): string
    {
        $image = $this->getImageByName($imageName);
        if (sizeof($image) == 0 || !isset($image['Id'])) {
            throw new Exception('Image ' . $imageName . ' does not exist');
        }

        $image = $this->getImageByName($imageName);
        if (sizeof($image) == 0 || !isset($image['Id'])) {
            throw new Exception('Image ' . $imageName . ' does not exist');
        }
        $args['Image'] = $imageName;
        $opts = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($args)
        ];

        $response = $this->makeRequest('post', 'containers/create', $opts);
        $body = json_decode($response->getBody(), true);
        $containerId = $body['Id'];

        return $containerId;


    }

    public function runContainer(string $imageName, array $args = []): void
    {

       
        // $cmd = [];
        // if (isset($args['cmd'])) {
        //     $cmd = $args;
        //     unset($args['cmd']);
        // }
        $containerId = $this->createContainer($imageName, $args);
        // $containerId = mb_substr($containerId, 0, 12);
        if (empty($containerId)) {
            throw new Exception('Error creating container');
        }

      
        $response = $this->makeRequest('post', 'containers/' . $containerId . '/start',);
        if ($response->getStatusCode() >= 400) {
            throw new Exception('Error starting container');
        }
    }
}
