<?php

namespace Droath\ProjectX\Task\Pantheon;

use Droath\ProjectX\Pantheon\Platform\PantheonPlatformType;
use Droath\ProjectX\ProjectX;
use Droath\ProjectX\Task\EventTaskBase;

/**
 * Define the pantheon tasks.
 */
class PantheonTasks extends EventTaskBase
{
    /**
     * @var PantheonPlatformType
     */
    protected $platform;

    /**
     * Pantheon tasks constructor.
     */
    public function __construct()
    {
        $this->platform = ProjectX::getPlatformType();
    }

    /**
     * Show what pantheon account is being used.
     *
     * @param array $opts
     * @option boolean $localhost Run command on localhost.
     * @throws \Exception
     */
    public function pantheonWho($opts = [
        'localhost' => false,
    ])
    {
        $this->executeCommandHook(__METHOD__, 'before');
        $this->platform->pantheonWho($opts['localhost']);
        $this->executeCommandHook(__METHOD__, 'after');
    }

    /**
     * Authenticate a pantheon account.
     *
     * @param array $opts
     * @option string $email Pantheon account email.
     * @option string $token Pantheon account machine token.
     * @option boolean $localhost Run command on localhost.
     * @throws \Exception
     */
    public function pantheonAuth($opts = [
        'email' => null,
        'token' => null,
        'localhost' => false,
    ])
    {
        $this->executeCommandHook(__METHOD__, 'before');
        $this->platform->pantheonAuth($opts['email'], $opts['token'], false, $opts['localhost']);
        $this->executeCommandHook(__METHOD__, 'after');
    }

    /**
     * Import a pantheon environment database.
     *
     * @param array $opts
     * @option string $environment The pantheon remote environment.
     * @option string $directory The directory on where the database exported should persist.
     * @option boolean $backup Create a backup prior to exporting database.
     * @option boolean $localhost Run command on localhost.
     * @throws \Exception
     */
    public function pantheonImport($opts = [
        'environment' => null,
        'directory' => '/tmp',
        'backup' => true,
        'localhost' => false,
    ])
    {
        $this->executeCommandHook(__METHOD__, 'before');
        $this->platform->pantheonImport(
            $opts['environment'],
            $opts['directory'],
            !$opts['backup'],
            $opts['localhost']
        );
        $this->executeCommandHook(__METHOD__, 'after');
    }

    /**
     * Execute arbitrary terminus commands.
     *
     * @aliases terminus
     *
     * @param array $terminus_command
     * @param array $opts
     * @option boolean $silent No output will be rendered.
     * @option boolean $localhost Run command on localhost.
     * @throws \Exception
     */
    public function pantheonTerminus(array $terminus_command, $opts = [
        'silent' => false,
        'localhost' => false,
    ])
    {
        $this->executeCommandHook(__METHOD__, 'before');
        $this->platform->executeTerminusCommand(
            implode(' ', $terminus_command),
            $opts['silent'],
            $opts['localhost']
        );
        $this->executeCommandHook(__METHOD__, 'after');
    }
}
