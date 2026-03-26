<?php

declare(strict_types=1);

namespace Elabftw\Storage;

use Elabftw\Elabftw\Env;
use GuzzleHttp\Client;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Psr\Http\Message\StreamInterface;
use Throwable;

class HDFSAdapter implements FilesystemAdapter
{
  private Client $client;
  private PathPrefixer $prefixer;
  private MimeTypeDetector $mimeTypeDetector;

  public function __construct(
    string $basePath = '/',
    ?MimeTypeDetector $mimeTypeDetector = null,
  ) {
    $this->client = new Client([
      'base_uri' => Env::asString('HDFS_API'),
      'timeout' => 300,
    ]);
    $this->prefixer = new PathPrefixer($basePath);
    $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
  }

  public function fileExists(string $path): bool
  {
    try {
      $location = $this->prefixer->prefixPath($path);
      $response = $this->client->get('exists', [
        'query' => ['path' => $location]
      ]);
      $data = json_decode($response->getBody()->getContents(), true);
      return $data['path_type'] === 'file';
    } catch (Throwable $exception) {
      throw UnableToCheckFileExistence::forLocation($path, $exception);
    }
  }

  public function directoryExists(string $path): bool
  {
    try {
      $location = $this->prefixer->prefixPath($path);
      $response = $this->client->get('exists', [
        'query' => ['path' => $location]
      ]);
      $data = json_decode($response->getBody()->getContents(), true);
      return $data['path_type'] === 'directory';
    } catch (Throwable $exception) {
      throw UnableToCheckDirectoryExistence::forLocation($path, $exception);
    }
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
    try {
      $location = $this->prefixer->prefixPath($path);
      $this->client->post('upload/', [
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
    } catch (Throwable $exception) {
      throw UnableToWriteFile::atLocation($location, $exception->getMessage(), $exception);
    }
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
    try {
      $location = $this->prefixer->prefixPath($path);
      $response = $this->client->get('download', [
        'query' => ['path' => $location],
        'stream' => true
      ]);
      return $response->getBody();
    } catch (Throwable $exception) {
      throw UnableToReadFile::fromLocation($location, $exception->getMessage(), $exception);
    }
  }

  public function delete(string $path): void
  {
    try {
      $location = $this->prefixer->prefixPath($path);
      $pathType = $this->mimeType($path)->type();
      $this->client->post('delete/', [
        'form_params' => ['path' => $location],
      ]);
    } catch (Throwable $exception) {
      if ($pathType === StorageAttributes::TYPE_FILE) {
        throw UnableToDeleteFile::atLocation($location, $exception->getMessage(), $exception);
      }

      throw UnableToDeleteDirectory::atLocation($location, $exception->getMessage(), $exception);
    }
  }

  public function deleteDirectory(string $path): void
  {
    $this->delete($path);
  }

  public function createDirectory(string $path, Config $config): void
  {
    try {
      $location = $this->prefixer->prefixPath($path);
      $this->client->post('mkdir/', [
        'form_params' => ['path' => $location],
      ]);
    } catch (Throwable $exception) {
      throw UnableToCreateDirectory::atLocation($location, $exception->getMessage(), $exception);
    }
  }

  public function setVisibility(string $path, string $visibility): void {}

  public function visibility(string $path): FileAttributes
  {
    return new FileAttributes($path);
  }

  public function mimeType(string $path): FileAttributes
  {
    $location = $this->prefixer->prefixPath($path);

    if (! $this->fileExists($path)) {
      throw UnableToRetrieveMetadata::mimeType($location, 'No such file exists.');
    }

    $mimeType = $this->mimeTypeDetector->detectMimeTypeFromPath($location);

    if ($mimeType === null) {
      throw UnableToRetrieveMetadata::mimeType($path);
    }

    return new FileAttributes($path, null, null, null, $mimeType);
  }

  public function lastModified(string $path): FileAttributes
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('list', [
      'query' => ['path' => $location]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    $lastModified = $data['mtime'];

    if ($lastModified === null) {
      throw UnableToRetrieveMetadata::lastModified($path);
    }

    return new FileAttributes($path, null, null, $lastModified);
  }

  public function fileSize(string $path): FileAttributes
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('list', [
      'query' => ['path' => $location]
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    $fileSize = $data['size'];

    if ($fileSize === null) {
      throw UnableToRetrieveMetadata::fileSize($path);
    }

    return new FileAttributes($path, $fileSize);
  }

  public function listContents(string $path, bool $deep): iterable
  {
    $location = $this->prefixer->prefixPath($path);
    $response = $this->client->get('list', [
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
    try {
      $sourcePath = $this->prefixer->prefixPath($source);
      $destinationPath = $this->prefixer->prefixPath($destination);
      $this->client->post('move/', [
        'form_params' => [
          'src' => $sourcePath,
          'dest' => $destinationPath,
        ]
      ]);
    } catch (Throwable $exception) {
      throw UnableToMoveFile::fromLocationTo($sourcePath, $destinationPath, $exception);
    }
  }

  public function copy(string $source, string $destination, Config $config): void
  {
    try {
      $sourcePath = $this->prefixer->prefixPath($source);
      $destinationPath = $this->prefixer->prefixPath($destination);
      $response = $this->client->post('copy/', [
        'form_params' => [
          'src' => $sourcePath,
          'dest' => $destinationPath,
        ]
      ]);
    } catch (Throwable $exception) {
      throw UnableToCopyFile::fromLocationTo($sourcePath, $destinationPath, $exception);
    }
  }
}
