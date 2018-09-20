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

        $block = function(string $message, string $foreground = 'white', string $background = 'black') use ($io) {
            $message = " $message";
            $blank = str_repeat(' ', SymfonyStyle::MAX_LINE_LENGTH);
            $append = str_repeat(' ', SymfonyStyle::MAX_LINE_LENGTH - strlen(strip_tags($message)));
            $io->writeln([
                "<fg=$foreground;bg=$background>",
                $blank,
                $message . $append,
                $blank,
                '</>'
            ]);
        };

        $dist = __DIR__ . '/.lando.yml.dist';
        $user = __DIR__ . '/../lando.yml';
        $build = __DIR__ . '/../.lando.yml';
        $yaml = Yaml::parseFile($user);

        $io->title('Lando environment builder');
        if (!file_exists($user)) {
            // Validation: check if the user-defined file exists
            $block('No <bg=red;options=bold>lando.yml</> found.', 'white', 'red');
            exit(1);
        } elseif (!array_key_exists('name', $yaml) || empty(trim($yaml['name']))) {
            // Validation: check if the project name is set
            $block('No <bg=red;options=bold>project name</> set in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
            exit(2);
        } else {
            $dist = Yaml::parseFile($dist);
            $user = Yaml::parseFile($user);
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