<?php

/*
 * This file is part of the promote-api package.
 *
 * (c) Bigz
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/

namespace Bigz\ApiDocDumperBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Doctrine\Common\Annotations\AnnotationReader;
use WizardsRest\Annotation\Exposable;

/**
 * Class GenerateSwaggerDocumentationCommand
 * @author Romain Richard
 */
class DumpApiDocCommand extends Command
{
    /**
     * @var ApiDocGenerator $generator
     */
    private $generator;

    private $reader;

    /**
     * DumpApiDocCommand constructor.
     *
     * @param ApiDocGenerator $generator
     * @param null $name
     */
    public function __construct(ApiDocGenerator $generator, $name = null)
    {
        $this->generator = $generator;

        $this->reader = new AnnotationReader();

        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('dump:api-doc')
            ->setDescription('Generate a openApi documentation json file according to your annotations')
            ->addArgument(
                'fileName',
                InputArgument::OPTIONAL,
                'Output file name. Default: apidoc.json',
                'apidoc.json'
            )
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileSystem = new Filesystem();
        $apiDoc = $this->generator->generate()->toArray();
        $apiDoc['paths'] = $this->removePrivatePaths($apiDoc['paths']);

        if ($this->isJsonApi($apiDoc) && isset($apiDoc['definitions'])) {
            $apiDoc['definitions'] = $this->removeIdFromDefinitions($apiDoc['definitions']);
            $apiDoc['definitions'] = $this->removeRelationsFromDefinitions($apiDoc['definitions']);
        }

        $jsonSchema = json_encode($apiDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonSchema) {
            $fileSystem->dumpFile($input->getArgument('fileName'), $jsonSchema);

            return $output->writeln('The API documentation was dumped successfully');
        }

        return $output->writeln('Unable to generate the API documentation');
    }

    /**
     * @param array $pathList
     *
     * @return array
     */
    private function removePrivatePaths(array $pathList)
    {
        foreach (array_keys($pathList) as $path) {
            if (false !== strstr($path, '/_')) {
                unset($pathList[$path]);
            }

            if ('/' === $path) {
                unset($pathList[$path]);
            }
        }

        return $pathList;
    }

    /**
     * Don't duplicate the id parameter in jsonapi.
     *
     * @param array $definitionList
     *
     * @return array
     */
    private function removeIdFromDefinitions(array $definitionList)
    {
        foreach ($definitionList as $definitionName => $definition) {
            if (isset($definition['properties'])) {
                foreach ($definition['properties'] as $propertyId => $property) {
                    if ('id' === $propertyId) {
                        unset($definitionList[$definitionName]['properties'][$propertyId]);
                    }
                }
            }
        }

        return $definitionList;
    }

    /**
     * Relationships are managed as HATEOAS in jsonapi.
     *
     * @param array $definitionList
     *
     * @return array
     */
    private function removeRelationsFromDefinitions(array $definitionList)
    {
        foreach ($definitionList as $definitionName => $definition) {
            if (isset($definition['properties'])) {
                foreach ($definition['properties'] as $propertyId => $property) {
                    // if property is a relationship
                    if (isset($property['$ref']) || isset($property['items']['$ref'])) {
                        // check in the entity s actually a relation
                        try {
                            $reflection = new \ReflectionClass(
                                sprintf('App\\Entity\\%s', ucfirst($definitionName))
                            );
                            if (
                                $reflection
                                && $reflection->hasProperty($propertyId)
                                && null === $this->reader->getPropertyAnnotation(
                                    $reflection->getProperty($propertyId),
                                    Exposable::class
                                )
                            ) {
                                unset($definitionList[$definitionName]['properties'][$propertyId]);
                            }
                        } catch (\Exception $exception) {
                           // property is not a relation. skip.
                        }
                    }
                }
            }
        }

        return $definitionList;
    }

    private function isJsonApi($apiDoc)
    {
        return isset($apiDoc['produces']) && in_array('application/vnd.api+json', $apiDoc['produces']);
    }
}
