<?php

namespace nglasl\extensible;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

/**
 * Details of a user search that are retrieved for analytics.
 * @author Nathan Glasl <nathan@symbiote.com.au>
 * @property ?string $Term
 * @property int $Results
 * @property float $Time
 * @property ?string $SearchEngine
 * @property int $ExtensibleSearchPageID
 * @method \nglasl\extensible\ExtensibleSearchPage ExtensibleSearchPage()
 */
class ExtensibleSearch extends DataObject
{
    private static string $table_name = 'ExtensibleSearch';

    private static array $db = [
        'Term' => 'Varchar(255)',
        'Results' => 'Int',
        'Time' => 'Float',
        'SearchEngine' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'ExtensibleSearchPage' => ExtensibleSearchPage::class
    ];

    private static string $default_sort = 'ID DESC';

    private static array $summary_fields = [
        'Created.Nice',
        'Term',
        'TimeTakenSummary',
        'Results',
        'SearchEngineSummary'
    ];

    /**
     * Allow the ability to disable search analytics.
     */
    private static bool $enable_analytics = true;

    #[\Override]
    public function canView($member = null)
    {

        return true;
    }

    #[\Override]
    public function fieldLabels($includerelations = true)
    {

        return [
            'Created.Nice' => _t('EXTENSIBLE_SEARCH.TIME', 'Time'),
            'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
            'TimeTakenSummary' => _t('EXTENSIBLE_SEARCH.TIME_TAKEN', 'Time Taken (s)'),
            'Results' => _t('EXTENSIBLE_SEARCH.RESULTS', 'Results'),
            'SearchEngineSummary' => _t('EXTENSIBLE_SEARCH.SEARCH_ENGINE', 'Search Engine')
        ];
    }

    /**
     * Retrieve the search time for display purposes.
     */
    public function getTimeTakenSummary(): float
    {

        return round($this->Time, 5);
    }

    /**
     * Retrieve the search engine for display purposes.
     *
     * @return string
     */
    public function getSearchEngineSummary()
    {

        $configuration = Config::inst()->get(ExtensibleSearchPage::class, 'custom_search_engines');
        return $configuration[$this->SearchEngine] ?? $this->SearchEngine;
    }

}
