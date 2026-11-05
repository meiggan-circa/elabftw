<?php

declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Enums\Storage;
use League\Flysystem\Filesystem;
use Override;

final class CreateUploadFromHDFS extends CreateUpload
{
    #[Override]
    public function getSourceFs(): Filesystem
    {
        return Storage::from(Storage::HDFS->value)->getStorage()->getFs();
    }

    #[Override]
    public function getTmpFilePath(): string
    {
        return $this->filePath;
    }
}
