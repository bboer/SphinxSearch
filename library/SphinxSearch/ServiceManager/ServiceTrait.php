<?php
namespace SphinxSearch\ServiceManager;

/**
 * @category    SphinxSearch
 * @package     ServiceManager
 */
trait ServiceTrait
{
    abstract public function getServiceLocator();

    /**
     * @return \SphinxClient
     */
    public function getSphinxClientService()
    {
        return $this->getServiceLocator()->get(
            'SphinxSearch\SphinxClient\SphinxClient'
        );
    }

    /**
     * @return \SphinxSearch\Search\Search
     */
    public function getSphinxSearchService()
    {
        return $this->getServiceLocator()->get(
            'SphinxSearch\Search\Search'
        );
    }
}
