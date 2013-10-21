<?php

namespace Raindrop\MobileDetectBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Liip\ThemeBundle\ActiveTheme;
use Raindrop\MobileDetectBundle\ActiveDevice;
use Raindrop\MobileDetectBundle\DeviceDetection\DeviceDetectionInterface;

/**
 * RequestListener
 */
class RequestListener implements EventSubscriberInterface
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
     * @var ActiveTheme $activeTheme
     */
    protected $activeTheme;

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
     *
     * @var string
     */
    protected $entityManager;

    /**
     * Constructor.
     *
     * @param ActiveDevice             $activeDevice
     * @param array                    $redirectConf
     * @param ActiveTheme              $activeTheme
     * @param DeviceDetectionInterface $deviceDetection
     */
    public function __construct($entityManager, ActiveDevice $activeDevice, array $redirectConf, ActiveTheme $activeTheme, DeviceDetectionInterface $deviceDetection)
    {
        $this->activeDevice = $activeDevice;
        $this->redirectConf = $redirectConf;
        $this->activeTheme = $activeTheme;
        $this->deviceDetection = $deviceDetection;
        $this->entityManager = $entityManager;
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
            // force liipTheme based on configured host
            if ($this->redirectConf['mobile']['force_device']) {
                if ($this->getCurrentHost($request) === $this->redirectConf['mobile']['host']) {
                    $this->activeTheme->setName($this->redirectConf['mobile']['mobile_theme']);
                } else {
                    $this->activeTheme->setName($this->redirectConf['mobile']['desktop_theme']);
                }
            }
            
        }
        
        // Redirects to the mobile version
        if ($this->hasMobileRedirect($request, $event)) {
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
    private function hasMobileRedirect($request, $event)
    {
        if (!$this->redirectConf['mobile']['is_enabled']) {
            return false;
        }
        
        $isMobile = $this->deviceDetection->isMobile() && !$this->deviceDetection->isTablet();
        $isMobileHost = ($this->getCurrentHost($request) === $this->redirectConf['mobile']['host']);

        if ($isMobile && !$isMobileHost){
            return true;
        }
        
        if(!$this->pageHasMobileView($request) && $isMobileHost) {
            //$event->setResponse(new RedirectResponse($request->getScheme() . '://' . $this->redirectConf[self::MOBILE]['host'])); 
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
        //checks if exists a mobile view for the current page
        if($this->pageHasMobileView($request)) {
            return $request->getScheme() . '://' . $this->redirectConf[$platform]['host'].$request->getRequestUri();
        } else {
            return $request->getScheme() . '://' . $this->redirectConf[$platform]['host'];
        }
        
    }

   /**
    * Gets the current host.
    *
    * @param Request $request
    * @return string
    */
    private function getCurrentHost($request)
    {
        return $request->getHost();
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 0),
        );
    }
    
    /**
     * Gets the current page from the request parameter after the match, if set, false otherwise
     * 
     * @param type $request
     * @return boolean
     */
    protected function getCurrentPage($request)
    {
        $route_content = $request->get('contentDocument');
        $page = $this->getContent($route_content);
        
        if($page) {
            return $page;
        }
        
        return false;
    }
    
    protected function getContent($route_content)
    {
        $content = null;

        if (!empty($route_content)) {
            list($model, $field, $value) = explode(':', $route_content);
            
            $content = $this->entityManager->getRepository($model)
                    ->findEager($value);
        }

        return $content;
    }
    
    /**
     * Check if current page ( resolved by route ) has a mobile layout or a mobile block inside.
     * @param type $request
     */
    protected function pageHasMobileView($request)
    {
        $page = $this->getCurrentPage($request);

        if($page) {
           
            //checks phone template. If exists return true
            $template = $this->entityManager->getRepository('RaindropRoutingBundle:Route')
                    ->findOneByName($page->getLayout().'|phone');
             
            if($template) {
                return true;
            }

            
            $blocks = $this->getCurrentPage($request)->getBlocks();

            //if at least 1 block inside the template is phone, is ok
            foreach($blocks as $block) {
                
                $vars = explode('|', $block->getTemplate());

                //if exists the phone theme means that mobile exists
                if(isset($vars[1])) {
                    return true;
                }
            }
        }
        
        //no mobile views
        return false;
    }
}
