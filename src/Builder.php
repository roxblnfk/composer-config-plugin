<?php

namespace Yiisoft\Composer\Config;

use Yiisoft\Composer\Config\Configs\ConfigFactory;
use Yiisoft\Composer\Config\Utils\Resolver;

/**
 * Builder assembles config files.
 */
class Builder
{
    /**
     * @var string path to output assembled configs
     */
    protected string $outputDir;

    /**
     * @var array configurations
     */
    protected $configs = [];

    private const OUTPUT_DIR_SUFFIX = '-output';

    public function __construct(string $outputDir = null)
    {
        $this->setOutputDir($outputDir);
    }

    public function createAlternative($name): Builder
    {
        $dir = $this->outputDir . DIRECTORY_SEPARATOR . $name;
        $alt = new static($dir);
        foreach (['aliases', 'packages'] as $key) {
            $alt->configs[$key] = $this->getConfig($key)->clone($alt);
        }

        return $alt;
    }

    public function setOutputDir(?string $outputDir)
    {
        $this->outputDir = $outputDir
            ? static::buildAbsPath(static::findBaseDir(), $outputDir)
            : static::findOutputDir();
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public static function rebuild($outputDir = null)
    {
        $builder = new self($outputDir);
        $files = $builder->getConfig('__files')->load();
        $builder->buildUserConfigs($files->getValues());
    }

    public function rebuildUserConfigs()
    {
        $this->getConfig('__files')->load();
    }

    /**
     * Returns default output dir.
     * @param string $baseDir path to project base dir
     * @return string
     */
    public static function findOutputDir(string $baseDir = null): string
    {
        $baseDir = $baseDir ?: static::findBaseDir();
        $path = $baseDir . DIRECTORY_SEPARATOR . 'composer.json';
        $data = @json_decode(file_get_contents($path), true);
        $dir = $data['extra'][Package::EXTRA_OUTPUT_DIR_OPTION_NAME] ?? null;

        return $dir ? static::buildAbsPath($baseDir, $dir) : static::defaultOutputDir($baseDir);
    }

    public static function findBaseDir(): string
    {
        $list = explode(DIRECTORY_SEPARATOR, __DIR__);
        return $list[3] !== 'vendor' ? getcwd() : dirname(__DIR__, 4);
    }

    /**
     * Returns default output dir.
     * @param string $baseDir path to base directory
     * @return string
     */
    public static function defaultOutputDir(string $baseDir = null): string
    {
        if ($baseDir) {
            $dir = $baseDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'yiisoft' . DIRECTORY_SEPARATOR . basename(dirname(__DIR__));
        } else {
            $dir = \dirname(__DIR__);
        }

        return $dir . static::OUTPUT_DIR_SUFFIX;
    }

    /**
     * Returns full path to assembled config file.
     * @param string $filename name of config
     * @param string $baseDir path to base dir
     * @return string absolute path
     */
    public static function path(string $filename, string $baseDir = null): string
    {
        return static::buildAbsPath(static::findOutputDir($baseDir), $filename . '.php');
    }

    public static function buildAbsPath(string $dir, string $file): string
    {
        if ($file === '') {
            throw new \Exception('Empty file name');
        }
        if ($file[0] !== '.' && strpos($file, ':') !== false) {
            return $file;
        }
        return $file[0] === DIRECTORY_SEPARATOR
            ? $dir . $file
            : $dir . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * Builds all (user and system) configs by given files list.
     * @param null|array $files files to process: config name => list of files
     */
    public function buildAllConfigs(array $files)
    {
        $this->buildUserConfigs($files);
        $this->buildSystemConfigs($files);
    }

    /**
     * Builds configs by given files list.
     * @param null|array $files files to process: config name => list of files
     */
    public function buildUserConfigs(array $files): array
    {
        $resolver = new Resolver($files);
        $files = $resolver->get();
        foreach ($files as $name => $paths) {
            $this->getConfig($name)->load($paths)->build()->write();
        }

        return $files;
    }

    public function buildSystemConfigs(array $files): void
    {
        $this->getConfig('__files')->setValues($files);
        foreach (['__rebuild', '__files', 'aliases', 'packages'] as $name) {
            $this->getConfig($name)->build()->write();
        }
    }

    public function getOutputPath($name): string
    {
        return $this->outputDir . DIRECTORY_SEPARATOR . $name . '.php';
    }

    protected function createConfig($name)
    {
        $config = ConfigFactory::create($this, $name);
        $this->configs[$name] = $config;

        return $config;
    }

    public function getConfig(string $name)
    {
        if (!isset($this->configs[$name])) {
            $this->configs[$name] = $this->createConfig($name);
        }

        return $this->configs[$name];
    }

    public function getVar($name, $key)
    {
        $config = $this->configs[$name] ?? null;
        if (empty($config)) {
            return null;
        }

        return $config->getValues()[$key] ?? null;
    }

    public function getVars(): array
    {
        $vars = [];
        foreach ($this->configs as $name => $config) {
            $vars[$name] = $config->getValues();
        }

        return $vars;
    }

    public function mergeAliases(array $aliases): void
    {
        $this->getConfig('aliases')->mergeValues($aliases);
    }

    public function setPackage(string $name, array $data): void
    {
        $this->getConfig('packages')->setValue($name, $data);
    }
}
