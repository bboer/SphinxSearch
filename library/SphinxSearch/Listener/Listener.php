<?php
namespace Notifier\Listener;

use Notifier\Db\Mapper;
use Notifier\Db\Entity;
use Notifier\Exception;
use Zend\Db\Sql\Predicate;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Listener sercive to use for listening for events stored in the database
 *
 * @category    Notifier
 * @package     Listener
 */
class Listener
{
    use \Notifier\ServiceManager\MapperServiceTrait;
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;

    const SERVICE_NAME = 'Notifier\Listener\Listener';

    /**
     * When a timeout occurs this const value gets returned by listen
     */
    const ERROR_TIMEOUT = -3;

    /**
     * @var integer
     */
    protected $offset            = 0;

    /**
     * @var string
     */
    protected $scope             = null;

    /**
     * @var integer
     */
    protected $scopeId           = null;

    /**
     * @var integer
     */
    protected $event             = null;

    /**
     * @var Mapper\Notification
     */
    protected $mapper            = null;

    /**
     * @var boolean
     */
    protected $lockRequired      = true;

    /**
     * @var integer
     */
    protected $lockTimeout       = 300;

    /**
     * @var integer
     */
    protected $lockWaitTime      = 10;

    /**
     * @var integer
     */
    protected $timeout           = 5;

    /**
     * @var integer
     */
    protected $queryTimeout      = 100000;

    /**
     * @var Entity\NotificationListener
     */
    protected $currentLock;

    /**
     * @var mixed
     */
    protected $conditions;

    /**
     * Create a new listener for a specific event
     *
     * @param ServiceLocatorInterface $locator
     */
    public function __construct(ServiceLocatorInterface $locator)
    {
        $this->scope = Entity\Notification::SCOPE_GLOBAL;
        $this->setServiceLocator($locator);
    }

