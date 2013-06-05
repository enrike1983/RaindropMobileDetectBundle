<?php

namespace Raindrop\MobileDetectBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Raindrop\MobileDetectBundle\ActiveDevice;
use Raindrop\MobileDetectBundle\DeviceDetection\DeviceDetectionInterface;

/**
 * RequestListener
 */
class RequestListener
{
    CONST REDIRECT = 'redirect';
    CONST NO_REDIRECT = 'no_redirect';
    CONST REDIRECT_WITHOUT_PATH = 'redirect_without_path';
    CONST MOBILE = 'mobile';

    /**
     * @var ActiveDevice $activeDevice
     */
    protected $activeDevice;

    /**
     * @var array
     */
    protected $redirectConf;

    /**
     * @var DeviceDetectionInterface $deviceDetection
     */
    protected $deviceDetection;

    /**
    * @var string
    */
    protected $newDevice;

    /**
     * Constructor.
     *
     * @param ActiveDevice             $activeDevice
     * @param array                    $redirectConf
     * @param DeviceDetectionInterface $deviceDetection
     */
    public function __construct(ActiveDevice $activeDevice, array $redirectConf, DeviceDetectionInterface $deviceDetection)
    {
        $this->activeDevice = $activeDevice;
        $this->redirectConf = $redirectConf;
        $this->deviceDetection = $deviceDetection;
    }

   /**
    * @param GetResponseEvent $event
    */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (HttpKernelInterface::MASTER_REQUEST === $event->getRequestType()) {
            $cookieValue = null;

            if (!$cookieValue && $this->deviceDetection instanceof DeviceDetectionInterface) {
                $cookieValue = $this->deviceDetection->getDevice();
            }

            if ($cookieValue && $cookieValue !== $this->activeDevice->getName()) {
                $this->activeDevice->setName($cookieValue);
            }
        }

        // Redirects to the mobile version
        if ($this->hasMobileRedirect($request)) {
            if (($response = $this->getMobileRedirectResponse($request))) {
                $event->setResponse($response);
            }

            return;
        }
    }

   /**
    * Detects mobile redirections.
    *
    * @param Request $request
    * @return boolean
    */
    private function hasMobileRedirect($request)
    {
        if (!$this->redirectConf['mobile']['is_enabled']) {
            return false;
        }

        $isMobile = $this->deviceDetection->isMobile();
        $isMobileHost = ($this->getCurrentHost($request) === $this->redirectConf['mobile']['host']);

        if ($isMobile && !$isMobileHost) {
            return true;
        }

        return false;
    }

   /**
    * Gets the mobile RedirectResponse.
    *
    * @return \Symfony\Component\HttpFoundation\RedirectResponse
    */
    private function getMobileRedirectResponse($request)
    {
        if (($host = $this->getRedirectUrl(self::MOBILE, $request))) {
            return new RedirectResponse($host);
        }
    }

   /**
    * Gets the redirect url.
    *
    * @param string  $platform
    * @param Request $request
    *
    * @return string
    */
    private function getRedirectUrl($platform, $request)
    {
        return $request->getScheme() . '://' . $this->redirectConf[$platform]['host'];
    }

   /**
    * Gets the current host.
    *
    * @param Request $request
    * @return string
    */
    private function getCurrentHost($request)
    {
        return $request->getScheme() . '://' . $request->getHost();
    }
}
