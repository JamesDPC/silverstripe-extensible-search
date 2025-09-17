<?php

namespace nglasl\extensible;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Search\FulltextSearchable;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Symbiote\Multisites\Model\Site;
use Symbiote\Multisites\Multisites;

/**
 * The page used to display search results, analytics and suggestions, allowing user customisation and developer extension.
 * @author Nathan Glasl <nathan@symbiote.com.au>
 * @property ?string $SearchEngine
 * @property ?string $SortBy
 * @property ?string $SortDirection
 * @property bool $StartWithListing
 * @property int $ResultsPerPage
 * @method \SilverStripe\ORM\HasManyList<\nglasl\extensible\ExtensibleSearch> History()
 * @method \SilverStripe\ORM\HasManyList<\nglasl\extensible\ExtensibleSearchArchive> Archives()
 * @method \SilverStripe\ORM\HasManyList<\nglasl\extensible\ExtensibleSearchSuggestion> Suggestions()
 * @method \SilverStripe\ORM\ManyManyList<\SilverStripe\CMS\Model\SiteTree> SearchTrees()
 */
class ExtensibleSearchPage extends \Page
{
    private static string $table_name = 'ExtensibleSearchPage';

    private static array $db = [
        'SearchEngine' => 'Varchar(255)',
        'SortBy' => 'Varchar(255)',
        'SortDirection' => "Enum('DESC, ASC', 'DESC')",
        'StartWithListing' => 'Boolean',
        'ResultsPerPage' => 'Int'
    ];

    private static array $defaults = [
        'ShowInMenus' => 0,
        'ShowInSearch' => 0,
        'ResultsPerPage' => 10
    ];

    private static array $has_many = [
        'History' => ExtensibleSearch::class,
        'Archives' => ExtensibleSearchArchive::class,
        'Suggestions' => ExtensibleSearchSuggestion::class
    ];

    private static array $many_many = [
        'SearchTrees' => SiteTree::class
    ];

    private static string $icon = 'nglasl/silverstripe-extensible-search: client/images/search.png';

    /**
     * The search engines that are available.
     */
    private static array $custom_search_engines = [];

    /**
     * The full-text search engine does not support hierarchy filtering.
     */
    public $supports_hierarchy = false;

    /**
     * Instantiate a search page, should one not exist.
     */
    #[\Override]
    public function requireDefaultRecords()
    {

        parent::requireDefaultRecords();
        $stage = Versioned::get_stage() ?: 'Stage';
        Versioned::set_stage('Stage');

        // Determine whether pages should be created.

        if (!self::config()->get('create_default_pages')) {
            return;
        }

        // This is required to support multiple sites.

        if (class_exists(Site::class)) {
            foreach (Site::get() as $site) {

                // The problem is that class name mapping happens after this, but we need it right now to query pages.

                if (!SiteTree::get()->filter([
                    'ClassName' => [
                        ExtensibleSearchPage::class,
                        'ExtensibleSearchPage'
                    ],
                    'SiteID' => $site->ID
                ])->first()) {

                    // Instantiate an extensible search page.

                    $page = ExtensibleSearchPage::create();
                    $page->ParentID = $site->ID;
                    $page->Title = 'Search Page';
                    $page->write();
                    DB::alteration_message("\"{$site->Title}\" Extensible Search Page", 'created');
                }
            }
        } elseif (!SiteTree::get()->filter('ClassName', [
            ExtensibleSearchPage::class,
            'ExtensibleSearchPage'
        ])->first()) {
            // The problem is that class name mapping happens after this, but we need it right now to query pages.
            // Instantiate an extensible search page.
            $page = ExtensibleSearchPage::create();
            $page->Title = 'Search Page';
            $page->write();
            DB::alteration_message('"Default" Extensible Search Page', 'created');
        }

        Versioned::set_stage($stage);
    }

