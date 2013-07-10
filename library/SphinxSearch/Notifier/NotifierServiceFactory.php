<?php
namespace Notifier\Notifier;

use ZendAdditionals\Stdlib\ArrayUtils;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory class to create the Notifier instances
 *
 * @category    Notifier
 * @package     Notifier
 */
class NotifierServiceFactory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config          = $serviceLocator->get('Config');
        $notifierConfig  = ArrayUtils::arrayTarget('notifier', $config, array());

        return new Notifier($notifierConfig);
    }
}
