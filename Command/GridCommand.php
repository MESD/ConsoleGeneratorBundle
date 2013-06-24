<?php

/*
 * This file is part of the Doctrine Bundle
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MESD\Console\GeneratorBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use MESD\Console\GeneratorBundle\Command\MESDGridGenerator;

/**
 * Generate entity classes from mapping information
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GridCommand extends DoctrineCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('mesd:grid')
            ->setDescription('Generates an entity grid classes from your mapping information')
            ->addArgument('name', InputArgument::REQUIRED, 'A bundle name, a namespace, or a class name')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The path where to generate entities when it cannot be guessed')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing entities files.')
            ->setHelp(<<<EOT
The <info>mesd:grid</info> command generates entity classes
and method stubs from your mapping information:

You have to limit generation of entities:

* To a bundle:

  <info>php app/console mesd:grid MyCustomBundle</info>

* To a single entity:

  <info>php app/console mesd:grid MyCustomBundle:User</info>
  <info>php app/console mesd:grid MyCustomBundle/Entity/User</info>

* To a namespace

  <info>php app/console mesd:grid MyCustomBundle/Entity</info>

If the entities are not stored in a bundle, and if the classes do not exist,
the command has no way to guess where they should be generated. In this case,
you must provide the <comment>--path</comment> option:

  <info>php app/console mesd:grid Blog/Entity --path=src/</info>

By default, the unmodified version of each entity is backed up and saved
(e.g. Product.php~). To prevent this task from creating the backup file,
pass the <comment>--no-backup</comment> option:

  <info>php app/console mesd:grid Blog/Entity --no-backup</info>

<error>Important:</error> Even if you specified Inheritance options in your
XML or YAML Mapping files the generator cannot generate the base and
child classes for you correctly, because it doesn't know which
class is supposed to extend which. You have to adjust the entity
code manually for inheritance to work!

EOT
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));

        try {
            $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('name'));

            $output->writeln(sprintf('Generating grids for bundle "<info>%s</info>"', $bundle->getName()));
            $metadata = $manager->getBundleMetadata($bundle);
        } catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');

            if (false !== $pos = strpos($name, ':')) {
                $name = $this->getContainer()->get('doctrine')->getEntityNamespace(substr($name, 0, $pos)).'\\'.substr($name, $pos + 1);
            }

            if (class_exists($name)) {
                $output->writeln(sprintf('Generating grid "<info>%s</info>"', $name));
                $metadata = $manager->getClassMetadata($name, $input->getOption('path'));
            } else {
                $output->writeln(sprintf('Generating grids for namespace "<info>%s</info>"', $name));
                $metadata = $manager->getNamespaceMetadata($name, $input->getOption('path'));
            }
        }

        $generator = $this->getMESDGridGenerator();

        $backupExisting = !$input->getOption('no-backup');
        $generator->setBackupExisting($backupExisting);

        foreach ($metadata->getMetadata() as $m) {
            if ($backupExisting) {
                $basename = substr($m->name, strrpos($m->name, '\\') + 1);
                $basename = str_replace('Entity','Grid',$basename).'Grid';
                $output->writeln(sprintf('  > backing up <comment>%s.php</comment> to <comment>%s.php~</comment>', $basename, $basename));
            }
            // Getting the metadata for the entity class once more to get the correct path if the namespace has multiple occurrences
            try {
                $entityMetadata = $manager->getClassMetadata($m->getName(), $input->getOption('path'));
            } catch (\RuntimeException $e) {
                // fall back to the bundle metadata when no entity class could be found
                $entityMetadata = $metadata;
            }

            $output->writeln(sprintf('  > generating <comment>%s</comment>',
                str_replace('Entity','Grid',$m->name).'Grid'));
            $generator->generate(array($m), $entityMetadata->getPath());
        }
    }

    protected function getMESDGridGenerator()
    {
        $entityGenerator = new MESDGridGenerator();
        // $entityGenerator->setGenerateAnnotations(false);
        // $entityGenerator->setGenerateStubMethods(true);
        // $entityGenerator->setRegenerateEntityIfExists(false);
        // $entityGenerator->setUpdateEntityIfExists(true);
        // $entityGenerator->setNumSpaces(4);
        // $entityGenerator->setAnnotationPrefix('ORM\\');

        return $entityGenerator;
    }
}
