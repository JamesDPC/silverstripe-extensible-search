<?php

namespace nglasl\extensible;

use SilverStripe\ORM\DataObject;

/**
 * This represents an archived search analytic.
 * @author Nathan Glasl <nathan@symbiote.com.au>
 * @property ?string $Term
 * @property int $Frequency
 * @property ?string $FrequencyPercentage
 * @property ?string $AverageTimeTaken
 * @property ?string $Results
 * @property int $ArchiveID
 * @method \nglasl\extensible\ExtensibleSearchArchive Archive()
 */
class ExtensibleSearchArchived extends DataObject
{
    private static string $table_name = 'ExtensibleSearchArchived';

    private static array $db = [
        'Term' => 'Varchar(255)',
        'Frequency' => 'Int',
        'FrequencyPercentage' => 'Varchar(255)',
        'AverageTimeTaken' => 'Varchar(255)',
        'Results' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'Archive' => ExtensibleSearchArchive::class
    ];

    private static array $summary_fields = [
        'Term',
        'Frequency',
        'FrequencyPercentage',
        'AverageTimeTaken',
        'Results'
    ];

    #[\Override]
    public function canEdit($member = null)
    {

        return false;
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {

        return false;
    }

    #[\Override]
    public function canDelete($member = null)
    {

        return false;
    }

    #[\Override]
    public function fieldLabels($includerelations = true)
    {

        return [
            'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
            'Frequency' => _t('EXTENSIBLE_SEARCH.FREQUENCY', 'Frequency'),
            'FrequencyPercentage' => _t('EXTENSIBLE_SEARCH.FREQUENCY_%', 'Frequency %'),
            'AverageTimeTaken' => _t('EXTENSIBLE_SEARCH.AVERAGE_TIME_TAKEN', 'Average Time Taken (s)'),
            'Results' => _t('EXTENSIBLE_SEARCH.HAS_RESULTS?', 'Has Results?')
        ];
    }

}
