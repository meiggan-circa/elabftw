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
use League\Flysystem\PathPrefixer;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

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
   return false;
  }

  public function directoryExists(string $path): bool
  {
    return false;
  }

  public function write(string $path, string $contents, Config $config): void
  {

  }

  public function writeStream(string $path, $contents, Config $config): void
  {

  }

  public function read(string $path): string
  {
    return "";
  }

  public function readStream(string $path)
  {

  }

  public function delete(string $path): void
  {

  }

  public function deleteDirectory(string $path): void
  {

  }

  public function createDirectory(string $path, Config $config): void
  {

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
    return new FileAttributes($path);
  }

  public function lastModified(string $path): FileAttributes
  {
    return new FileAttributes($path);
  }

  public function fileSize(string $path): FileAttributes
  {
    return new FileAttributes($path);
  }

  public function listContents(string $path, bool $deep): iterable
  {
    return [];
  }

  public function move(string $source, string $destination, Config $config): void
  {

  }

  public function copy(string $source, string $destination, Config $config): void
  {

  }
}
