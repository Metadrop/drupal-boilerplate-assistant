<?php

namespace Metadrop\DrupalBoilerplateAssistant\Composer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "boilerplate:assistant" command class.
 *
 * Manually run the scaffold operation that normally happens after
 * 'composer install'.
 *
 * @internal
 */
class SetupAssistantCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('boilerplate:assistant')
      ->setAliases(['boilerplate-assistant'])
      ->setDescription('Run the same setup assistant as after create-project.')
      ->setHelp(
        <<<EOT
The <info>boilerplate-assistant</info> runs the setup assitant that configures
the Drupal Boilerplate.

<info>php composer.phar boilerplate:create-symlinks</info>

It is usually not necessary to call <info>boilerplate:create-symlinks</info> manually,
because it is called automatically the first time the project is created when
<info>composer create-project</info> is run.

If called manually this command will override nay changes on the configuration
files that it handles like the behat.yml file

EOT
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $handler = new Handler($this->getComposer(), $this->getIO());
    $handler->createProjectAssistant();
  }

}
