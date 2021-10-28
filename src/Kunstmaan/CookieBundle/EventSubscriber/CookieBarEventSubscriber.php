<?php

namespace Kunstmaan\CookieBundle\EventSubscriber;

use Kunstmaan\AdminBundle\Helper\AdminRouteHelper;
use Kunstmaan\CookieBundle\Helper\LegalCookieHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Class CookieBarEventSubscriber
 */
class CookieBarEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var AdminRouteHelper
     */
    protected $adminRouteHelper;

    /**
     * @var LegalCookieHelper
     */
    private $cookieHelper;

    /**
     * CookieBarEventSubscriber constructor.
     */
    public function __construct(Environment $twig, AdminRouteHelper $adminRouteHelper, LegalCookieHelper $cookieHelper)
    {
        $this->twig = $twig;
        $this->adminRouteHelper = $adminRouteHelper;
        $this->cookieHelper = $cookieHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -125],
        ];
    }

    /**
     * @param FilterResponseEvent|ResponseEvent $event
     */
    public function onKernelResponse($event)
    {
        if (!$event instanceof FilterResponseEvent && !$event instanceof ResponseEvent) {
            throw new \InvalidArgumentException(\sprintf('Expected instance of type %s, %s given', \class_exists(ResponseEvent::class) ? ResponseEvent::class : FilterResponseEvent::class, \is_object($event) ? \get_class($event) : \gettype($event)));
        }

        $response = $event->getResponse();
        $request = $event->getRequest();
        $url = $event->getRequest()->getRequestUri();

        // Do not capture redirects
        if (!$event->isMasterRequest() || $this->adminRouteHelper->isAdminRoute($url)) {
            return;
        }

        if ($response->isRedirection() || ($response->headers->has('Content-Type') && false === strpos(
                    $response->headers->get('Content-Type'),
                    'html'
                ))
            || 'html' !== $request->getRequestFormat()
            || !$this->cookieHelper->isGrantedForCookieBundle($request)
            || false !== stripos($response->headers->get('Content-Disposition'), 'attachment;')
        ) {
            return;
        }

        $response = $this->cookieHelper->checkCookieVersionInResponse($response, $request);

        $this->injectCookieBar($response);
    }

    protected function injectCookieBar(Response $response)
    {
        $content = $response->getContent();
        $pos = strripos($content, '</kuma-cookie-bar>');

        if (false !== $pos) {
            $toolbar = "\n" . str_replace(
                    "\n",
                    '',
                    $this->twig->render('@KunstmaanCookie/CookieBar/view.html.twig')
                ) . "\n";
            $content = substr($content, 0, $pos) . $toolbar . substr($content, $pos);
            $response->setContent($content);
        }
    }
}