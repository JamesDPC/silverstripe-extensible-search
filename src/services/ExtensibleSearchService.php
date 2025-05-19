<?php

namespace nglasl\extensible;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ValidationException;

/**
 * Handles the search analytics and suggestions, while providing any additional functionality required by the module.
 * @author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchService
{
    /**
     * Log the details of a user search for analytics.
     *
     * @param string $term
     * @param int $results
     * @param float $time
     * @param string $engine
     * @param int $pageID
     */
    public function logSearch($term, $results, $time, $engine, $pageID)
    {

        // Make sure the search analytics are enabled.

        if (!Config::inst()->get(ExtensibleSearch::class, 'enable_analytics')) {
            return null;
        }

        // Log the details of the user search.

        $search = ExtensibleSearch::create(
            [
                'Term'	=> $term,
                'Results' => $results,
                'Time' => $time,
                'SearchEngine' => $engine,
                'ExtensibleSearchPageID' => $pageID
            ]
        );
        $search->write();

        // Log the details of the user search as a suggestion.

        if ($results > 0) {
            $this->logSuggestion($term, $pageID);
        }

        return $search;
    }

    /**
     * Log a user search generated suggestion.
     *
     * @param string $term
     * @param int $pageID
     */
    public function logSuggestion($term, $pageID)
    {

        // Make sure the search suggestions are enabled, and the search matches the minimum autocomplete length.

        if (!Config::inst()->get(ExtensibleSearchSuggestion::class, 'enable_suggestions') || (strlen((string) $term) < 3)) {
            return null;
        }

        // Make sure the suggestion doesn't already exist.

        $term = strtolower((string) $term);
        $filter = [
            'Term' => $term,
            'ExtensibleSearchPageID' => $pageID
        ];
        $suggestion = ExtensibleSearchSuggestion::get()->filter($filter)->first();

        // Store the frequency to make search suggestion relevance more efficient.

        $frequency = ExtensibleSearch::get()->filter($filter)->count();
        if ($suggestion) {
            $suggestion->Frequency = $frequency;
        } else {

            // Log the suggestion.

            $suggestion = ExtensibleSearchSuggestion::create(
                [
                    'Term' => $term,
                    'Frequency' => $frequency,
                    'Approved' => (int)Config::inst()->get(ExtensibleSearchSuggestion::class, 'automatic_approval'),
                    'ExtensibleSearchPageID' => $pageID
                ]
            );
        }

        // The suggestion might now exist.

        try {
            $suggestion->write();
        } catch (ValidationException) {

            // This indicates a possible race condition.

            $suggestions = ExtensibleSearchSuggestion::get()->filter($filter);
            while ($suggestions->count() > 1) {
                $suggestions->last()->delete();
            }

            $suggestion = $suggestions->first();
            $frequency = ExtensibleSearch::get()->filter($filter)->count();
            $suggestion->Frequency = $frequency;
            $suggestion->write();
        }

        return $suggestion;
    }

    /**
     * Toggle a search suggestion's approval.
     *
     * @param int $ID
     * @return string
     */
    public function toggleSuggestionApproved($ID): ?string
    {

        if ($suggestion = ExtensibleSearchSuggestion::get()->byID($ID)) {

            // Update the search suggestion.

            $approved = !$suggestion->Approved;
            $suggestion->Approved = $approved;
            $suggestion->write();

            // Determine the approval status.

            $status = $approved ? 'Approved' : 'Disapproved';
            return "{$status} \"{$suggestion->Term}\"!";
        } else {
            return null;
        }
    }

    /**
     * Retrieve the search suggestions for a page.
     *
     * @param int $pageID
     * @param int $limit
     * @param bool $approved
     * @return array
     */
    public function getPageSuggestions($pageID, $limit = 0, $approved = true)
    {

        // Make sure the current user has appropriate permission.

        $pageID = (int)$pageID;
        if (($page = \nglasl\extensible\ExtensibleSearchPage::get()->byID($pageID)) && $page->canView()) {

            // Retrieve the search suggestions.

            $suggestions = ExtensibleSearchSuggestion::get()->filter([
                'Approved' => (int)$approved,
                'ExtensibleSearchPageID' => $pageID
            ])->sort('Frequency', 'DESC');
            if ($limit) {
                $suggestions = $suggestions->limit($limit);
            }

            // Make sure the search suggestions are unique.

            return $suggestions->column('Term');
        }

        return [];
    }

    /**
     * Retrieve the most relevant search suggestions.
     *
     * @parame string $term
     * @param int $pageID
     * @param int $limit
     * @param bool $approved
     * @return array
     */
    public function getSuggestions($term, $pageID, ?int $limit = 5, $approved = true)
    {

        // Make sure the search matches the minimum autocomplete length.

        if ($term && (strlen((string) $term) > 2)) {

            // Make sure the current user has appropriate permission.

            $pageID = (int)$pageID;
            if (($page = \nglasl\extensible\ExtensibleSearchPage::get()->byID($pageID)) && $page->canView()) {

                // Retrieve the search suggestions.

                $suggestions = ExtensibleSearchSuggestion::get()->filter([
                    'Term:StartsWith' => $term,
                    'Approved' => (int)$approved,
                    'ExtensibleSearchPageID' => $pageID
                ])->sort('Frequency', 'DESC')->limit($limit);

                // Make sure the search suggestions are unique.

                return $suggestions->column('Term');
            }
        }

        return [];
    }

}
