<?php

namespace Metadrop\DrupalBoilerplateAssistant\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;

/**
 * Core class of the plugin.
 *
 * Contains the primary logic which determines the files to be fetched and
 * processed.
 *
 * @internal
 */
class Handler {

  const DIR = './scripts';
  const ENV_FILE = './.env';
  const MAKE_FILE = './Makefile';
  const DRUSH_ALIASES_FOLDER = './drush/sites';
  const DRUSH_ALIASES_FILE_SUFFIX = '.site.yml';

  const TARGET_DIR = '../vendor/metadrop/scripthor/bin/';

  const SIMLINK_FILES = [
    'frontend-build.sh',
    'copy-content-config-entity-to-module.sh',
    'reload-local.sh',
    'setup-traefik-port.sh',
    'backup.sh',
  ];

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * Wether the user wants to initialize a Git repository or not.
   *
   * @var bool
   */
  protected $initializeGit;

  /**
   * Command to use to call the docker compose plugin.
   *
   * When it is V1 it thould be "compose", for V2 is "docker compose".
   */
  protected $dockerComposeCmd;

  /**
   * Handler constructor.
   *
   * @param \Composer\Composer $composer
   *   The Composer service.
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service.
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * Create symlinks.
   *
   * @throws \Exception
   *   Error when not created
   */
  public function createSymlinks() {
    if ($this->createScriptDir()) {
      $this->createScriptLink();
    }
    else {
      $this->io->writeError('./scripts directory not created.');
      throw new \Exception('./scripts directory not created.');
    }
  }

