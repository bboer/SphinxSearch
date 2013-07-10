SphinxSearch 1.0
================
By [Bart de Boer] (http://github.com/bboer/)

Introduction
------------
When you like to search sphinx generated indexes from a zf2 application, use this module.

Installation
------------

1. Preparation

    Make sure you have a working Sphinx server anywhere

2. Require SphinxSearch

    From within your project execute the following:

    ```
    php composer.phar require bboer/sphinxsearch
    ```

3. Configure SphinxSearch

    copy sphinxsearch.local.php.dist to your /config/autoload/sphinxsearch.local.php

    Sample configuration:

    ```php
    <?php
    return array(
        'sphinx_search' => array(
            'server' => array(
                'host' => 'mysphinxhost.com',
                'port' => 9312,
            ),
        ),
    );
    
    ```

Usage
-----

1. Using the Search service using the ServiceTrait

    ```php
    <?php
    namespace MyModule\MySpace;
    
    use Zend\ServiceManager\ServiceLocatorAwareInterface;
    
    class MyService implements ServiceLocatorAwareInterface
    {
        use \SphinxSearch\ServiceManager\ServiceTrait;
    
        public function myServiceMethod()
        {
            // Get the results from the SphinxSearch service
            $results = $this->getSphinxSearchService()->search(
                'person_main',
                $filters,
                $queries,
                $fieldWeights,
                $limit,
                $offset
            );
            // NOTE: Used variables are not defined and intended as an example
        }
    }
    ```

2. Using the Search service by locating it through the ServiceLocator

    ```php
    <?php
    namespace MyModule\MySpace;
    
    use Zend\ServiceManager\ServiceLocatorAwareInterface;
    
    class MyService implements ServiceLocatorAwareInterface
    {
        use \SphinxSearch\ServiceManager\ServiceTrait;
    
        public function myServiceMethod()
        {
            // Get the SphinxSearch Search service
            $searchService = $this->getServiceLocator()->get(
                'SphinxSearch\Search\Search'
            );
            // Get the results from the SphinxSearch service
            $results = $searchService->search(
                'person_main',
                $filters,
                $queries,
                $fieldWeights,
                $limit,
                $offset
            );
            // NOTE: Used variables are not defined and intended as an example
        }
    }
    ```
