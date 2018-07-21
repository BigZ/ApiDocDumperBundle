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

    /**
     * DumpApiDocCommand constructor.
     *
     * @param ApiDocGenerator $generator
     * @param null $name
     */
    public function __construct(ApiDocGenerator $generator, $name = null)
    {
        $this->generator = $generator;

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
        $apiDoc['paths'] = $this->addExamples($apiDoc['paths']);
        $apiDoc['definitions'] = $this->removeDatePattern($apiDoc['definitions']);

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
        }

        return $pathList;
    }

    /**
     * @param array $pathList
     *
     * @return array
     */
    private function addExamples(array $pathList)
    {
        foreach ($pathList as $pathName => $path) {
            foreach ($path as $methodName => $method) {
                if (isset($method['parameters']) && is_array($method['parameters'])) {
                    foreach ($method['parameters'] as $parameterName => $parameter) {
                        if (true === $parameter['required'] && 'path' === $parameter['in']) {
                            $pathList[$pathName][$methodName]['parameters'][$parameterName]['x-example'] =
                                'delete' === $methodName ? '2' : '1';
                        }
                    }
                }
            }
        }

        return $pathList;
    }

    /**
     * Nelmio api doc adds a shitty "pattern" thing to date-time in forms, and we don't want that.
     *
     * @param array $definitionList
     *
     * @return array
     */
    private function removeDatePattern(array $definitionList)
    {
        foreach ($definitionList as $definitionName => $definition) {
            if (isset($definition['properties'])) {
                foreach ($definition['properties'] as $propertyId => $property) {
                    if (isset($property['format']) && isset($property['pattern'])) {
                        unset($definitionList[$definitionName]['properties'][$propertyId]['pattern']);
                    }
                }
            }
        }

        return $definitionList;
    }
}
