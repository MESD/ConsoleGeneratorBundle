<?php
namespace Mesd\Console\GeneratorBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Doctrine\Bundle\DoctrineBundle\Mapping\MetadataFactory;

class FormCommand extends ContainerAwareCommand
{
    protected function configure() {
        $this
        ->setName( 'mesd:form' )
        ->setDescription( 'Write Default Mesd formtypes' )
        ->addArgument(
            'Entity',
            InputArgument::REQUIRED,
            'Bundle:Entity Syntax'
        )
        ->addOption(
            'overwrite'
            , null
            , InputOption::VALUE_NONE
            , 'If not set, existing directory will not be overwritten.'
        )
        ->addOption(
            'stock'
            , null
            , InputOption::VALUE_NONE
            ,
            "If set, stock skeletons are used even if local skeletons exist.\n".
            "Custom skeletons go in Resources/skeleton/form/FormType.php.twig\n"
        )
        ;
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        $entity = $input->getArgument( 'Entity' );
        $forceOverwrite = ( $input->getOption( 'overwrite' ) )?:false;

        list( $bundle, $entity ) = $this->parseShortcutNotation( $entity );
        $bundle      = $this->getContainer()->get( 'kernel' )->getBundle( $bundle );
        $entityClass = $this->getContainer()->get( 'doctrine' )->getAliasNamespace( $bundle->getName() );
        $entityClass.="\\".$entity;

        $stock = ( $input->getOption( 'stock' )?:false );
        $metadata = $this->getEntityMetadata( $entityClass );
        $formGenerator = new MesdFormGenerator ( $this->getContainer()->get( 'filesystem' )
            , $bundle
            , $this->getContainer()->get( 'kernel' )->getBundle( 'MesdConsoleGeneratorBundle' )
        );

        $formGenerator->generate( $bundle, $entity, $metadata[0], $forceOverwrite, $stock );
    }

    protected function createGenerator( $bundle = null ) {
        return new MesdFormGenerator( $this->getContainer()->get( 'filesystem' ) );
    }

    protected function getEntityMetadata( $entity ) {
        $factory = new MetadataFactory( $this->getContainer()->get( 'doctrine' ) );

        return $factory->getClassMetadata( $entity )->getMetadata();
    }

    protected function parseShortcutNotation( $shortcut ) {
        $entity = str_replace( '/', '\\', $shortcut );

        if ( false === $pos = strpos( $entity, ':' ) ) {
            throw new \InvalidArgumentException( sprintf( 'The entity name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/Post)', $entity ) );
        }

        return array( substr( $entity, 0, $pos ), substr( $entity, $pos + 1 ) );
    }

}
