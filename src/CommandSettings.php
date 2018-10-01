<?php

namespace Lando;

use Exception;

class CommandSettings
{
    /**
     * @var string
     */
    private $root;
    /**
     * @var array
     */
    private $composer;
    /**
     * @var string
     */
    private $project;

    /**
     * @param string $directory
     * @return string
     * @throws Exception
     */
    private function findProjectRoot(string $directory): string
    {
        $ret = null;
        do {
            $directory = realpath(dirname($directory));
            if (is_file("$directory/composer.json")) {
                return $directory;
            }
        } while ($directory != '/');
        throw new Exception('Unable to find composer.json');
    }

    /**
     * CommandSettings constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $this->root = $this->findProjectRoot(LANDO_COMPOSER_INSTALL);
        $this->composer = json_decode(file_get_contents("{$this->root}/composer.json"), true);
        if (!array_key_exists('name', $this->composer)) $this->composer['name'] = '';
        $this->project = $this->composer['name'];
    }

    /**
     * @return string
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * @return array
     */
    public function getComposer(): array
    {
        return $this->composer;
    }

    /**
     * @return string
     */
    public function getProject(): string
    {
        return $this->project;
    }

    public function isDevelopmentEnvironment(): bool
    {
        return $this->project == 'torfs-ict/lando-symfony';
    }
}