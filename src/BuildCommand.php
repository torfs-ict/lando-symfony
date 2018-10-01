<?php

namespace Lando;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class BuildCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('build');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $this->settings->getRoot();
        $io = new SymfonyStyle($input, $output);

        $dist = "$root/vendor/torfs-ict/lando-symfony/src/Resources/lando.yml.tpl";
        $sample = "$root/vendor/torfs-ict/lando-symfony/samples/lando.yml.dist";
        $user = "$root/lando.yml";
        $build = "$root/.lando.yml";

        $io->title('Lando environment builder');
        // Generate default user file if one doesn't exist
        if (!file_exists($user)) {
            $ret = $io->confirm('No <fg=white;options=bold>lando.yml</> found, would you like to create one?');
            if ($ret) {
                $project = null;
                $default = basename($root);
                // Replace dots and underscores by hyphens
                $default = str_replace(['.', '_'], '-', $default);
                while (is_null($project)) {
                    $project = $io->ask('Please enter your project name', $default, function ($name) use ($io) {
                        $name = strtolower($name);
                        $ret = preg_match('/^([a-z0-9\-]+)$/', $name);
                        if ($ret === 1) return $name;
                        $io->writeln('');
                        $io->writeln('<fg=red> ! <fg=red;options=bold>[INVALID]</> Project name can only contain (lowercase) <fg=white;options=bold>alphanumeric characters</> and <fg=white;options=bold>hyphens</>.</>');
                        return null;
                    });
                }
                $sample = Yaml::parseFile($sample);
                $replace = $sample['name'];
                $sample['name'] = $project;
                unset($sample['tooling']);
                foreach ($sample['proxy'] as $service => $domains) {
                    foreach ($domains as $index => $domain) ;
                    $sample['proxy'][$service][$index] = str_replace($replace, $project, $domain);
                }
                file_put_contents($user, Yaml::dump($sample, 10, 2));
                $this->writeBlock(
                    $io,
                    '[OK] Successfully created your environment in <bg=green;options=bold>' . basename($user) . '</>.',
                    'black', 'green'
                );
            } else {
                exit(11);
            }
        }

        $dist = Yaml::parseFile($dist);
        $user = Yaml::parseFile($user);
        if (!array_key_exists('name', $user) || empty(trim($user['name']))) {
            // Validation: check if the project name is set
            $this->writeBlock($io,'No <bg=red;options=bold>project name</> set in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
            exit(12);
        } else {

            // Check if any of our services were marked for removal in the user file
            if (array_key_exists('services', $user) && is_array($user['services'])) {
                foreach ($user['services'] as $service => $value) {
                    if (!is_null($value)) continue;
                    if (array_key_exists($service, $dist['services'])) unset($dist['services'][$service]);
                    if (($index = array_search($service, $dist['proxy'])) !== false) unset($dist['proxy'][$index]);
                    if (array_key_exists($service, $user['proxy'])) unset($user['proxy'][$service]);
                    unset($user['services'][$service]);
                }
            }

            // Validation: at least one proxy URL should be set for each service
            if (!array_key_exists('proxy', $user) || !is_array($user['proxy'])) {
                $this->writeBlock($io, 'No <bg=red;options=bold>proxy domains</> set in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
                exit(13);
            }
            foreach ($dist['proxy'] as $service) {
                if (!array_key_exists($service, $user['proxy']) || !is_array($user['proxy'][$service])) {
                    $this->writeBlock($io, 'No <bg=red;options=bold>proxy domain(s)</> set for service <bg=red;options=bold>' . $service . '</> in <bg=red;options=bold>lando.yml</>.', 'white', 'red');
                    exit(14);
                }
            }
            unset($dist['proxy']);

            file_put_contents($build, Yaml::dump(array_merge_recursive($dist, $user), 10, 2));
            $this->writeBlock(
                $io,
                '[OK] Successfully created your environment in <bg=green;options=bold>.lando.yml</>.',
                'black', 'green'
            );
        }
    }

    /**
     * @param SymfonyStyle $io
     * @param string $message
     * @param string $foreground
     * @param string $background
     * @param bool $pad
     */
    private function writeBlock(SymfonyStyle $io, string $message, string $foreground = 'white', string $background = 'black', bool $pad = true) {
        $message = " $message";
        $blank = str_repeat(' ', SymfonyStyle::MAX_LINE_LENGTH);
        $append = str_repeat(' ', SymfonyStyle::MAX_LINE_LENGTH - strlen(strip_tags($message)));
        $messages = ["<fg=$foreground;bg=$background>"];
        if ($pad) $messages[] = $blank;
        $messages[] = $message . $append;
        if ($pad) $messages[] = $blank;
        $messages[] = '</>';
        $io->writeln($messages);
    }
}