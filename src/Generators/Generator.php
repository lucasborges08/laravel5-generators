<?php
namespace Bronco\LaravelGenerators\Generators;

abstract class Generator {
    public function replaceTag($tag, $value, &$content)
    {
        $content = str_replace('{{' . $tag . '}}', $value, $content);
        return $this;
    }

    public function loadParameters($filePath)
    {
        return require $filePath;
    }

}
