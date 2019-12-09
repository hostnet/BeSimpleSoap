<?php

/*
 * This file is part of the BeSimpleSoapBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 * (c) Francis Besset <francis.besset@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BeSimple\SoapBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Francis Besset <francis.besset@gmail.com>
 */
class SoapExceptionListener implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container  A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();
        if (!in_array($request->getRequestFormat(), array('soap', 'xml'))) {
            return;
        } elseif ('xml' === $request->getRequestFormat() && '_webservice_call' !== $request->attributes->get('_route')) {
            return;
        }

        $attributes = $request->attributes;
        if (!$webservice = $attributes->get('webservice')) {
            return;
        }

        if (!$this->container->has(sprintf('besimple.soap.context.%s', $webservice))) {
            return;
        }

        // hack to retrieve the current WebService name in the controller
        $request->query->set('_besimple_soap_webservice', $webservice);

        $exception = $event->getException();
        if ($exception instanceof \SoapFault) {
            $request->query->set('_besimple_soap_fault', $exception);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            // Must be called before ExceptionListener of HttpKernel component
            KernelEvents::EXCEPTION => array('onKernelException', -64),
        );
    }
}
