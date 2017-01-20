<?php namespace Bronco\LaravelGenerators\Generators;

use Illuminate\Filesystem\Filesystem;

trait FileWriter {
    private $targetFilePath;

    public function write($content)
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists(dirname($this->getTargetFilePath())))
            $filesystem->makeDirectory(dirname($this->getTargetFilePath()), 0755, true);
            
        $filesystem->put($this->getTargetFilePath(), $content);
    }

    public function getTargetFilePath()
    {
        return $this->targetFilePath;
    }

    public function setTargetFilePath($targetFilePath)
    {
        $this->targetFilePath = $targetFilePath;

        return $this;
    }

}
