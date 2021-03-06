<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2020 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Bundle\TrackingBundle\EventListener;

use Pimcore\Tool;
use Pimcore\Http\ResponseHelper;
use Pimcore\Analytics\SiteId\SiteIdProvider;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use CoreShop\Bundle\TrackingBundle\Tracker\Google\TagManager\CodeTracker;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\Templating\EngineInterface;

class GtmDataLayerBlockListener
{
    /**
     * @var PimcoreContextResolver
     */
    protected $pimcoreContextResolver;

    /**
     * @var ResponseHelper
     */
    protected $responseHelper;

    /**
     * @var CodeTracker
     */
    protected $codeTracker;

    /**
     * @var SiteIdProvider
     */
    private $siteIdProvider;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EngineInterface
     */
    private $templatingEngine;

    /**
     * @param PimcoreContextResolver   $pimcoreContextResolver
     * @param ResponseHelper           $responseHelper
     * @param SiteIdProvider           $siteIdProvider
     * @param EventDispatcherInterface $eventDispatcher
     * @param EngineInterface          $templatingEngine
     * @param CodeTracker              $codeTracker
     */
    public function __construct(
        PimcoreContextResolver $pimcoreContextResolver,
        ResponseHelper $responseHelper,
        SiteIdProvider $siteIdProvider,
        EventDispatcherInterface $eventDispatcher,
        EngineInterface $templatingEngine,
        CodeTracker $codeTracker
    ) {
        $this->pimcoreContextResolver = $pimcoreContextResolver;
        $this->responseHelper = $responseHelper;
        $this->siteIdProvider = $siteIdProvider;
        $this->eventDispatcher = $eventDispatcher;
        $this->templatingEngine = $templatingEngine;
        $this->codeTracker = $codeTracker;
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $request = $event->getRequest();
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$this->pimcoreContextResolver->matchesPimcoreContext($request, PimcoreContextResolver::CONTEXT_DEFAULT)) {
            return;
        }

        if (!Tool::useFrontendOutputFilters()) {
            return;
        }

        $serverVars = $event->getRequest()->server;
        if ($serverVars->get('HTTP_X_PURPOSE') === 'preview') {
            return;
        }

        $response = $event->getResponse();
        if (!$this->responseHelper->isHtmlResponse($response)) {
            return;
        }

        $codeHead = $this->generateCode();
        $content = $response->getContent();

        if (!empty($codeHead)) {
            $headEndPosition = stripos($content, '</head>');
            if ($headEndPosition !== false) {
                $content = substr_replace($content, $codeHead . '</head>', $headEndPosition, 7);
            }
        }

        $response->setContent($content);
    }

    /**
     * @return string
     */
    private function generateCode()
    {
        $html = '';
        foreach ($this->codeTracker->getBlocks() as $code) {
            $html .= $code . "\n";
        }

        return '<script>' . "\n" . $html . '</script>';
    }
}
