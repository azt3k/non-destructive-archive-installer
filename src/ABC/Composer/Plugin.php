<?php

namespace ABC\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class NonDestructiveArchiveInstallerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new NonDestructiveArchiveInstallerPlugin($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}