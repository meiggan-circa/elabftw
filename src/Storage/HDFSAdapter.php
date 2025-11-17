<?php
declare(strict_types=1);

namespace Elabftw\Storage;

use GuzzleHttp\Client;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\PathPrefixer;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Psr\Http\Message\StreamInterface;

class HDFSAdapter implements FilesystemAdapter {
  private Client $client;
  private PathPrefixer $prefixer;
  private MimeTypeDetector $mimeTypeDetector;

  public function __construct(
    string $apiBaseUrl,
    string $basePath = '/',
    ?MimeTypeDetector $mimeTypeDetector = null,
  )
  {
    $this->client = new Client([
      'base_uri' => rtrim($apiBaseUrl, '/'),
      'timeout' => 300,
    ]);
    $this->prefixer = new PathPrefixer($basePath);
    $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
  }

  public function fileExists(string $path): bool
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('/exists', [
      'query' => ['path' => $location]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    return $data['path_type'] === 'file';
  }

  public function directoryExists(string $path): bool
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('/exists', [
      'query' => ['path' => $location]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    return $data['path_type'] === 'directory';
  }

  public function write(string $path, string $contents, Config $config): void
  {
    $this->upload($path, $contents);
  }

  public function writeStream(string $path, $contents, Config $config): void
  {
    $this->upload($path, $contents);
  }

  private function upload(string $path, $contents): void
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->post('/upload', [
      'multipart' => [
        [
          'name' => 'path',
          'contents' => $location
        ],
        [
          'name' => 'file',
          'contents' => $contents,
          'filename' => basename($location)
        ],
      ]
    ]);
  }

  public function read(string $path): string
  {
    $body = $this->fetchStream($path);
    return (string) $body->getContents();
  }

  public function readStream(string $path)
  {
    $resource =  $this->fetchStream($path)->detach();
    return $resource;
  }

  private function fetchStream(string $path): StreamInterface
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('/download', [
      'query' => ['path' => $location],
      'stream' => true
    ]);
    return $response->getBody();
  }

  public function delete(string $path): void
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->post('/delete', [
      'body' => ['path' => $location],
    ]);
  }

  public function deleteDirectory(string $path): void
  {
    $this->delete($path);
  }

  public function createDirectory(string $path, Config $config): void
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->post('/mkdir', [
      'body' => ['path' => $location],
    ]);
  }

  public function setVisibility(string $path, string $visibility): void
  {

  }

  public function visibility(string $path): FileAttributes
  {
    return new FileAttributes($path);
  }

  public function mimeType(string $path): FileAttributes
  {
    $location = $this->prefixer->prefixPath($path);
    if ( ! $this->fileExists($location)) {
        throw UnableToRetrieveMetadata::mimeType($location, 'No such file exists.');
    }

    $mimeType = $this->mimeTypeDetector->detectMimeTypeFromPath($location);
    if ($mimeType === null) {
        throw UnableToRetrieveMetadata::mimeType($path, error_get_last()['message'] ?? '');
    }

    return new FileAttributes($path, null, null, null, $mimeType);
  }

  public function lastModified(string $path): FileAttributes
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('/list', [
      'query' => ['path' => $location]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    $lastModified = $data['mtime'];

    return new FileAttributes($path, null, null, $lastModified);
  }

  public function fileSize(string $path): FileAttributes
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('/list', [
      'query' => ['path' => $location]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    $fileSize = $data['size'];

    return new FileAttributes($path, $fileSize);
  }

  public function listContents(string $path, bool $deep): iterable
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('/list', [
      'query' => [
        'path' => $location,
        'deep' => $deep,
      ]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);

    foreach ($data as $pathInfo) {
      $path = $this->prefixer->stripPrefix($pathInfo['path']);
      $size = $pathInfo['size'];
      $lastModified = $pathInfo['mtime'];
      $isFile = $pathInfo['is_file'];

      yield $isFile ?
        new FileAttributes($path, $size, null, $lastModified) :
        new DirectoryAttributes($path, null, $lastModified);
    }
  }

  public function move(string $source, string $destination, Config $config): void
  {

  }

  public function copy(string $source, string $destination, Config $config): void
  {

  }
}
