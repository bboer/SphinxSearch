<?php
namespace SphinxSearch\SphinxClient;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SphinxSearch\SphinxClient\Exception;

/**
 * Factory class for SphinxClient Service
 */
class SphinxClientServiceFactory implements FactoryInterface
{
    /**
     * @param ServiceLocatorInterface $serviceLocator
     *
     * @return SphinxClientServiceFactory
     */
    public function setServiceLocator($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->getServiceLocator()->get('Config');
    }

    /**
     * Creates the SphinxClient service
     *
     * @param  ServiceLocatorInterface $serviceLocator
     *
     * @return \SphinxClient
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);

        $config = $this->getConfig();

        $host = (
            isset($config['sphinx_search']['server']['host']) ?
            $config['sphinx_search']['server']['host'] :
            null
        );

        $port = (
            isset($config['sphinx_search']['server']['port']) ?
            $config['sphinx_search']['server']['port'] :
            null
        );

        if (null === $host || null === $port) {
            throw new Exception\SphinxConnectionFailedException(
                'No sphinx server information found within the configuration!'
            );
        }

        $sphinxClient = new SphinxClient();
        $sphinxClient->SetServer($host, $port);
        $sphinxClient->SetConnectTimeout(1);
        $sphinxClient->SetArrayResult(true);
        $sphinxClient->SetMatchMode(SPH_MATCH_EXTENDED2);
        $sphinxClient->SetSortMode(SPH_SORT_RELEVANCE);
        $sphinxClient->SetRankingMode(SPH_RANK_PROXIMITY);

        return $sphinxClient;
    }
}