    /**
     * Display the search engine specific configuration, and the search page specific analytics and suggestions.
     */
    #[\Override]
    public function getCMSFields()
    {

        $fields = parent::getCMSFields();
        Requirements::css('nglasl/silverstripe-extensible-search: client/css/extensible-search.css');

        // Determine the search engines that are available.

        $engines = [];
        foreach (self::config()->get('custom_search_engines') as $engine => $display) {

            // The search engines may define an optional display title.

            if (is_numeric($engine)) {
                $engine = $display;
            }

            // Determine whether the search engines exist.

            if (ClassInfo::exists($engine)) {
                $engines[$engine] = $display;
            }
        }

        // Determine whether the full-text search engine is available.

        $configuration = Config::inst();
        $classes = $configuration->get(FulltextSearchable::class, 'searchable_classes');
        if (is_array($classes) && ($classes !== [])) {
            $engines['Full-Text'] = 'Full-Text';
        }

        // Display the search engine selection.

        $fields->addFieldToTab('Root.Main', DropdownField::create(
            'SearchEngine',
            _t('EXTENSIBLE_SEARCH.SEARCH_ENGINE', 'Search Engine'),
            $engines
        )->setHasEmptyDefault(true)->setDescription('This needs to be saved before further customisation is available'), 'Title');

        // Determine whether a search engine has been selected.

        if ($this->SearchEngine && isset($engines[$this->SearchEngine])) {

            // Display a search engine specific notice.

            $fields->addFieldToTab('Root.Main', LiteralField::create(
                'SearchEngineNotice',
                "<p class='extensible-search notice'><strong>{$engines[$this->SearchEngine]} Search Page</strong></p>"
            ), 'Title');

            // Determine whether the search engine supports hierarchy filtering.

            $hierarchy = $this->supports_hierarchy;
            if ($this->SearchEngine !== 'Full-Text') {
                $hierarchy = singleton($this->SearchEngine)->supports_hierarchy;
            }

            // The search engine may only support limited hierarchy filtering for multiple sites.

            if ($hierarchy || class_exists(Multisites::class)) {

                // Display the search trees selection.

                $fields->addFieldToTab('Root.Main', $tree = TreeMultiselectField::create(
                    'SearchTrees',
                    _t('EXTENSIBLE_SEARCH.SEARCH_TREES', 'Search Trees'),
                    SiteTree::class
                ), 'Content');

                // Determine whether the search engine only supports limited hierarchy filtering.

                if (!$hierarchy) {

                    // Update the search trees to reflect this.

                    $tree->setDisableFunction(fn ($page): bool => $page->ParentID != 0);
                    $tree->setDescription('This <strong>search engine</strong> only supports limited hierarchy');
                }
            }

            // Display the sorting selection.

            $fields->addFieldToTab('Root.Main', DropdownField::create(
                'SortBy',
                _t('EXTENSIBLE_SEARCH.SORT_BY', 'Sort By'),
                $this->getSelectableFields()
            ), 'Content');
            $fields->addFieldToTab('Root.Main', DropdownField::create(
                'SortDirection',
                _t('EXTENSIBLE_SEARCH.SORT_DIRECTION', 'Sort Direction'),
                [
                    'DESC' => _t('EXTENSIBLE_SEARCH.DESCENDING', 'Descending'),
                    'ASC' => _t('EXTENSIBLE_SEARCH.ASCENDING', 'Ascending')
                ]
            ), 'Content');

            // Display the start with listing selection.

            $fields->addFieldToTab('Root.Main', CheckboxField::create(
                'StartWithListing',
                _t('EXTENSIBLE_SEARCH.START_WITH_LISTING?', 'Start With Listing?')
            )->addExtraClass('start-with-listing'), 'Content');

            // Display the results per page selection.

            $fields->addFieldToTab('Root.Main', NumericField::create(
                'ResultsPerPage',
                _t('EXTENSIBLE_SEARCH.RESULTS_PER_PAGE', 'Results Per Page')
            ), 'Content');
        } else {

            // The search engine has not been selected.

            $fields->addFieldToTab('Root.Main', LiteralField::create(
                'SearchEngineNotification',
                "<p class='extensible-search notification'><strong>Select a Search Engine</strong></p>"
            ), 'Title');
        }

        // Determine whether analytics have been enabled.

        if ($configuration->get(ExtensibleSearch::class, 'enable_analytics')) {

            // Instantiate the analytic summary.

            $fields->findOrMakeTab('Root.SearchAnalytics.Current', _t('EXTENSIBLE_SEARCH.CURRENT', 'Current'));
            $fields->findOrMakeTab('Root.SearchAnalytics')->setTitle(_t('EXTENSIBLE_SEARCH.SEARCH_ANALYTICS', 'Search Analytics'));
            $fields->addFieldToTab('Root.SearchAnalytics.Current', $summary = GridField::create(
                'HistorySummary',
                _t('EXTENSIBLE_SEARCH.SUMMARY', 'Summary'),
                $this->getHistorySummary()
            )->setModelClass(ExtensibleSearch::class));
            $summaryConfiguration = $summary->getConfig();

            // Update the display columns.

            $summaryDisplay = singleton(ExtensibleSearchArchived::class)->fieldLabels();
            $summaryConfiguration->getComponentByType(GridFieldDataColumns::class)->setDisplayFields($summaryDisplay);

            // Instantiate an export button.

            if ($this->getHistorySummary()->exists()) {
                $summaryConfiguration->addComponent($summaryExport = GridFieldExportButton::create());
                $summaryExport->setExportColumns($summaryDisplay);
            }

            // Update the custom summary fields to be sortable.

            $summaryConfiguration->getComponentByType(GridFieldSortableHeader::class)->setFieldSorting([
                'FrequencyPercentage' => 'Frequency'
            ]);
            $summaryConfiguration->removeComponentsByType(GridFieldFilterHeader::class);

            // Instantiate the analytic history.

            $fields->addFieldToTab('Root.SearchAnalytics.Current', $history = GridField::create(
                'History',
                _t('EXTENSIBLE_SEARCH.HISTORY', 'History'),
                $this->History()
            )->setModelClass(ExtensibleSearch::class));
            $historyConfiguration = $history->getConfig();

            // Update the custom summary fields to be sortable.

            $historyConfiguration->getComponentByType(GridFieldSortableHeader::class)->setFieldSorting([
                'Created.Nice' => 'Created',
                'TimeTakenSummary' => 'Time',
                'SearchEngineSummary' => 'SearchEngine'
            ]);
            $historyConfiguration->removeComponentsByType(GridFieldFilterHeader::class);

            // Instantiate the archived collection of search analytics.

            $archives = $this->Archives();
            if ($archives->exists() && $archives->first()->canView()) {
                $fields->findOrMakeTab('Root.SearchAnalytics.Archives', _t('EXTENSIBLE_SEARCH.ARCHIVES', 'Archives'));
                $fields->addFieldToTab('Root.SearchAnalytics.Archives', GridField::create(
                    'Archives',
                    _t('EXTENSIBLE_SEARCH.ARCHIVES', 'Archives'),
                    $archives,
                    $archivesConfiguration = GridFieldConfig_RecordEditor::create()
                )->setModelClass(ExtensibleSearchArchive::class));

                // Update the custom summary fields to be sortable.

                $archivesConfiguration->getComponentByType(GridFieldSortableHeader::class)->setFieldSorting([
                    'TitleSummary' => 'StartingDate'
                ]);
                $archivesConfiguration->removeComponentsByType(GridFieldFilterHeader::class);
            }
        }

        // Determine whether suggestions have been enabled.

        if ($configuration->get(ExtensibleSearchSuggestion::class, 'enable_suggestions')) {

            // Appropriately restrict the approval functionality.

            $user = Security::getCurrentUser();
            if (Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS')) {
                Requirements::javascript('nglasl/silverstripe-extensible-search: client/javascript/extensible-search-approval.js');
            }

            // Determine the search page specific suggestions.

            $fields->findOrMakeTab('Root.SearchSuggestions', _t('EXTENSIBLE_SEARCH.SEARCH_SUGGESTIONS', 'Search Suggestions'));
            $fields->addFieldToTab('Root.SearchSuggestions', GridField::create(
                'Suggestions',
                _t('EXTENSIBLE_SEARCH.SUGGESTIONS', 'Suggestions'),
                $this->Suggestions(),
                $suggestionsConfiguration =
                    singleton(ExtensibleSearchSuggestion::class)->canEdit() ? GridFieldConfig_RecordEditor::create() : GridFieldConfig_Base::create()
            )->setModelClass(ExtensibleSearchSuggestion::class));

            // Update the custom summary fields to be sortable.

            $suggestionsConfiguration->getComponentByType(GridFieldSortableHeader::class)->setFieldSorting([
                'FrequencySummary' => 'Frequency',
                'FrequencyPercentage' => 'Frequency',
                'ApprovedField' => 'Approved'
            ]);
            $suggestionsConfiguration->removeComponentsByType(GridFieldFilterHeader::class);
        }

        // Allow extension customisation.

        $this->extend('updateExtensibleSearchPageCMSFields', $fields);
        return $fields;
    }