    /**
     * Set another mapper that points to another table to listen for notifications
     * this mapper must extend the default mapper.
     *
     * @param Mapper\Notification $mapper
     * @return Listener
     */
    public function setMapper(\ZendAdditionals\Db\Mapper\AbstractMapper $mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper\Notification|null
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param boolean $lockRequired
     * @return Listener
     */
    public function setLockRequired($lockRequired)
    {
        $this->lockRequired = $lockRequired;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isLockRequired()
    {
        return $this->lockRequired;
    }

    /**
     * @param integer $lockTimeout
     * @return Listener
     */
    public function setLockTimeout($lockTimeout)
    {
        $this->lockTimeout = $lockTimeout;
        return $this;
    }

    /**
     * @return integer
     */
    public function getLockTimeout()
    {
        return $this->lockTimeout;
    }

    /**
     * @param integer $lockWaitTime
     * @return Listener
     */
    public function setLockWaitTime($lockWaitTime)
    {
        $this->lockWaitTime = $lockWaitTime;
        return $this;
    }

    /**
     * @return integer
     */
    public function getLockWaitTime()
    {
        return $this->lockWaitTime;
    }

    /**
     * @param integer $timeout
     * @return Listener
     */
    public function setTimeout($timeout)
    {
        $this->timeout     = $timeout;
        $this->initialized = false;
        return $this;
    }

    /**
     * @return integer
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the amount of microseconds that the mapper should wait
     * before executing the next query
     *
     * @param integer $timeout
     * @return Listener
     */
    public function setQueryTimeout($queryTimeout)
    {
        $this->queryTimeout = $queryTimeout;

        return $this;
    }

    /**
     * Returns the amount of microseconds that the mapper should wait
     * before executing the next query
     *
     * @return integer
     */
    public function getQueryTimeout()
    {
        return $this->queryTimeout;
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
     * @param string $scope
     *
     * @return Listener
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * @return string
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @param  integer $scopeId
     *
     * @return Listener
     *
     * @throws NonLogicScopeException
     * @throws InvalidArgumentException
     */
    public function setScopeId($scopeId)
    {
        if ($this->scope === Entity\Notification::SCOPE_GLOBAL) {
            throw new Exception\NonLogicScopeException(
                'The scope should not be \'' . Entity\Notification::SCOPE_GLOBAL .
                '\' when providing a scope id!'
            );
        }
        $scopeId = (int)$scopeId;
        if ($scopeId < 0) {
            throw new Exception\InvalidArgumentException(
                'The provided scope id must be an integer!'
            );
        }
        $this->scopeId = $scopeId;
        return $this;
    }

    /**
     * @return integer|null
     */
    public function getScopeId()
    {
        return $this->scopeId;
    }

    /**
     * @param  string|array<events> $event
     *
     * @return Listener
     *
     * @throws InvalidArgumentException
     */
    public function setEvent($event)
    {
        if ( !(is_string($event) || is_array($event)) || empty($event)) {
            throw new Exception\InvalidArgumentException(
                'The given event to listen to must be a valid string!'
            );
        }

        $this->event = $event;
        return $this;
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param type $offset
     *
     * @return Listener
     *
     * @throws InvalidArgumentException
     */
    public function setOffset($offset)
    {
        $offset = (int)$offset;
        if ($offset < 0) {
            throw new Exception\InvalidArgumentException(
                'The provided offset id must be an integer!'
            );
        }
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return integer
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set multiple conditons
     *
     * @param array $conditions the values of the array must contain arrays with the keys
     *                          expression and statement, both values should be Predicate\Operator instances.
     *
     * @return Listener
     */
    public function setConditions(array $conditions)
    {
        foreach ($conditions as $identifier => $condition) {
            if (!isset($condition['statement']) || !isset($condition['condition'])) {
                throw new \Exception('The conditions array should have an array with the values statement and condition');
            }
            $this->setCondition($identifier, $condition['expression'], $condition['statement']);
        }
        return $this;
    }

    /**
     * Set a single Condition, this will create a SQL query like this:
     * ... WHERE ...
     * (
     *   (`expression` != `expressionValue` OR `statement` = `statementValue`)
     * )
     * ...
     *
     * This is simular to the following, but shorter:
     * ... WHERE ...
     * (
     *   (`expression` != `expressionValue`) OR
     *   (`expression` = `expressionValue` AND `statement` = `statementValue`)
     * )
     * ...
     *
     * @param mixed $identifier
     * @param Predicate\Operator $expression
     * @param Predicate\Operator $statement
     *
     * @return Listener
     */
    public function setCondition($identifier, Predicate\Operator $expression, Predicate\Operator $statement)
    {
        $opositeExpression = clone $expression->setOperator(
            $this->getOpositeOperator(
                $expression->getOperator()
            )
        );

        $predicateSet = new Predicate\PredicateSet(
            array(
                $opositeExpression,
                $statement,
            ), Predicate\PredicateSet::COMBINED_BY_OR
        );
        $this->conditions[$identifier] = $predicateSet;
        return $this;
    }

    /**
     * Unset a Condition
     *
     * @param mixed $identifier
     *
     * @return Listener
     */
    public function unsetCondition($identifier)
    {
        if (isset($this->conditions[$identifier])) {
            unset($this->conditions[$identifier]);
        }
        return $this;
    }

    /**
     * Get a single condition
     *
     * @param mixed $identifier
     *
     * @return Predicate\Operator or false if condition is not set.
     */
    public function getCondition($identifier)
    {
        if (isset($this->conditions[$identifier])) {
            return $this->conditions[$identifier];
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Start listening for notifications
     *
     * @param integer  $chunkSize How many notifications to process at once (maximum)
     * @param callable $callback  When provided listener continues to listen after calling callback
     * @param integer  $timeout   The time to wait for notifications
     *
     * @return array<Notification>|integer A list of notifications or an integer identifying a failure
     *
     * @throws Exception\LogicException
     * @throws Exception\InvalidArgumentException
     */
    public function listen(
        $chunkSize = 1,
        $callback = null
    ) {
        // Validate event
        if (empty($this->event)) {
            throw new Exception\LogicException(
                'No event has been provided to listen to!'
            );
        }

        // Validate callback
        if (null !== $callback && !is_callable($callback)) {
            throw new Exception\LogicException(
                'The provided callback is not callable!'
            );
        }

        // Validate chunkSize
        $chunkSize = (int)$chunkSize;
        if ($chunkSize < 1) {
            throw new Exception\InvalidArgumentException(
                'The provided chunkSize must be an integer of at least 1!'
            );
        }

        // Build filter
        $filter = \ZendAdditionals\Stdlib\ArrayUtils::mergeDistinct(
            array(
                'scope'          => $this->getScope(),
            ),
            $this->getMapper()->getScopeFilter(
                $this->getScope(),
                $this->getScopeId()
            )
        );

        if (is_string($this->getEvent())) {
            $filter['event'] = $this->getEvent();
        } else {
            $filter['event'] = new Predicate\In('event', $this->getEvent());
        }

        // Aquire lock
        if ($this->isLockRequired()) {
            if ($this->lock() === false) {
                throw new Exception\ListenerLockedException(
                    'Could not aquire lock to process notifications for filter: ' .
                    print_r($filter, true)
                );
            }
        } else {
            $this->setOffset(0);
        }

        $listening    = true;
        $start        = microtime(true);
        $firstIterate = true;
        $now          = new \DateTime;

        // Set the trigger date operator
        $filter['trigger_date'] = new Predicate\Operator(
            'trigger_date',
            Predicate\Operator::OPERATOR_LESS_THAN_OR_EQUAL_TO,
            $now->format('Y-m-d H:i:s')
        );

        if (is_array($this->getConditions())) {
            $filter = \ZendAdditionals\Stdlib\ArrayUtils::mergeDistinct($filter, $this->getConditions());
        }

        $eventListener = $this->getMapper()->getEventManager()->attach(
            'search_and_wait_next_iteration',
            function($event) {
                $params = $event->getParams();
                $now    = new \DateTime;
                $params['filter']['trigger_date'] = new Predicate\Operator(
                    'trigger_date',
                    Predicate\Operator::OPERATOR_LESS_THAN_OR_EQUAL_TO,
                    $now->format('Y-m-d H:i:s')
                );
            }
        );

        // Detach event closure used to disconnect listener from mapper
        $detachEvent = function() use ($eventListener) {
            $this->getMapper()->getEventManager()->detach($eventListener);
        };

        while ($listening) {
            // For the filter pop/push functionality
            if ($firstIterate) {
                $firstIterate = false;
            } else {
                array_pop($filter);
            }

            // Set correct offset
            $filter['id'] = new Predicate\Operator(
                'id',
                Predicate\Operator::OPERATOR_GREATER_THAN,
                $this->getOffset()
            );

            // Retrieve notifications
            $result = $this->getMapper()->searchAndWait(
                $this->getTimeout() - 1,
                $this->getQueryTimeout(),
                array('begin' => 0, 'end' => $chunkSize),
                $filter,
                null,
                null,
                null,
                null,
                true
            );

            $runTime  = (microtime(true) - $start);
            $continue = true;
            // Has our own timeout expired as well?
            if ($runTime >= $this->getTimeout()) {
                $continue = false;
            }

            // No result means break or continue
            if ($result === false || empty($result)) {
                $this->updateLock();

                // On mapper timeout we'll break
                if ($continue && $result !== false) {
                    continue;
                } else {
                    break;
                }
            }

            // When no callback has been specified return result
            if ($callback === null) {
                return $result;
            }

            // Try to perform the callback
            try {
                call_user_func($callback, $result);
                $last = array_pop($result);

                if (is_object($last) && method_exists($last, 'getId')) {
                    $this->setOffset($last->getId());
                }

                $this->updateLock();
            } catch (\Exception $exception) {
                $this->unLock();
                $detachEvent(); // Detach event listener from mapper
                throw new Exception\CallbackFailedException($exception);
            }

            // Only continue when there is time left
            if ($continue) {
                continue;
            }

            break;
        }

        $detachEvent(); // Detach event listener from mapper

        $this->unLock();

        return;
    }

    protected function lock()
    {
        if (!$this->isLockRequired()) {
            return true;
        }

        $notificationTable = $this->getMapper()->getTableName();

        $filter = array(
            'notification_table' => $notificationTable,
            'event'              => $this->getEvent(),
            'scope'              => $this->getScope(),
            'scope_id'           => $this->getScopeId(),
        );

        $result = $this->getNotificationListenerMapper()->search($filter);
        if (empty($result)) {
            $this->currentLock = new Entity\NotificationListener();
            $this->currentLock->setNotificationTable($notificationTable);
            $this->currentLock->setEvent($this->getEvent());
            $this->currentLock->setScope($this->getScope());
            $this->currentLock->setScopeId($this->getScopeId());
            $now = new \DateTime();
            $this->currentLock->setLockDate($now->format('Y-m-d H:i:s'));
            $now->add(new \DateInterval('PT' . $this->getLockTimeout() . 'S'));
            $this->currentLock->setLockExpiryDate($now->format('Y-m-d H:i:s'));
            $this->currentLock->setOffset($this->getOffset());
            $this->getNotificationMapper()->save($this->currentLock);
            return true;
        }
        $now  = new \DateTime();
        $lock = $result[0];
        if (!($lock instanceof Entity\NotificationListener)) {
            // throw some exception
            throw new \Exception();
        }
        $lockExpiryDate = $lock->getLockExpiryDate();
        if (!empty($lockExpiryDate)) {
            $lockExpiryDate = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $lockExpiryDate
            );
        }
        if (empty($lockExpiryDate) || ($now > $lockExpiryDate)) {
            $this->currentLock = $lock;
            // Restore the offset from the previous run
            $this->setOffset($this->currentLock->getOffset());
            $this->updateLock();
            return true;
        }
        return false;
    }

    protected function updateLock()
    {
        if (!$this->isLockRequired()) {
            return true;
        }

        if (!($this->currentLock instanceof Entity\NotificationListener)) {
            throw new Exception\LogicException(
                'Update lock called while no lock has been set!'
            );
        }

        $now = new \DateTime();
        $this->currentLock->setLockDate($now->format('Y-m-d H:i:s'));
        $now->add(new \DateInterval('PT' . $this->getLockTimeout() . 'S'));
        $this->currentLock->setLockExpiryDate($now->format('Y-m-d H:i:s'));
        $this->currentLock->setOffset($this->getOffset());
        $this->getNotificationListenerMapper()->save($this->currentLock);
    }

    protected function unLock()
    {
        if (!$this->isLockRequired()) {
            return true;
        }
        if (!($this->currentLock instanceof Entity\NotificationListener)) {
            throw new Exception\LogicException(
                'Update lock called while no lock has been set!'
            );
        }
        $this->currentLock->setLockExpiryDate(null);
        $this->getNotificationListenerMapper()->save($this->currentLock);
    }

    /**
     * Retrieve the oposite Operator of a Predicate\Operator
     *
     * @param string $operator must be an operator of Predicate\Operator
     *
     * @return string the oposite operator of your input
     *
     * @throws Exception when the input is not a operator of Predicate\Operator
     */
    protected function getOpositeOperator($operator)
    {
        $oposites = array(
            Predicate\Operator::OP_EQ   => Predicate\Operator::OP_NE,
            Predicate\Operator::OP_NE   => Predicate\Operator::OP_EQ,
            Predicate\Operator::OP_LT   => Predicate\Operator::OP_GTE,
            Predicate\Operator::OP_GTE  => Predicate\Operator::OP_LT,
            Predicate\Operator::OP_LTE  => Predicate\Operator::OP_GT,
            Predicate\Operator::OP_GT   => Predicate\Operator::OP_LTE,
        );
        if (!isset($oposites[$operator])) {
            throw new \Exception('A Operator from Zend\Db\Sql\Predicate\Operator should be used.');
        }
        return $oposites[$operator];
    }


}
