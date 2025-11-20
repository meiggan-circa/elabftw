<?php

declare(strict_types=1);

namespace Elabftw\Storage;

use Elabftw\Elabftw\Env;
use League\Flysystem\FilesystemAdapter;
use Elabftw\Storage\HDFSAdapter;
use Override;

/**
 * For HDFS stored uploads
 */
class HDFS extends AbstractStorage
{
  protected const string FOLDER = 'uploads';

  #[Override]
  protected function getAdapter(): FilesystemAdapter
  {
    return new HDFSAdapter('/elabftw/' . static::FOLDER);
  }
}
