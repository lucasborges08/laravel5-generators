<?php
namespace Bronco\LaravelGenerators\Generators;

use Illuminate\Filesystem\Filesystem;

abstract class Generator {
    protected $filesystem;

    public function __construct(Filesystem $filesystem) {
        $this->filesystem = $filesystem;
    }

    public function replaceTag($tag, $value, &$content)
    {
        $content = str_replace('{{' . $tag . '}}', $value, $content);
        return $this;
    }

    public function compileNamespace(&$content)
    {
        return $this->replaceTag('app_namespace', app()->getNamespace(), $content);
    }

    public function loadParameters($filePath)
    {
        return require $filePath;
    }

}
