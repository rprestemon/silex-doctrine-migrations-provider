<?php
/**
 * A silex provider for doctrine migrations.
 *
 * @author  chris
 * @created 15/12/14 10:12
 */

namespace Kurl\Silex\Provider;

use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\OutputWriter;
use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Pimple\Container;
use Silex\Application;
use Silex\Api\BootableProviderInterface;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Console\Application as Console;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class DoctrineMigrationsProvider
 *
 * @package Kurl\Silex\Provider
 */
class DoctrineMigrationsProvider implements ServiceProviderInterface, BootableProviderInterface
{

    /**
     * The console application.
     *
     * @var Console
     */
    protected $console;

    /**
     * Creates a new doctrine migrations provider.
     *
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
    }

    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container An Application instance
     */
    public function register(Container $container)
    {
        $container['migrations.output_writer'] = new OutputWriter(
            function ($message) {
                $output = new ConsoleOutput();
                $output->writeln($message);
            }
        );

        $container['migrations.directory']  = null;
        $container['migrations.name']       = 'Migrations';
        $container['migrations.namespace']  = null;
        $container['migrations.table_name'] = 'migration_versions';
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $helperSet = new HelperSet(array(
            'connection' => new ConnectionHelper($app['db']),
            'dialog'     => new DialogHelper(),
        ));

        if (isset($app['orm.em'])) {
            $helperSet->set(new EntityManagerHelper($app['orm.em']), 'em');
        }

        $this->console->setHelperSet($helperSet);

        $commands = array(
            'Doctrine\DBAL\Migrations\Tools\Console\Command\ExecuteCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\MigrateCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\StatusCommand',
            'Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand'
        );

        // @codeCoverageIgnoreStart
        if (true === $this->console->getHelperSet()->has('em')) {
            $commands[] = 'Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand';
        }
        // @codeCoverageIgnoreEnd

        $configuration = new Configuration($app['db'], $app['migrations.output_writer']);

        $configuration->setMigrationsDirectory($app['migrations.directory']);
        $configuration->setName($app['migrations.name']);
        $configuration->setMigrationsNamespace($app['migrations.namespace']);
        $configuration->setMigrationsTableName($app['migrations.table_name']);

        $configuration->registerMigrationsFromDirectory($app['migrations.directory']);

        foreach ($commands as $name) {
            /** @var AbstractCommand $command */
            $command = new $name();
            $command->setMigrationConfiguration($configuration);
            $this->console->add($command);
        }
    }
}
