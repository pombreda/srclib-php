<?php

namespace Sourcegraph\PHP\SourceUnit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveCallbackFilterIterator;
use RuntimeException;
use UnexpectedValueException;
use SplFileInfo;
use Sourcegraph\PHP\SourceUnit\ComposerPackage\ComposerJson;
use Sourcegraph\PHP\SourceUnit\ComposerPackage\ComposerLock;
use Sourcegraph\PHP\SourceUnit;

class ComposerPackage implements SourceUnit
{
    const TYPE = 'ComposerPackage';

    private $ignored = ['vendor'];
    private $extensions = ['php'];

    protected $path;
    protected $json;
    protected $lock;

    public function __construct($path)
    {
        $this->path = $path;
        $this->json = new ComposerJson($path);
        $this->lock = new ComposerLock($path);
    }

    public function getType()
    {
        return self::TYPE;
    }

    public function getName()
    {
        return $this->json->getName();
    }

    public function getDependencies()
    {
        return $this->json->getDependencies();
    }

    public function getNamespaces()
    {
        return $this->json->getNamespaces();
    }

    public function getFiles()
    {
        $realpath = realpath($this->path) . DIRECTORY_SEPARATOR;
        $files = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($realpath),
            [$this, 'getFilesFilter']
        );

        $output = [];
        foreach (new RecursiveIteratorIterator($files) as $file) {
            $output[] = str_replace($realpath, '', $file->getPathname());
        }

        return $output;
    }

    public function getFilesFilter(SplFileInfo $current, $key, $iterator)
    {
        if ($this->isFileOrDirectoryIgnored($current)) {
            return false;
        }

        if ($iterator->hasChildren()) {
            return true;
        }

        if ($this->isValidFile($current)) {
            return true;
        }

        return false;
    }

    protected function isFileOrDirectoryIgnored(SplFileInfo $file)
    {
        return in_array($file->getFilename(), $this->ignored);
    }

    protected function isValidFile(SplFileInfo $file)
    {
        if (!$file->isFile()) {
            return false;
        }

        $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
        return in_array($ext, $this->extensions);
    }

    public function getPackageName($defPath)
    {
        $namespace = str_replace('/', '\\', $defPath);

        foreach ($this->json->getNamespaces() as $ns) {
            if (stripos($namespace, $ns) !== false) {
                return $this->getName();
            }
        }

        return $this->lock->getPackageName($namespace);
    }

    public function getRepository($packageName)
    {
        return $this->lock->getRepository($packageName);
    }

    public function getCommit($packageName)
    {
        return $this->lock->getCommit($packageName);
    }

    public function getRequiredVersion($packageName)
    {
        return $this->json->getRequiredVersion($packageName);
    }

    public function toArray()
    {
        return [
            'Name' => $this->getName(),
            'Type' => self::TYPE,
            'Globs' => [],
            'Files' => $this->getFiles(),
            'Dependencies' => $this->getDependencies(),
            'Data' => ['namespaces' => $this->getNamespaces()],
            'Ops' => ['depresolve' => null, 'graph' => null]
        ];
    }
}
