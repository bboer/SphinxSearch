<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'SphinxSearch\Search\Search'
                => 'SphinxSearch\Search\SearchServiceFactory',
            'SphinxSearch\SphinxClient\SphinxClient'
                => 'SphinxSearch\SphinxClient\SphinxClientServiceFactory',
        ),
    ),
);
