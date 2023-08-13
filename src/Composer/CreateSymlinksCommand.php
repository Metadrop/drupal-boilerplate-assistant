<?php

namespace Metadrop\DrupalBoilerplateAssistant\Composer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "boilerplate:create-symlinks" command class.
 *
 * Manually run the operation that normally happens after
 * 'composer install'. This operation creates symlinks in
 * the `/scriots` folder to scripts provided by Scripthor.
 *
 * @internal
 */
class CreateSymlinksCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('boilerplate:create-symlinks')
      ->setAliases(['boilerplate-symlinks'])
      ->setDescription('Create symlinks to the scripts provided by Scripthor.')
      ->setHelp(
        <<<EOT
The <info>scripthor:create-symlinks</info> command creates symlinks in the
/scripts folder to the scritps provided by Scripthor if it is installed.

<info>php composer.phar boilerplate:create-symlinks</info>

It is usually not necessary to call <info>boilerplate:create-symlinks</info> manually,
because it is called automatically as needed, e.g. after an <info>install</info> or
<info>update</info> command.

Scripthor is a separate pacakge that provides some shell scripts to ease some
tasks in a Drupal installation.
EOT
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $handler = new Handler($this->getComposer(), $this->getIO());
    $handler->createSymlinks();
  }

}