    #[\Override]
    public function onBeforeWrite()
    {

        parent::onBeforeWrite();

        // Determine whether a new search engine has been selected.

        $changed = $this->getChangedFields();
        if ($this->SearchEngine && isset($changed['SearchEngine']) && ($changed['SearchEngine']['before'] != $changed['SearchEngine']['after'])) {

            // Determine whether the sort by is a selectable field.

            $selectable = $this->getSelectableFields();
            if (!isset($selectable[$this->SortBy])) {

                // Initialise the default search engine specific sort by.

                $this->SortBy = ($this->SearchEngine !== 'Full-Text') ? 'LastEdited' : 'Relevance';
            }
        }

        // Initialise the default search engine specific sort direction.

        if (!$this->SortDirection) {
            $this->SortDirection = 'DESC';
        }
    }

    /**
     * Determine the search engine specific selectable fields, primarily for sorting.
     */
    public function getSelectableFields(): array
    {

        // Instantiate some default selectable fields, just in case the search engine does not provide any.

        $selectable = [
            'LastEdited' => _t('EXTENSIBLE_SEARCH.LAST_EDITED', 'Last Edited'),
            'ID' => _t('EXTENSIBLE_SEARCH.CREATED', 'Created'),
            'ClassName' => _t('EXTENSIBLE_SEARCH.TYPE', 'Type')
        ];

        // Determine the search engine that has been selected.

        if (($this->SearchEngine !== 'Full-Text') && ClassInfo::exists($this->SearchEngine)) {

            // Determine the search engine specific selectable fields.

            $fields = singleton($this->SearchEngine)->getSelectableFields($this);
            if (!$fields) {
                // If null or falsey type, change to array
                $fields = [];
            }

            return $fields + $selectable;
        } elseif (($this->SearchEngine === 'Full-Text') && is_array($classes = Config::inst()->get(FulltextSearchable::class, 'searchable_classes')) && ($classes !== [])) {

            // Determine the full-text specific selectable fields.

            $selectable = [
                'Relevance' => _t('EXTENSIBLE_SEARCH.RELEVANCE', 'Relevance')
            ] + $selectable;
            foreach ($classes as $class) {
                $fields = DataObject::getSchema()->databaseFields($class);

                // Determine the most appropriate fields, primarily for sorting.

                if (isset($fields['Title'])) {
                    $selectable['Title'] = _t('EXTENSIBLE_SEARCH.TITLE', 'Title');
                }

                if (isset($fields['MenuTitle'])) {
                    $selectable['MenuTitle'] = _t('EXTENSIBLE_SEARCH.NAVIGATION_TITLE', 'Navigation Title');
                }

                if (isset($fields['Sort'])) {
                    $selectable['Sort'] = _t('EXTENSIBLE_SEARCH.DISPLAY_ORDER', 'Display Order');
                }

                // This is specific to file searching.

                if (isset($fields['Name'])) {
                    $selectable['Name'] = _t('EXTENSIBLE_SEARCH.FILE_NAME', 'File Name');
                }
            }
        }

        // Allow extension customisation, so custom fields may be selectable.

        $this->extend('updateExtensibleSearchPageSelectableFields', $selectable);
        return $selectable;
    }

