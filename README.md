# ApiDocDumperBundle
Dumps your OpenApi documention to a json file, based on NelmioApiDoc

## Install
```
composer require bigz/api-doc-dumper-bundle --dev
```

## Configure
add 
```
    Wizards\RestBundle\WizardsRestBundle::class => ['all' => true],
    Bigz\ApiDocDumperBundle\BigzApiDocDumperBundle::class => ['dev' => true],
```
to the returned array in `/config/bundles.php`

# Run
```
bin/console dump:api-doc myfile.json
```
It will generate an myfile.json file at the root of your project. Default name is apidoc.json
