<?php

declare(strict_types=1);

namespace Netgen\Bundle\EzPlatformSiteApiBundle\Templating\Twig\Extension;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Values\Content\Content as APIContent;
use eZ\Publish\API\Repository\Values\Content\Location as APILocation;
use eZ\Publish\API\Repository\Values\ValueObject;
use LogicException;
use Netgen\Bundle\EzPlatformSiteApiBundle\View\Builder\ContentViewBuilder;
use Netgen\Bundle\EzPlatformSiteApiBundle\View\ContentView;
use Netgen\Bundle\EzPlatformSiteApiBundle\View\ViewRenderer;
use Netgen\EzPlatformSiteApi\API\Values\Content;
use Netgen\EzPlatformSiteApi\API\Values\Location;

/**
 * Twig extension runtime for Site API content view rendering.
 */
class ContentViewRuntime
{
    /**
     * @var \Netgen\Bundle\EzPlatformSiteApiBundle\View\Builder\ContentViewBuilder
     */
    private $viewBuilder;

    /**
     * @var \Netgen\Bundle\EzPlatformSiteApiBundle\View\ViewRenderer
     */
    private $viewRenderer;

    /**
     * @var \eZ\Publish\API\Repository\LocationService
     */
    private $locationService;

    public function __construct(
        ContentViewBuilder $viewBuilder,
        ViewRenderer $viewRenderer,
        LocationService $locationService
    ) {
        $this->viewBuilder = $viewBuilder;
        $this->viewRenderer = $viewRenderer;
        $this->locationService = $locationService;
    }

    /**
     * Renders the HTML for a given $content.
     *
     * @param \eZ\Publish\API\Repository\Values\ValueObject $value
     * @param string $viewType
     * @param array $parameters
     * @param bool $layout
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @return string
     */
    public function renderContentView(
        ValueObject $value,
        string $viewType,
        array $parameters = [],
        bool $layout = false
    ): string {
        $content = $this->getContent($value);
        $location = $this->getLocation($value);

        $view = $this->viewBuilder->buildView([
            'content' => $content,
            'location' => $location,
            'viewType' => $viewType,
            'layout' => $layout,
            '_controller' => 'ng_content:viewAction',
        ] + $parameters);

        if (!$this->viewMatched($view)) {
            throw new LogicException(
                \sprintf('Could not match view "%s" for Content #%d', $viewType, $content->id)
            );
        }

        return $this->viewRenderer->render($view, $parameters, $layout);
    }

    private function getContent(ValueObject $value): ValueObject
    {
        if ($value instanceof Content || $value instanceof APIContent) {
            return $value;
        }

        if ($value instanceof Location || $value instanceof APILocation) {
            // eZ location also has a lazy loaded "content" property
            return $value->content;
        }

        throw new LogicException('Given value must be Content or Location instance.');
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ValueObject $value
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     *
     * @return null|\eZ\Publish\API\Repository\Values\ValueObject
     */
    private function getLocation(ValueObject $value): ?ValueObject
    {
        if ($value instanceof Location || $value instanceof APILocation) {
            return $value;
        }

        if ($value instanceof Content) {
            return $value->mainLocation ?? null;
        }

        if ($value instanceof APIContent) {
            if ($value->contentInfo->mainLocationId === null) {
                return null;
            }

            // View builder will not load the main location if it is not provided,
            // this makes sure it is available to the template
            return $this->locationService->loadLocation((int) $value->contentInfo->mainLocationId);
        }

        throw new LogicException('Given value must be Content or Location instance.');
    }

    /**
     * This is the only way to check if the view actually matched?
     *
     * @param \Netgen\Bundle\EzPlatformSiteApiBundle\View\ContentView $contentView
     *
     * @return bool
     */
    private function viewMatched(ContentView $contentView): bool
    {
        return !empty($contentView->getConfigHash());
    }
}
