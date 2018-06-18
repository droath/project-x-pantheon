<?php

namespace Droath\ProjectX\Pantheon\Command;

use Droath\ProjectX\ComposerCommandBuilder;

/**
 * Define terminus executable command.
 */
class TerminusCommand extends ComposerCommandBuilder
{
    const DEFAULT_EXECUTABLE = 'terminus';

    /**
     * Command options.
     *
     * @var array
     */
    protected $commandOptions = [];

    /**
     * Set command option.
     *
     * @param $option
     *   The command option key.
     * @param null|string $value
     *   The command option value.
     * @param null $delimiter
     *   The command option delimiter.
     * @return $this
     */
    public function setCommandOption($option, $value = null , $delimiter = null)
    {
        $delimiter = isset($delimiter) ? $delimiter : " ";

        $this->commandOptions[] = strpos($option, '-') !== false
            ? "{$option}{$delimiter}{$value}"
            : "--{$option}{$delimiter}{$value}";

        return $this;
    }

    /**
     * Get command options.
     *
     * @return string
     */
    public function getCommandOptions()
    {
        return implode(' ', $this->commandOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $commands = [];

        foreach ($this->commands as $command) {
            $commands[] = trim("{$this->getEnvVariable()} {$this->executable} {$this->getOptions()} {$command} {$this->getCommandOptions()}");
        }
        $this->commands = [];

        return implode(' && ', $commands);
    }
}