    /**
     * Determine the search page specific analytics.
     *
     */
    public function getHistorySummary(): ArrayList
    {

        
        if(!$this->isInDB()) {
            return ArrayList::create();
        }

        // Count of linked records
        $countQuery = new SQLSelect(
            "COUNT(ID) AS RecordCount",
            "ExtensibleSearch",
            [
                "ExtensibleSearchPageID = ?" => $this->ID
            ]
        );
        $historyCount = 0;
        if($countResult = $countQuery->execute()) {
            $countRecord = $countResult->record();
            $historyCount = (int)$countRecord["RecordCount"] ?? 0;
        }

        // Frequency percentage field expression
        $frequencyPercentage = "";
        if($historyCount == 0 ) {
            $frequencyPercentage = "NULL";
        } else {
            $frequencyPercentage = "((COUNT(\"ID\") * 100.00) / {$historyCount})";
        }
        $query = new SQLSelect(
            "\"Term\", COUNT(\"ID\") AS \"Frequency\","
            . " {$frequencyPercentage} AS \"FrequencyPercentage\","
            . " AVG(\"Time\") AS \"AverageTimeTaken\","
            . " IF(\"Results\" > 0, 1, 0) AS \"HasResults\"",
            "\"ExtensibleSearch\"",
            [
                "\"ExtensibleSearchPageID\" = ?" => $this->ID
            ],
            [
                "\"Frequency\"" => "DESC",
                "\"Term\"" => "ASC"
            ],
            [
                "\"Term\"",
                "\"Results\""
            ]
        );

        // These will require display formatting.
        $analytics = ArrayList::create();
        foreach ($query->execute() as $result) {
            $result = ArrayData::create(
                $result
            );
            $result->FrequencyPercentage = sprintf('%.2f %%', $result->FrequencyPercentage);
            $result->AverageTimeTaken = sprintf('%.5f', $result->AverageTimeTaken);
            $result->Results = $result->HasResults == 1 ? _t('EXTENSIBLE_SEARCH.TRUE', 'true') : _t('EXTENSIBLE_SEARCH.FALSE', 'false');
            $analytics->push($result);
        }

        return $analytics;
    }

}
