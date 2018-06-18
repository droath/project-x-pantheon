<?php

namespace Droath\ProjectX\Pantheon\Platform;

use Droath\ProjectX\ComposerPackageInterface;
use Droath\ProjectX\Event\EngineEventInterface;
use Droath\ProjectX\OptionFormAwareInterface;
use Droath\ProjectX\Platform\PlatformTypeInterface;
use Droath\ProjectX\Project\DrupalPlatformRestoreInterface;
use Droath\ProjectX\TaskSubTypeInterface;

interface PantheonPlatformInterface extends TaskSubTypeInterface, PlatformTypeInterface, OptionFormAwareInterface, ComposerPackageInterface, DrupalPlatformRestoreInterface, EngineEventInterface
{

}
