<?php
namespace Notifier\Listener;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Creates the listener service for the notifier_notifications
 *
 * @category    Notifier
 * @package     Listener
 */
class ListenerServiceFactory implements FactoryInterface
{
    /**
     * @{@inheritdoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new Listener($serviceLocator);
    }
}
