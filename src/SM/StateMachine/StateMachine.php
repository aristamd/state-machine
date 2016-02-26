<?php

/*
 * This file is part of the StateMachine package.
 *
 * (c) Alexandre Bacco
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SM\StateMachine;

use SM\Callback\CallbackFactory;
use SM\Callback\CallbackFactoryInterface;
use SM\Callback\CallbackInterface;
use SM\Event\SMEvents;
use SM\Event\TransitionEvent;
use SM\SMException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class StateMachine implements StateMachineInterface
{
    /**
     * @var object
     */
    protected $object;

    /**
     * @var object
     */
    protected $workflowClass;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var CallbackFactoryInterface
     */
    protected $callbackFactory;

    /**
     * [$previousState description]
     * @var String
     */
    protected $previousState;

    /**
     * @param object                   $object          Underlying object for the state machine
     * @param array                    $config          Config array of the graph
     * @param EventDispatcherInterface $dispatcher      EventDispatcher or null not to dispatch events
     * @param CallbackFactoryInterface $callbackFactory CallbackFactory or null to use the default one
     *
     * @throws SMException If object doesn't have configured property path for state
     */
    public function __construct( $object, $workflowClass )
    {

        $this->workflowClass = $workflowClass;

        $this->object          = $object;

        $this->callbackFactory = new CallbackFactory('SM\Callback\Callback');

        $this->previousState = null;

        if (!isset($config['property_path'])) {
            $config['property_path'] = 'state';
        }

        $this->config = $workflowClass->config;

        // Test if the given object has the given state property path
        try {
            $this->getState();
        } catch (NoSuchPropertyException $e) {
            throw new SMException(sprintf(
               'Cannot access to configured property path %s on object %s with graph %s',
                $config['property_path'],
                get_class($object),
                $config['graph']
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function can($transition)
    {
        if (!isset($this->config['transitions'][$transition])) {
            throw new SMException(sprintf(
                'Transition %s does not exist on object %s with graph %s',
                $transition,
                get_class($this->object),
                $this->config['graph']
            ));
        }

        if (!in_array($this->getState(), $this->config['transitions'][$transition]['from'])) {
            return false;
        }

        if (null !== $this->dispatcher) {
            $event = new TransitionEvent($transition, $this->getState(), $this->config['transitions'][$transition], $this);
            $this->dispatcher->dispatch(SMEvents::TEST_TRANSITION, $event);

            return !$event->isRejected();
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function apply($transition, $soft = false)
    {
        if (!$this->can($transition)) {
            if ($soft) {
                return false;
            }

            throw new SMException(sprintf(
                'Transition %s cannot be applied on state %s of object %s with graph %s',
                $transition,
                $this->getState(),
                get_class($this->object),
                $this->config['graph']
            ));
        }

        $event = new TransitionEvent($transition, $this->getState(), $this->config['transitions'][$transition], $this);

        if (null !== $this->dispatcher) {
            $this->dispatcher->dispatch(SMEvents::PRE_TRANSITION, $event);

            if ($event->isRejected()) {
                return false;
            }
        }

        $this->executeTransitionHook('before',$transition);

        $this->previousState = $this->getState();

        $this->setState($this->config['transitions'][$transition]['to']);

        $this->executeTransitionHook('after',$transition);

        if (null !== $this->dispatcher) {
            $this->dispatcher->dispatch(SMEvents::POST_TRANSITION, $event);
        }

        return true;
    }

    /**
     * Handles calling the before/after method on the workflowClass attached to
     * this state machine instance for the transition specified
     *
     * @param  string $type either "before" or "after"
     * @param  string $transition name of a defined transition
     * @return boolean
     */
    public function executeTransitionHook($type, $transition)
    {
        $methodName = $type . self::studlyCase($transition);
        return $this->workflowClass->{$methodName}( $this->object, $this->previousState );
    }

    /**
     * {@inheritDoc}
     */
    public function getState()
    {
        $accessor = new PropertyAccessor();
        return $accessor->getValue($this->object, $this->config['property_path']);
    }

    /**
     * {@inheritDoc}
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * {@inheritDoc}
     */
    public function getGraph()
    {
        return $this->config['graph'];
    }

    /**
     * {@inheritDoc}
     */
    public function getPossibleTransitions()
    {
        return array_filter(
            array_keys($this->config['transitions']),
            array($this, 'can')
        );
    }

    /**
     * Set a new state to the underlying object
     *
     * @param string $state
     *
     * @throws SMException
     */
    protected function setState($state)
    {
        if (!in_array($state, $this->config['states'])) {
            throw new SMException(sprintf(
                'Cannot set the state to %s to object %s with graph %s because it is not pre-defined.',
                $state,
                get_class($this->object),
                $this->config['graph']
            ));
        }

        $accessor = new PropertyAccessor();
        $accessor->setValue($this->object, $this->config['property_path'], $state);
    }

    /**
     * Builds and calls the defined callbacks
     *
     * @param TransitionEvent $event
     * @param string          $position
     */
    protected function callCallbacks(TransitionEvent $event, $position)
    {
        if (!isset($this->config['callbacks'][$position])) {
            return;
        }

        foreach ($this->config['callbacks'][$position] as &$callback) {
            if (!$callback instanceof CallbackInterface) {
                $callback = $this->callbackFactory->get($callback);
            }

            call_user_func($callback, $event);
        }
    }

    /**
     * Converts transition names such as my_transition to something suitable
     * for a method name such as MyTransition
     *
     * @param string $string string to be converted
     * @return string
     */
    private static function studlyCase( $string )
    {
        // Break words seperated by a dash or underscore into distinct words
        $string = str_replace(['-', '_'], ' ', $string);

        // Upper case the first letter of each word
        $string = ucwords($string);

        // Join the words back into a single string and return the result
        return str_replace(' ', '', $string);
    }
}