  /**
   * Create script directory.
   *
   * @return bool
   *   Exist or not directory
   */
  protected function createScriptDir() {
    if (!is_dir(self::DIR)) {
      $this->io->write('./scripts directory created with 755 permissions.');
      mkdir(self::DIR, 0755);
    }

    if (is_dir(self::DIR)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Create script symbolic links.
   */
  protected function createScriptLink() {

    foreach (self::SIMLINK_FILES as $file) {
      $script = self::DIR . '/' . $file;
      if (!file_exists($script)) {
        symlink(self::TARGET_DIR . $file, $script);
        $this->io->write('Script created: ' . $file);
      }
    }
  }

  /**
   * Assistant on create project.
   */
  public function createProjectAssistant() {
    $this->io->write('Launching assistant to configure the Drupal Boilerplate');
    $project_name = $this->setConfFiles();
    $theme_name = str_replace('-', '_', $project_name);
    $this->setupDockerComposeCmd();
    $this->setUpGit();
    $this->startDocker($theme_name);
    $this->initGrumPhp();
    $this->installDrupal($project_name);
    $this->createDirectories();
    $this->createSubTheme($theme_name);
    $this->assistantSuccess($project_name);
    return 0;
  }

  /**
   * Determines the command to run docker compose plugin.
   */
  protected function setupDockerComposeCmd() {

    $this->dockerComposeCmd = trim(shell_exec("grep ^DOCKER_COMPOSE_CMD= .env.example | cut -f2 -d="));
    $this->io->write('Using ' . $this->dockerComposeCmd . ' to run Docker Compose commands');
  }

  /**
   * Runs a docker compose command.
   *
   * It makes sure the proper docker command is used.
   */
  protected function runDockerComposeCmd(string $args) {
    system($this->dockerComposeCmd . " " . $args);
  }

  /**
  * Create needed directories.
  */
  protected function createDirectories() {
    $behat_dir = './web/sites/default/files/behat';
    if (!is_dir($behat_dir)) {
      mkdir($behat_dir, 0755, true);
    }
    $behat_dir_errors = $behat_dir . '/errors';
    if (!is_dir($behat_dir_errors)) {
      mkdir($behat_dir_errors, 0755, true);
    }
  }

  /**
   * Replaces the string $needle in the file $path with $replacement.
   */
  protected function replaceInFile($path, $needle, $replacement) {
    $content = file_get_contents($path);
    $replaced_content = str_replace($needle, $replacement, $content);
    file_put_contents($path, $replaced_content);
  }

  /**
   * Copies an example file to the final file and replaces strings inside.
   *
   * Some important required files are provided with examples. To use them,
   * they need to be copied to a file withthe required name and replace the
   * example values with the final values. This function takes an example file,
   * with path $path . $suffix, copies it to $path and replaces inside that copy
   * the string $needle with $replacement.
   */
  protected function processExampleFile($path, $suffix, $needle, $replacement) {
    copy($path . $suffix, $path);
    $this->replaceInFile($path, $needle, $replacement);
  }

  /**
   * Helper method to setup several configuration files.
   */
  protected function setConfFiles() {
    $current_dir = basename(getcwd());
    $project_name = $this->io->ask('Please enter the project name (default to ' . $current_dir . '): ', $current_dir);
    $theme_name = str_replace('-', '_', $project_name) . '_radix';

    $this->io->write('Setting up .env file');
    $this->processExampleFile(self::ENV_FILE, '.example', 'example', $project_name);
    $this->replaceInFile(self::MAKE_FILE, 'frontend_target ?= "example"', 'frontend_target ?= "' . $theme_name . '"');

    $this->io->write('Setting up Drush aliases file');
    $drush_site_aliases = self::DRUSH_ALIASES_FOLDER . '/sitename' . self::DRUSH_ALIASES_FILE_SUFFIX;
    $drush_local_alias = self::DRUSH_ALIASES_FOLDER . '/default' . self::DRUSH_ALIASES_FILE_SUFFIX;
    $this->processExampleFile($drush_site_aliases,  '.example', 'sitename', $project_name);
    $this->replaceInFile($drush_local_alias, 'example', $project_name);

    $this->io->write('Setting up behat.yml file');
    $this->replaceInFile('./behat.yml', 'example', $project_name);


    $this->io->write('Setting up BackstopJS\' cookies.json file');
    $this->replaceInFile('./tests/functional/backstopjs/backstop_data/engine_scripts/cookies.json', 'example', $project_name);

    $this->io->write('Setting up compose.override.yml');
    copy('./compose.override.yml.dist', './compose.override.yml');

    $this->io->write('Setting up phpunit.xml');
    copy('./phpunit.xml.dist', './phpunit.xml');

    $this->io->write('Setting up phpcs.xml');
    copy('./phpcs.xml.dist', './phpcs.xml.dist');

    $this->io->write('Setting up phpmd.xml');
    copy('./phpmd.xml.dist', './phpmd.xml');


    return $project_name;
  }

  /**
   * Setup git.
   */
  protected function setUpGit() {

    $this->initializeGit = $this->io->askConfirmation('Do you want to initialize a git repository for your new project? (Y/n) ');

    if ($this->initializeGit) {
      system('git init');
      system('git checkout -b dev');
    }
  }

  /**
   * Start docker.
   */
  protected function startDocker(string $theme_name) {
    system('./scripts/setup-traefik-port.sh');
    $this->runDockerComposeCmd('up -d php');
    $theme_path = '/var/www/html/web/themes/custom/' . $theme_name;
    $this->runDockerComposeCmd('exec php mkdir -p ' . $theme_path);
    $this->runDockerComposeCmd('up -d');
  }

  /**
   * Enable grumphp.
   */
  protected function initGrumPhp() {
    $this->runDockerComposeCmd('exec php ./vendor/bin/grumphp git:init');
  }

  /**
   * Install Drupal with a selected profile.
   */
  protected function installDrupal(string $project_name) {
    if ($this->io->askConfirmation('Do you want to install Drupal? (Y/n) ')) {

      $available_profiles = [
        'Minimal' ,
        'Standard',
        'Umami (demo site)'
      ];

      $available_profile_machine_names = [
        'minimal',
        'standard',
        'demo_umami'
      ];

      $selected_profile_index = $this->io->select('What install profile you want to install?', $available_profiles, 'Minimal');
      $this->io->write('Installing profile ' . $available_profiles[$selected_profile_index]);
      $this->waitDatabase();
      copy('./web/sites/default/example.settings.local.php', './web/sites/default/settings.local.php');
      $drush_yml = file_get_contents('./web/sites/default/example.local.drush.yml');
      $drush_yml = str_replace('example', $project_name, $drush_yml);
      file_put_contents('./web/sites/default/local.drush.yml', $drush_yml);
      $this->runDockerComposeCmd("exec php drush -y si {$available_profile_machine_names[$selected_profile_index]}");
      $this->runDockerComposeCmd('exec php drush cr');
    }
  }


  /**
   * Waits until the database container is available.
   *
   * @throws \Exception
   *   When the container is not available after timeout expires.
   */
  protected function waitDatabase() {
    $count = 10;
    while ($count) {
      $this->io->write('Waiting for database to be ready....');
      $result = trim(shell_exec('. ./.env; ' . $this->dockerComposeCmd . ' exec -u root mariadb mysql -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME} -e "SELECT 1234567890 AS result"| grep "1234567890" | wc -l'));
      if ($result === "1") {
        $this->io->write('Database ready!');
        return;
      }
      sleep(1);
      $count--;
    }

    // Let's hope the detection process failed.
    $this->io->write("Could not detect if database is ready, trying to continue hoping it is ready");
  }

  /**
   * Create new sub-theme.
   */
  protected function createSubTheme(string $theme_name) {
    if ($this->io->askConfirmation('Do you want to create a Radix sub-theme? (Y/n) ')) {
      $this->runDockerComposeCmd('exec php drush en components');
      $this->runDockerComposeCmd('exec php drush theme:enable radix -y');
      $this->runDockerComposeCmd('exec php drush --include="web/themes/contrib/radix" radix:create ' . $theme_name);
      $this->runDockerComposeCmd('exec php drush theme:enable ' . $theme_name . ' -y');
      $this->runDockerComposeCmd('exec php drush config-set system.theme default ' . $theme_name . ' -y');
      system('make frontend dev');
    }
  }

  /**
   * Assistant success message.
   */
  protected function assistantSuccess(string $project_name) {
    $port = shell_exec($this->dockerComposeCmd . ' port traefik 80 | cut -d: -f2');

    if ($this->initializeGit) {
        system('git add .');
        system('git commit -m "Initial commit" -n');
    }

    $this->io->write("\n\n" . '***********************'
      . "\n    CONGRATULATIONS!"
      . "\n***********************"
      . "\nYour new project is up and running on the following url: http://$project_name.docker.localhost:$port"
      . "\nRun `make info` for more URLs to other provided tools\n");
    $this->io->write('Click on the following link to start building your site:');
    $this->runDockerComposeCmd('exec php drush uli');
    $this->io->write("\n");
  }
}
