<?php

namespace nglasl\extensible;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataExtension;
use Symbiote\MultiValueField\Fields\MultiValueDropdownField;

/**
 * Update search form based on configuration
 * Extension can be applied to nglasl\extensible\ExtensibleSearchPageController
 * @author James
 */
class ExtensibleSearchPageExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = [
        'DisplayedSortFields' => 'MultiValueField',
        'UseAdvancedSearch' => 'Boolean'
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'UseAdvancedSearch' => 0
    ];

    /**
     * Apply selectable sort fields to the displayed sort fields
     */
    public function updateExtensibleSearchPageCMSFields(&$fields) {

        if($this->owner->SearchEngine) {

            // set to use advanced search
            $fields->insertBefore(
                'SortBy',
                CheckboxField::create(
                    'UseAdvancedSearch',
                    _t(
                        'nswdpc_searchboilerplate.USE_ADVANCED_SEARCH',
                        'Use advanced search'
                    )
                )->setDescription(
                    _t(
                        'nswdpc_searchboilerplate.USE_ADVANCED_SEARCH_DESCRIPTION',
                        'Show filters next to the search results'
                    )
                )
            );

            // add sort fields that can be displayed
            if($sortByField = $fields->dataFieldByName('SortBy')) {
                $fields->insertAfter(
                    'SortBy',
                    MultiValueDropdownField::create(
                        'DisplayedSortFields',
                        _t(
                            'nswdpc_searchboilerplate.DISPLAYED_SORT_FIELDS',
                            'Displayed sort fields'
                        ),
                        $sortByField->getSource()
                    )
                );
            } else {
                $fields->removeByName(['DisplayedSortFields']);
            }

        } else {
            $fields->removeByName(['DisplayedSortFields','UseAdvancedSearch']);
        }
    }

}
