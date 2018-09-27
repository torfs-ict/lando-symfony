<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

$app = new Application('echo', '1.0.0');
$app
    ->register('build')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($app) {
        $io = new SymfonyStyle($input, $output);

        $block = function(string $message, string $foreground = 'white', string $background = 'black', bool $pad = true) use ($io) {
            $message = " $message";
            $blank = str_repeat(' ', SymfonyStyle::MAX_LINE_LENGTH);
            $append = str_repeat(' ', SymfonyStyle::MAX_LINE_LENGTH - strlen(strip_tags($message)));
            $messages = ["<fg=$foreground;bg=$background>"];
            if ($pad) $messages[] = $blank;
            $messages[] = $message . $append;
            if ($pad) $messages[] = $blank;
            $messages[] = '</>';
            $io->writeln($messages);
        };

        $dist = __DIR__ . '/lando.yml.tpl';
        $user = __DIR__ . '/../lando.yml';
        $build = __DIR__ . '/../.lando.yml';

        $io->title('Lando environment builder');
        // Generate default user file if one doesn't exist
        if (!file_exists($user)) {
            //$block('No <bg=red;options=bold>lando.yml</> found.', 'white', 'red');
            $ret = $io->confirm('No <fg=white;options=bold>lando.yml</> found, would you like to create one?');
            if ($ret) {
                $project = null;
                while (is_null($project)) {
                    $default = basename(dirname(__DIR__));
                    // Replace dots and underscores by hyphens
                    $default = str_replace(['.', '_'], '-', $default);
                    $project = $io->ask('Please enter your project name', $default, function($name) use ($io) {
                        $name = strtolower($name);
                        $ret = preg_match('/^([a-z0-9\-]+)$/', $name);
                        if ($ret === 1) return $name;
                        $io->writeln('');
                        $io->writeln('<fg=red> ! <fg=red;options=bold>[INVALID]</> Project name can only contain (lowercase) <fg=white;options=bold>alphanumeric characters</> and <fg=white;options=bold>hyphens</>.</>');
                        return null;
                    });
                }
                $sample = Yaml::parseFile(__DIR__ . '/lando.yml.dist');
                $replace = $sample['name'];
                $sample['name'] = $project;
                unset($sample['tooling']);
                foreach($sample['proxy'] as $service => $domains) {
                    foreach($domains as $index => $domain);
                    $sample['proxy'][$service][$index] = str_replace($replace, $project, $domain);
                }
                file_put_contents($user, Yaml::dump($sample, 10, 2));
                $block(
                    '[OK] Successfully created your environment in <bg=green;options=bold>' . basename($user) . '</>.',
                    'black', 'green'
                );
            } else {
                exit(1);
            }
        }

        $dist = Yaml::parseFile($dist);
        $user = Yaml::parseFile($user);
        if (!array_key_exists('name', $user) || empty(trim($user['name']))) {
            // Validation: check if the project name is set
            $block('No <bg=red;options=bold>project name</> set in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
            exit(2);
        } else {

            // Validation: at least one proxy URL should be set for each service
            if (!array_key_exists('proxy', $user) || !is_array($user['proxy'])) {
                $block('No <bg=red;options=bold>proxy domains</> set in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
                exit(3);
            }
            foreach($dist['proxy'] as $service) {
                if (!array_key_exists($service, $user['proxy']) || !is_array($user['proxy'][$service])) {
                    $block('No <bg=red;options=bold>proxy domain(s)</> set for service <bg=red;options=bold>' . $service . '</> in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
                    exit(4);
                }
            }
            unset($dist['proxy']);

            file_put_contents($build, Yaml::dump(array_merge_recursive($dist, $user), 10, 2));
            $block(
                '[OK] Successfully created your environment in <bg=green;options=bold>.lando.yml</>.',
                'black', 'green'
            );
        }
    })
    ->getApplication()
    ->setDefaultCommand('build', true)
    ->run();