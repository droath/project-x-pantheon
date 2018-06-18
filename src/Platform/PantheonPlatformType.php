<?php

namespace Droath\ProjectX\Pantheon\Platform;

use Droath\ConsoleForm\Field\TextField;
use Droath\ConsoleForm\Form;
use Droath\ProjectX\CommandInterface;
use Droath\ProjectX\Config\ComposerConfig;
use Droath\ProjectX\Engine\DockerEngineType;
use Droath\ProjectX\Pantheon\Command\TerminusCommand;
use Droath\ProjectX\Pantheon\Store\PantheonTerminusTokenStore;
use Droath\ProjectX\Platform\PlatformType;
use Droath\ProjectX\Project\Command\WrapperCommand;
use Droath\ProjectX\Project\DrupalProjectType;
use Droath\ProjectX\Project\PhpProjectType;
use Droath\ProjectX\TaskResultTrait;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Define pantheon platform type.
 */
class PantheonPlatformType extends PlatformType implements PantheonPlatformInterface
{
    use TaskResultTrait;

    /**
     * @var PantheonTerminusTokenStore
     */
    protected $authStore;

    /**
     * Default terminus version
     */
    const DEFAULT_TERMINUS_VERSION = '^1.8';

    /**
     * Constructor for the pantheon platform.
     */
    public function __construct()
    {
        $this->authStore = new PantheonTerminusTokenStore();
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabel() {
        return 'Pantheon';
    }

    /**
     * {@inheritdoc}
     */
    public static function getTypeId() {
        return 'pantheon';
    }

    /**
     * {@inheritdoc}
     */
    public function taskDirectories()
    {
        return array_merge([
            __DIR__ . '/Task/Pantheon'
        ], parent::taskDirectories());
    }

    /**
     * {@inheritdoc}
     */
    public function environments()
    {
        return [
            'dev' => 'Development',
            'test' => 'Staging',
            'live' => 'Production',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function optionForm()
    {
        return (new Form())
            ->addField(
                new TextField('site_name', 'Site Name', true)
            );
    }

    /**
     * {@inheritdoc}
     */
    public function drupalRestoreOptions() {
        return ['pantheon-import'];
    }

    /**
     * {@inheritdoc}
     */
    public function drupalRestore($method) {
        switch ($method) {
            case 'pantheon-import':
                $this->pantheonImport();
                break;
        }
    }

    /**
     * @param bool $localhost
     * @return $this
     * @throws \Exception
     */
    public function pantheonWho($localhost = false)
    {
        $command = (new TerminusCommand())
            ->command("auth:whoami");
        $service = $this->getPhpServiceName();

        $this->executeEngineCommand($command, $service, [], false, $localhost);

        return $this;
    }

    /**
     * @param null $email
     * @param null $token
     * @param bool $localhost
     * @param bool $quiet
     * @return $this
     * @throws \Exception
     */
    public function pantheonAuth($email = null, $token = null, $quiet = false, $localhost = false)
    {
        $options = [];
        $terminus = new TerminusCommand();

        if (isset($email)) {
            $options['email'] = $email;
        }

        if (isset($token)) {
            $options['machine-token'] = $token;
        }

        if (empty($options)) {
            $options = $this->extractPantheonAuthHostTokens() ?: [];
        }
        $command = $terminus->command("auth:login {$this->formatCommandOptions($options)}");

        $this->executeTerminusCommand($command, $quiet, $localhost);

        $email = isset($options['email'])
            ? $options['email']
            : null;
        $token = isset($options['machine-token'])
            ? $options['machine-token']
            : null;

        $this->authStore
            ->setUser($email)
            ->setToken($token)
            ->save();

        return $this;
    }

    /**
     * @param $command
     * @param bool $quiet
     * @param bool $localhost
     * @return $this
     * @throws \Exception
     */
    public function executeTerminusCommand($command, $quiet = false, $localhost = false)
    {
        if (!$command instanceof CommandInterface) {
            $command = (new TerminusCommand())
                ->command($command);
        }
        $this->executeEngineCommand($command, $this->getPhpServiceName(), [], $quiet, $localhost);

        return $this;
    }

    /**
     * Pantheon import database from platform.
     *
     * @param null $environment
     * @param string $directory
     * @param bool $backup
     * @param bool $localhost
     * @return self
     * @throws \Exception
     */
    public function pantheonImport(
        $environment = null,
        $directory = '/tmp',
        $backup = true,
        $localhost = false
    )
    {
        if (!isset($environment)) {
            $environment = $this->doAsk(
                new ChoiceQuestion('Select environment:', $this->environments())
            );
        }
        /** @var PhpProjectType $project */
        $project = $this->getProjectInstance();
        $filepath = "{$directory}/{$this->getSiteName()}.{$environment}.sql.gz";

        $this->executeSiteBackup('db', $environment, $backup, $filepath, $localhost);
        $project->importDatabaseToService($project->getPhpServiceName(), $filepath, false, $localhost);

        return $this;
    }

    /**
     * @param $element
     * @param $environment
     * @param bool $create
     * @param string $destination
     * @param bool $localhost
     * @return string
     * @throws \Exception
     */
    public function executeSiteBackup(
        $element,
        $environment,
        $create = true,
        $destination  = '/tmp/pantheon-backup.tar.gz',
        $localhost = false
    )
    {
        $site = $this->getSiteName();
        $engine = $this->getEngineInstance();
        $service = $this->getPhpServiceName();

        $terminus = new TerminusCommand(null, $localhost);

        if ($create) {
            $terminus->command("backup:create {$site}.{$environment} --element={$element}");
        }
        $terminus->command("backup:get {$site}.{$environment} --element={$element} --to={$destination}");

        if ($engine instanceof DockerEngineType) {
            $engine->execRaw("rm -f {$destination}", $service, [], true);
        } else {
            $this->_remove($destination);
        }

        $this->executeEngineCommand($terminus, $service, [], false, $localhost);

        return $destination;
    }

    /**
     * Get pantheon site name.
     *
     * @return string
     */
    public function getSiteName()
    {
        $options = $this->getPlatformOptions();

        if (!isset($options['site_name'])) {
           return null;
        }

        return $options['site_name'];
    }

    /**
     * {@inheritdoc}
     */
    public function onEngineUp()
    {
        $contents = $this->authStore->getStoreData();

        $user = isset($contents['user'])
            ? $contents['user']
            : NULL;
        $token = isset($contents['token'])
            ? $contents['token']
            : NULL;

        $this->pantheonAuth($user, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function onEngineDown()
    {
        // No actions to perform.
    }

    /**
     * @return array
     */
    protected function elements()
    {
        return ['db', 'files', 'code'];
    }

    /**
     * @return bool
     */
    protected function isDrupal()
    {
        return $this->getProjectInstance() instanceof DrupalProjectType;
    }

    /**
     * @param array $options
     * @return string
     */
    protected function formatCommandOptions(array $options)
    {
        array_walk($options, function(&$value, $key, $prefix) {
            $value = "{$prefix}{$key}={$value}";
        }, '--');

        return implode(' ', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function alterComposer(ComposerConfig $composer)
    {
        if (!$this->hasTerminus()) {
            $composer->addDevRequire('pantheon-systems/terminus', static::DEFAULT_TERMINUS_VERSION);
        }
    }

    /**
     * Determine if terminus is defined in the composer.json.
     */
    protected function hasTerminus()
    {
        $project = $this->getProjectInstance();
        if (!$project instanceof PhpProjectType) {
            return false;
        }

        return $project->hasComposerPackage('pantheon-systems/terminus', static::DEFAULT_TERMINUS_VERSION);
    }

    /**
     * Get PHP service name.
     *
     * @return string
     * @throws \Exception
     */
    protected function getPhpServiceName()
    {
        /** @var PhpProjectType $project */
        $project = $this->getProjectInstance();

        if (!$project instanceof PhpProjectType) {
            throw new \Exception(
                'Project is not using PHP.'
            );
        }

        return $project->getPhpServiceName();
    }

    /**
     * Extract pantheon auth host token.
     *
     * @return array|bool
     */
    protected function extractPantheonAuthHostTokens()
    {
        $home = getenv('HOME');
        $terminus_cache = "$home/.terminus/cache/tokens";

        if (!file_exists($terminus_cache)) {
            return false;
        }
        $files = [];

        foreach (new \DirectoryIterator($terminus_cache) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $files[$file->getFilename()] = $file->getRealPath();
        }

        if (empty($files)) {
            return false;
        }
        $continue = $this->confirm('Use existing machine token from host?');

        if (!$continue) {
            return false;
        }
        $filepath = reset($files);

        if (count($files) > 1) {
            $filename = $this->doAsk(
                new ChoiceQuestion('Select pantheon account:', array_keys($files))
            );
            $filepath = $files[$filename];
        }
        $content = json_decode(file_get_contents($filepath));

        return [
            'email' => $content->email,
            'machine-token' => $content->token
        ];
    }
}
