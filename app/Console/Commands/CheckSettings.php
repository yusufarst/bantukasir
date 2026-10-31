<?php

namespace App\Console\Commands;

use App\Domain\Operations\RequiredSettings;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * The start-up check of the settings (SECURITY_MODEL SEC-SECRET-03; ENVIRONMENTS
 * section 11; TASK-012 scope item 3), which docker/app/entrypoint.sh runs before
 * anything else: it reads the process's environment, not the configuration, which
 * may be cached from the container's previous start. Each problem is one line with
 * the setting's name and kind, never its value; any problem ends with a failure,
 * which refuses the start. Its text is for the operator, in English like the
 * runbooks.
 *
 * --describe lists what the role needs for the image checks, which then refuse
 * each setting in turn (scripts/checks/image-checks.sh).
 */
final class CheckSettings extends Command
{
    protected $signature = 'settings:check
        {--describe : List the required and forbidden settings and the known defaults of a role}
        {--role= : The role that --describe lists, web or backup}';

    protected $description = 'Check at start-up that the settings of the process role exist and are not defaults';

    public function handle(): int
    {
        if ($this->option('describe') === true) {
            return $this->describe();
        }

        $problems = RequiredSettings::problems(getenv());

        foreach ($problems as $name => $kind) {
            $this->line("SETTING_REFUSED {$name} {$kind}");
        }

        return $problems === [] ? SymfonyCommand::SUCCESS : SymfonyCommand::FAILURE;
    }

    private function describe(): int
    {
        $option = $this->option('role');
        $role = is_string($option) ? $option : '';
        $required = RequiredSettings::required($role);

        if ($required === []) {
            $this->line('SETTING_REFUSED APP_PROCESS_ROLE '.RequiredSettings::INVALID);

            return SymfonyCommand::FAILURE;
        }

        foreach ($required as $name) {
            $this->line("required {$name}");

            foreach (RequiredSettings::defaults($name) as $default) {
                $this->line("default {$name} {$default}");
            }
        }

        foreach (RequiredSettings::forbidden($role) as $name) {
            $this->line("forbidden {$name}");
        }

        return SymfonyCommand::SUCCESS;
    }
}
