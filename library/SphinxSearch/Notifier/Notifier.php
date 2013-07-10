<?php
namespace Notifier\Notifier;

use Notifier\Db\Entity;
use Notifier\Exception;
use Zend\EventManager\Event;
use Zend\EventManager\EventManager;
use Zend\EventManager\GlobalEventManager;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use ZendAdditionals\Stdlib\ArrayUtils;

/**
 * Service to easily acces notification storage
 */
class Notifier implements ServiceManagerAwareInterface
{
    use \Guardian\Db\ApplicationAwareTrait;

    const SERVICE_NAME = 'Notifier\Notifier\Notifier';

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var array
     */
    protected $collect;

    protected $notificationMapperServiceName;

    /**
     * Constructs the Notifier service
     *
     * @param array $collect A list of global events to collect
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->eventManager = GlobalEventManager::getEventCollection();
        $this->startCollecting();
    }

    /**
     * Store an event in the global scope
     *
     * @param string  $event
     * @param mixed   $data
     * @param integer $ttl default is infinite
     */
    public function storeGlobalEvent($event, $data, $ttl = 0)
    {
        $this->storeEvent(
            Entity\Notification::SCOPE_GLOBAL,
            $event,
            $data,
            null,
            $ttl
        );
    }

    /**
     * Clear an event from the global scope
     *
     * @param string  $event
     */
    public function clearGlobalEvent($event)
    {
        $this->clearEvent(Entity\Notification::SCOPE_GLOBAL, $event);
    }

    /**
     * Store an event
     *
     * @param string  $scope
     * @param string  $event
     * @param mixed   $data
     * @param integer $scopeId Reguired when scope is not global
     * @param integer $delay   The amount of seconds to delay the event
     * @param integer $ttl     default is infinite
     */
    public function storeEvent(
        $scope,
        $event,
        $data,
        $scopeId = null,
        $delay   = null,
        $ttl     = 0
    ) {
        $notification = clone $this->getNotificationMapper()->getEntityPrototype();
        /*@var $notification \Notifier\Db\Entity\AbstractNotification*/
        $triggerDate = new \DateTime;
        if (null !== $delay && (int) $delay > 0) {
            // We want to delay this event, modify trigger date
            $delay = (int) $delay;
            $triggerDate->add(new \DateInterval("PT{$delay}S"));
        }
        $notification->setTriggerDate($triggerDate->format('Y-m-d H:i:s'));
        if ((int) $ttl > 0) {
            // Use trigger date as offset for the expiry date
            $ttl = (int) $ttl;
            $triggerDate->add(new \DateInterval("PT{$ttl}S"));
            $notification->setExpiryDate($triggerDate->format('Y-m-d H:i:s'));
        }
        $this->getNotificationMapper()->applyScopeValuesToEntity(
            $scope,
            $notification,
            $scopeId
        );
        $notification->setScope($scope);
        $notification->setEvent($event);
        // @TODO Generate expiry date when ttl > 0
        if (!empty($data)) {
            $notification->setData(serialize($data));
        }
        $this->getNotificationMapper()->save($notification);
    }

    /**
     * Clear an event
     *
     * @param string  $scope
     * @param string  $event
     * @param integer $scopeId Reguired when scope is not global
     */
    public function clearEvent($scope, $event, $scopeId = null)
    {
        $filter = ArrayUtils::mergeDistinct(
            array(
                'scope' => $scope,
                'event' => $event,
            ),
            $this->getNotificationMapper()->getScopeFilter($scope, $scopeId)
        );

        $results = $this->getNotificationMapper()->search(
            array('begin' => 0, 'end' => 1000),
            $filter
        );

        if (!empty($results)) {
            $this->getNotificationMapper()->deleteMultiple($results);
        }
    }

    /**
     * Set the notification mapper service name
     *
     * @param string $serviceName
     *
     * @return Notifier
     */
    public function setNotificationMapperServiceName($serviceName)
    {
        $this->notificationMapperServiceName = $serviceName;
    }

    /**
     * Get the notification mapper service name
     *
     * @return string
     */
    public function getNotificationMapperServiceName()
    {
        return $this->notificationMapperServiceName;
    }

    /**
     * Notification mapper can be an extended one in an another module
     *
     * @return \Notifier\Db\Mapper\Notification
     */
    public function getNotificationMapper()
    {
        return $this->serviceManager->get(
            $this->getNotificationMapperServiceName()
        );
    }

    /**
     * @inheritdoc
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Required for the traits
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->getServiceManager();
    }

    /**
     * Initializes listeners for all the events that need to be collected
     */
    protected function startCollecting()
    {
        $notifier = $this;
        foreach ($this->config as $mapperServiceName => $config) {
            foreach ($config['collect-events'] as $key => $eventToCollect) {
                $options = array();
                if (is_array($eventToCollect)) {
                    $options        = $eventToCollect;
                    $eventToCollect = $key;
                }
                $this->eventManager->attach(
                    $eventToCollect,
                    function(Event $data) use (
                        $notifier,
                        $options,
                        $mapperServiceName
                    ) {
                        $target = $data->getTarget();

                        // Get the current mapper service name to restore later
                        $currentMapperServiceName = $notifier->getNotificationMapperServiceName();

                        // Set the appropriate mapper for the event being collected
                        $notifier->setNotificationMapperServiceName(
                            $mapperServiceName
                        );

                        $mapper = $notifier->getNotificationMapper();
                        /*@var $mapper \Notifier\Db\Mapper\Notification*/

                        $scope = (!isset($options['scope']) ?
                            Entity\Notification::SCOPE_GLOBAL :
                            $options['scope']
                        );

                        $scopeIdRequired = $mapper->isScopeIdRequired($scope);

                        if ($scopeIdRequired && !isset($target["{$scope}_id"])) {
                            // Restore the previously set mapper
                            $notifier->setNotificationMapperServiceName(
                                $currentMapperServiceName
                            );
                            throw new Exception\InvalidArgumentException(
                                'Target argument for collected events must ' .
                                'be an array containing the scope id!'
                            );
                        }

                        $scopeId = (
                            $scopeIdRequired ?
                            $target["{$scope}_id"] :
                            null
                        );

                        $eventsToClear = (isset($options['clear']) ?
                            $options['clear'] :
                            array()
                        );

                        $delay = (isset($options['delay']) ?
                            $options['delay'] :
                            null
                        );

                        $ttl = (isset($options['ttl']) ?
                            $options['ttl'] :
                            0 // 0 == infinite
                        );

                        // Clear events if necessary
                        foreach ($eventsToClear as $eventToClear) {
                            $notifier->clearEvent(
                                $scope,
                                $eventToClear,
                                $scopeId
                            );
                        }

                        // Store the event
                        $notifier->storeEvent(
                            $scope,
                            $data->getName(),
                            $data->getParams(),
                            $scopeId,
                            $delay,
                            $ttl
                        );

                        // Restore the previously set mapper
                        $notifier->setNotificationMapperServiceName(
                            $currentMapperServiceName
                        );
                    }
                );
            }
        }
    }
}
