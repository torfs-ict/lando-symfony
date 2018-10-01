<?php

namespace Lando;

use Matomo\Ini\IniReader;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

class TestCommand extends AbstractCommand
{
    /**
     * @var bool
     */
    private $cleanup;

    protected function configure()
    {
        $this
            ->setName('test')
            ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not clean up');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->cleanup = !$input->getOption('no-cleanup');
        $root = $this->settings->getRoot();
        $test = "$root/test";
        $artifact = "$root/artifact.zip";
        // Remove possible orphan directory
        if (is_dir($test)) $this->tryCommand($output, ['rm', '-r', $test], 'Removing orphaned directory');
        // Create artifact package
        chdir($root);
        $this->tryCommand(
            $output,
            ['zip', 'artifact.zip', '.', '-r', '-x', '/.git/*', '-x', '/.idea/*', '-x', '/test/*', '-x', '/vendor/*', '-x', '/artifact.zip'],
            'Creating artifact package'
        );
        // Create test project
        $this->tryCommand($output, ['composer', 'create-project', 'symfony/website-skeleton', $test], 'Creating test project');
        chdir($test);
        // Adding artifact repository
        $this->tryCallable($output, function() use($test, $artifact) {
            $package = array_merge($this->settings->getComposer(), [
                'version' => 'master',
                'dist' => [
                    'type' => 'zip',
                    'url' => $artifact,
                    'reference' => 'master'
                ]
            ]);
            $json = json_decode(file_get_contents("$test/composer.json"), true);
            $json['repositories'] = [[
                'type' => 'package',
                'package' => $package
            ]];
            return file_put_contents("$test/composer.json", json_encode($json, JSON_PRETTY_PRINT)) !== false;
        }, 'Adding artifact repository to test project');
        // Clear composer cache
        $this->tryCommand($output, ['composer', 'clear-cache'], 'Clearing composer cache');
        // Install our artifacted package
        $this->tryCommand($output, ['composer', 'require', 'torfs-ict/lando-symfony:dev-master'], 'Install artifact package in test project');
        // Create test/lando.yaml
        $this->tryCommand($output, ['cp', $root . '/samples/lando.yml.test', $test . '/lando.yml'], 'Creating test environment definition');
        // Create test/.env
        $this->tryCallable($output, function() use ($root, $test) {
            $dist = (new IniReader())->readFile("$root/samples/.env.dist");
            foreach($dist as $key => $value) {
                if ($key == 'APP_ENV') continue;
                if (empty($value)) $value = $_ENV[$key];

                file_put_contents("$test/.env", "$key=$value\n", FILE_APPEND | LOCK_EX);
            }
            return true;
        }, 'Creating <options=bold>.env</> file for the test project');
        // Build and start the environment
        $this->tryCommand($output, [$test . '/vendor/bin/lando'], 'Building test environment definition');
        $this->tryCommand($output, ['lando', 'destroy', '-y'], 'Destroying possible orphaned test environment');
        $this->tryCallable($output, function() {
            $output = [];
            $exit = 0;
            exec('lando rebuild -y 2> /dev/null', $output, $exit);
            return $exit === 0;
        }, 'Building test environment');
        // Check if all PHP extensions have been installed
        exec('lando php -m', $extensions);
        foreach(['blackfire', 'gmp', 'igbinary', 'sockets', 'xdebug'] as $extension) {
            $this->tryCallable($output, function() use ($extensions, $extension) {
                return array_search($extension, $extensions, true) !== false;
            }, 'Checking if <options=bold>' . $extension . '</> extension is installed');
        }
        // Test connection to all services
        $connections = [
            ['nginx', 443], ['appserver', 9000], ['database', 3306], ['phpmyadmin', 80], ['blackfire', 8707],
            ['mailhog', 80], ['mailhog', 1025], ['memcached', 11211], ['chrome-headless', 9222], ['unoconv', 3000],
            ['pdftk', 80], ['elk', 5601]
        ];
        while(list($host, $port) = current($connections)) {
            $this->tryCallable($output, function() use ($host, $port) {
                $cmd = "lando ssh -c 'nc -vz $host $port 2> /dev/null' 2> /dev/null";
                exec($cmd, $output, $exit);
                return $exit == '0';
            }, sprintf('Checking connection to <options=bold>%s</>, port <options=bold>%d</>', $host, $port));
            next($connections);
        }
        // Clean up
        if ($this->cleanup) $this->cleanUp($output);
    }

    private function cleanUp(OutputInterface $output)
    {
        $root = $this->settings->getRoot();
        $test = "$root/test";
        $artifact = "$root/artifact.zip";

        if (is_dir($test)) {
            chdir($test);
            // Destroy lando environment
            $this->tryCommand($output, ['lando', 'destroy', '-y'], '[!CLEANUP] Destroying test environment', false);
            // Remove the test directory
            $this->tryCommand($output, ['rm', '-r', $test], '[!CLEANUP] Removing test project', false);
            // Remove the artifact package
            chdir($root);
            $this->tryCommand($output, ['rm', $artifact], '[!CLEANUP] Removing artifact package', false);
        }
    }

    /**
     * @param OutputInterface $output
     * @param array $cmd
     * @param string $description
     * @param bool $cleanup
     * @return string
     * @throws Throwable
     */
    private function tryCommand(OutputInterface $output, array $cmd, string $description, bool $cleanup = true): string
    {
        /** @var ProcessHelper $helper */
        $helper = $this->getApplication()->getHelperSet()->get('process');
        try {
            $process = (new Process($cmd))->setTimeout(null);
            $output->write([$description, '... ']);
            $helper->mustRun($output, $process);
            $output->writeln("<fg=green;options=bold>\u{2713}</>");
            return $process->getOutput();
        } catch (Throwable $e) {
            $output->writeln("<fg=red;options=bold>\u{2717}</>");
            if ($cleanup && $this->cleanup) $this->cleanUp($output);
            throw $e;
        }
    }

    /**
     * @param OutputInterface $output
     * @param callable $callback
     * @param string $description
     * @param bool $cleanup
     * @return $this
     * @throws Throwable
     */
    private function tryCallable(OutputInterface $output, callable $callback, string $description, bool $cleanup = true): AbstractCommand
    {
        try {
            $output->write([$description, '... ']);
            $result = $callback();
        } catch (Throwable $e) {
            $result = false;
        }
        $output->writeln($result === true ? "<fg=green;options=bold>\u{2713}</>" : "<fg=red;options=bold>\u{2717}</>");
        if (isset($e)) {
            if ($cleanup && $this->cleanup) $this->cleanUp($output);
            throw($e);
        } elseif ($result !== true) {
            if ($cleanup && $this->cleanup) $this->cleanUp($output);
            exit(1);
        }
        return $this;
    }
}