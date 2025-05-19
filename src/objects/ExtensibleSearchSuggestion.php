<?php

namespace nglasl\extensible;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

/**
 * Details of a user search generated suggestion.
 * @author Nathan Glasl <nathan@symbiote.com.au>
 * @property ?string $Term
 * @property int $Frequency
 * @property bool $Approved
 * @property int $ExtensibleSearchPageID
 * @method \nglasl\extensible\ExtensibleSearchPage ExtensibleSearchPage()
 */
class ExtensibleSearchSuggestion extends DataObject implements PermissionProvider
{
    private static string $table_name = 'ExtensibleSearchSuggestion';

    /**
     * Store the frequency to make search suggestion relevance more efficient.
     */
    private static array $db = [
        'Term' => 'Varchar(255)',
        'Frequency' => 'Int',
        'Approved' => 'Boolean'
    ];

    private static array $has_one = [
        'ExtensibleSearchPage' => ExtensibleSearchPage::class
    ];

    private static string $default_sort = 'Frequency DESC, Term ASC';

    private static array $summary_fields = [
        'Term',
        'FrequencySummary',
        'FrequencyPercentage',
        'ApprovedField'
    ];

    private static array $indexes = [
        'Approved' => true,
        'SearchPageID_Approved' => ['type' => 'index', 'columns' => ["ExtensibleSearchPageID","Approved"]],
    ];

    /**
     * Allow the ability to disable search suggestions.
     */
    private static bool $enable_suggestions = true;

    /**
     * Allow the ability to automatically approve user search generated suggestions.
     */
    private static bool $automatic_approval = false;

    /**
     * Create a unique permission for management of search suggestions.
     */
    #[\Override]
    public function providePermissions()
    {

        return [
            'EXTENSIBLE_SEARCH_SUGGESTIONS' => [
                'category' => _t('EXTENSIBLE_SEARCH.EXTENSIBLE_SEARCH', 'Extensible search'),
                'name' => _t('EXTENSIBLE_SEARCH.MANAGE_SEARCH_SUGGESTIONS', 'Manage search suggestions'),
                'help' => 'Allow management of user search generated suggestions.'
            ]
        ];
    }

    #[\Override]
    public function canView($member = null)
    {

        return true;
    }

    #[\Override]
    public function canEdit($member = null)
    {

        return $this->canCreate($member);
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {

        return Permission::checkMember($member, 'EXTENSIBLE_SEARCH_SUGGESTIONS');
    }

    #[\Override]
    public function canDelete($member = null)
    {

        return Permission::checkMember($member, 'EXTENSIBLE_SEARCH_SUGGESTIONS');
    }

    /**
     * Retrieve the search suggestion title.
     *
     * @return string
     */
    #[\Override]
    public function getTitle()
    {

        return $this->Term;
    }

    #[\Override]
    public function getCMSFields()
    {

        $fields = parent::getCMSFields();
        $fields->removeByName('ExtensibleSearchPageID');
        $fields->dataFieldByName('Approved')->setTitle(_t('EXTENSIBLE_SEARCH.APPROVED?', 'Approved?'));

        // Make sure the search suggestions and frequency are read only.

        if ($this->Term) {
            $fields->makeFieldReadonly('Term');
        }

        $fields->removeByName('Frequency');

        // Allow extension customisation.

        $this->extend('updateExtensibleSearchSuggestionCMSFields', $fields);
        return $fields;
    }

    /**
     * Confirm that the current search suggestion is valid.
     */
    #[\Override]
    public function validate()
    {

        $result = parent::validate();

        // Confirm that the current search suggestion matches the minimum autocomplete length and doesn't already exist.

        if ($result->isValid() && (strlen($this->Term) < 3)) {
            $result->addError('Minimum autocomplete length required!');
        } elseif ($result->isValid() && ExtensibleSearchSuggestion::get_one(ExtensibleSearchSuggestion::class, [
            'ID != ?' => (int)$this->ID,
            'Term = ?' => $this->Term,
            'ExtensibleSearchPageID = ?' => $this->ExtensibleSearchPageID
        ])) {
            $result->addError('Suggestion already exists!');
        }

        // Allow extension customisation.

        $this->extend('validateExtensibleSearchSuggestion', $result);
        return $result;
    }

    #[\Override]
    public function fieldLabels($includerelations = true)
    {

        return [
            'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
            'FrequencySummary' => _t('EXTENSIBLE_SEARCH.ANALYTIC_FREQUENCY', 'Analytic Frequency'),
            'FrequencyPercentage' => _t('EXTENSIBLE_SEARCH.ANALYTIC_FREQUENCY_%', 'Analytic Frequency %'),
            'ApprovedField' => _t('EXTENSIBLE_SEARCH.APPROVED?', 'Approved?')
        ];
    }

    /**
     * Retrieve the frequency for display purposes.
     *
     * @return string
     */
    public function getFrequencySummary()
    {

        return $this->Frequency ?: '-';
    }

    /**
     * Retrieve the frequency percentage.
     */
    public function getFrequencyPercentage(): string
    {

        $history = ExtensibleSearch::get()->filter('ExtensibleSearchPageID', $this->ExtensibleSearchPageID);
        return $this->Frequency ? sprintf('%.2f %%', ($this->Frequency / $history->count()) * 100) : '-';
    }

    /**
     * Retrieve the approved field for update purposes.
     *
     * @return string
     */
    public function getApprovedField()
    {

        $approved = CheckboxField::create(
            'Approved',
            '',
            $this->Approved
        )->addExtraClass('approved');

        // Restrict this field appropriately.

        $user = Security::getCurrentUser();
        if (!Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS')) {
            $approved->setAttribute('disabled', 'true');
        }

        return $approved;
    }

}
