<?php

namespace Yiisoft\Composer\Config\Utils;

use Yiisoft\Composer\Config\Builder;
use Yiisoft\Composer\Config\Exceptions\CircularDependencyException;

/**
 * Resolver class.
 * Reorders files according to their cross dependencies
 * and resolves `$name` paths.
 */
class Resolver
{
    protected $order = [];

    protected $deps = [];

    protected $following = [];

    private $files;

    public function __construct(array $files)
    {
        $this->files = $files;

        $this->collectDeps();
        foreach (array_keys($this->files) as $name) {
            $this->followDeps($name);
        }
    }

    public function get(): array
    {
        $result = [];
        foreach ($this->order as $name) {
            $result[$name] = $this->resolveDeps($this->files[$name]);
        }

        return $result;
    }

    protected function resolveDeps(array $paths): array
    {
        foreach ($paths as &$path) {
            $dep = $this->isDep($path);
            if ($dep) {
                $path = Builder::path($dep);
            }
        }

        return $paths;
    }

    protected function followDeps(string $name): void
    {
        if (isset($this->order[$name])) {
            return;
        }
        if (isset($this->following[$name])) {
            throw new CircularDependencyException($name . ' ' . implode(',', $this->following));
        }
        $this->following[$name] = $name;
        if (isset($this->deps[$name])) {
            foreach ($this->deps[$name] as $dep) {
                $this->followDeps($dep);
            }
        }
        $this->order[$name] = $name;
        unset($this->following[$name]);
    }

    protected function collectDeps(): void
    {
        foreach ($this->files as $name => $paths) {
            foreach ($paths as $path) {
                $dep = $this->isDep($path);
                if ($dep) {
                    if (!isset($this->deps[$name])) {
                        $this->deps[$name] = [];
                    }
                    $this->deps[$name][$dep] = $dep;
                }
            }
        }
    }

    protected function isDep($path)
    {
        return 0 === strncmp($path, '$', 1) ? substr($path, 1) : false;
    }
}
