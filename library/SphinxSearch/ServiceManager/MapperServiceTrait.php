<?php
namespace Notifier\ServiceManager;

use Notifier\Db\Mapper;

/**
 * @category    Notifier
 * @package     ServiceManager
 */
trait MapperServiceTrait
{
    abstract public function getServiceLocator();

    /**
     * @return \Notifier\Db\Mapper\Notification
     */
    public function getNotificationMapper()
    {
        return $this->getServiceLocator()->get(Mapper\Notification::SERVICE_NAME);
    }

    /**
     * @return \Notifier\Db\Mapper\NotificationListener
     */
    public function getNotificationListenerMapper()
    {
        return $this->getServiceLocator()->get(Mapper\NotificationListener::SERVICE_NAME);
    }
}
