<?php

namespace nglasl\extensible;

use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extension;
use Symbiote\Multisites\Multisites;

/**
 * This extension is used to implement a search form, primarily outside the search page.
 * @author Nathan Glasl <nathan@symbiote.com.au>
 * @extends \SilverStripe\Core\Extension<static>
 */
class ExtensibleSearchExtension extends Extension
{
    private static array $allowed_actions = [
        'getSearchForm'
    ];

    /**
     * Instantiate the search form, primarily outside the search page.
     */
    public function getSearchForm(?HTTPRequest $request = null, bool|string $sorting = false)
    {

        // Instantiate the search form, primarily excluding the sorting selection.

        $page = $this->getOwner()->getSearchPage();
        if ($page instanceof ExtensibleSearchPage) {
            /** @var ExtensibleSearchPageController $controller */
            $controller = ModelAsController::controller_for($page);
            return $controller->getSearchForm($request, $sorting);
        }

        return null;
    }

    /**
     * Retrieve the search page.
     *
     */
    public function getSearchPage(): ?ExtensibleSearchPage
    {

        $pages = ExtensibleSearchPage::get();

        // This is required to support multiple sites.

        if (class_exists(Multisites::class)) {
            $pages = $pages->filter('SiteID', $this->getOwner()->SiteID);
        }

        return $pages->first();
    }

}
