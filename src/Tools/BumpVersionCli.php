<?php

namespace AppSupport\Tools;

/**
 * Command line of php-bump-version: changes the version of the project in the current directory. It is
 * the PHP version of a script that did it for the alire.toml of an Ada crate.
 */
final class BumpVersionCli
{
    public const USAGE = <<<'EOT'
php-bump-version - bump the semantic version of a PHP project.

The version is read from the "version" of composer.json, and has to be of the
form MAJOR.MINOR.PATCH. At most one bump level may be requested:

    --major    MAJOR+1, MINOR and PATCH reset to 0  (1.4.2 -> 2.0.0)
    --minor    MINOR+1, PATCH reset to 0            (1.4.2 -> 1.5.0)
    --patch    PATCH+1                              (1.4.2 -> 1.4.3)

The new version is written back into composer.json, and the 'version' and the
'date' of the 'info' array of config/app.php (if it exists) are updated with
the new version and the date of today. Only those values are changed.

With --sync and no bump level, the version of composer.json is copied to
config/app.php (with the date, only if the version there was different).

With no option at all, the current version is printed.

Usage:
    php-bump-version [--major | --minor | --patch] [--sync] [--date YYYY-MM-DD] [--dry-run]

Options:
    --major             bump the major component
    --minor             bump the minor component
    --patch             bump the patch component
    --sync              copy the current version to config/app.php (no bump)
    --date YYYY-MM-DD   date to write in config/app.php (default: today, in the
                        time zone of PHP)
    --dry-run           show what would change, without writing any file
    -h, --help          show this help and exit

It has to be run from the root of the project.
EOT;

    /** @var resource */
    private $out;
    /** @var resource */
    private $err;

    /**
     * @param resource|null $out where the messages go (the standard output by default)
     * @param resource|null $err where the errors go (the standard error by default)
     */
    public function __construct($out = null, $err = null)
    {
        $this->out = $out ?? STDOUT;
        $this->err = $err ?? STDERR;
    }

    /**
     * @param string[] $args the arguments of the command line, without the name of the program
     * @param string $dir the root of the project
     * @return int the exit code of the program: 0 if all went well
     */
    public function run(array $args, string $dir): int
    {
        try {
            return $this->execute($args, rtrim($dir, '/'));
        } catch (\InvalidArgumentException $error) {
            fwrite($this->err, 'error: ' . $error->getMessage() . "\n");

            return 1;
        }
    }

    private function execute(array $args, string $dir): int
    {
        $level = null;
        $sync = false;
        $dryRun = false;
        $date = null;
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (in_array($arg, ['--major', '--minor', '--patch'], true)) {
                if ($level !== null) {
                    throw new \InvalidArgumentException('only one of --major, --minor, --patch allowed');
                }
                $level = substr($arg, 2);
            } elseif ($arg === '--sync') {
                $sync = true;
            } elseif ($arg === '--dry-run') {
                $dryRun = true;
            } elseif ($arg === '--date' || str_starts_with($arg, '--date=')) {
                $value = $arg === '--date' ? ($args[++$i] ?? null) : substr($arg, strlen('--date='));
                if ($value === null) {
                    throw new \InvalidArgumentException('--date needs a value');
                }
                $date = $this->date($value);
            } elseif ($arg === '-h' || $arg === '--help') {
                fwrite($this->out, self::USAGE . "\n");

                return 0;
            } else {
                throw new \InvalidArgumentException("unknown argument: {$arg} (see --help)");
            }
        }

        // The current version
        $composerFile = "{$dir}/composer.json";
        if (!is_file($composerFile)) {
            throw new \InvalidArgumentException('run this from the root of the project (no composer.json here)');
        }
        $composer = file_get_contents($composerFile);
        $current = VersionBumper::readComposerVersion($composer);
        if ($current === null) {
            throw new \InvalidArgumentException('could not read the "version" of composer.json (add one, e.g. "version": "0.1.0")');
        }

        if ($level === null && !$sync) {
            if ($date !== null) {
                throw new \InvalidArgumentException('--date needs --major, --minor, --patch or --sync');
            }
            fwrite($this->out, $current . "\n");

            return 0;
        }

        $new = $level !== null ? VersionBumper::bump($current, $level) : $current;
        VersionBumper::parse($new);

        // Everything is prepared before anything is written
        $writes = [];
        $prefix = $dryRun ? '(dry run) ' : '';
        if ($level !== null) {
            $writes[$composerFile] = VersionBumper::writeComposerVersion($composer, $new);
            fwrite($this->out, "{$prefix}Bumped version: {$current} -> {$new}   (composer.json)\n");
        }

        $configFile = "{$dir}/config/app.php";
        if (is_file($configFile)) {
            $source = file_get_contents($configFile);
            $before = VersionBumper::updateAppConfig($source, null, null);
            if (!$before['version']['found']) {
                fwrite($this->out, "{$prefix}Note: config/app.php has no 'version' in 'info': not updated\n");
            } else {
                // The date goes with a change of version (always when there is a bump)
                $changesVersion = $level !== null || $before['version']['old'] !== $new;
                $newDate = $changesVersion || $date !== null ? ($date ?? date('Y-m-d')) : null;
                $after = VersionBumper::updateAppConfig($source, $changesVersion ? $new : null, $newDate);

                $changes = [];
                if ($changesVersion) {
                    $changes[] = "version {$before['version']['old']} -> {$new}";
                }
                if ($newDate !== null && $after['date']['found']) {
                    $changes[] = "date {$after['date']['old']} -> {$newDate}";
                }
                if ($newDate !== null && !$after['date']['found']) {
                    fwrite($this->out, "{$prefix}Note: config/app.php has no 'date' in 'info': the date is not updated\n");
                }

                if ($after['source'] !== $source) {
                    $writes[$configFile] = $after['source'];
                    fwrite($this->out, "{$prefix}Updated config/app.php: " . implode(', ', $changes) . "\n");
                } else {
                    fwrite($this->out, "{$prefix}config/app.php already has the version {$new}\n");
                }
            }
        }

        if (!$dryRun) {
            foreach ($writes as $file => $content) {
                $this->write($file, $content);
            }
        }

        return 0;
    }

    private function date(string $value): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) || !checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1])) {
            throw new \InvalidArgumentException("invalid date '{$value}'. Expected YYYY-MM-DD");
        }

        return $value;
    }

    /** Replaces a file, keeping its permissions (the new content is complete before it takes the place of the old one) */
    private function write(string $file, string $content): void
    {
        $temporary = $file . '.tmp' . getmypid();
        if (file_put_contents($temporary, $content) === false) {
            throw new \InvalidArgumentException("could not write {$file}");
        }
        chmod($temporary, fileperms($file) & 0777);
        if (!rename($temporary, $file)) {
            unlink($temporary);
            throw new \InvalidArgumentException("could not write {$file}");
        }
    }
}
