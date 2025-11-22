<?php

namespace App\Http\Controllers\Traits;

/**
 * Trait for escaping LIKE query wildcards to prevent performance attacks
 *
 * This trait provides a method to escape special characters in LIKE queries
 * to prevent users from injecting wildcards (%, _) that could cause:
 * - Full table scans
 * - Performance degradation
 * - Potential DoS attacks
 */
trait EscapesLikeQueries
{
    /**
     * Escape special LIKE characters in search string
     *
     * Escapes the following characters:
     * - % (percent) - matches any sequence of characters
     * - _ (underscore) - matches any single character
     * - \ (backslash) - escape character itself
     *
     * @param string $value The search string to escape
     * @return string The escaped search string safe for LIKE queries
     */
    protected function escapeLikeString(string $value): string
    {
        // Escape backslash first to prevent double-escaping
        $value = str_replace('\\', '\\\\', $value);

        // Escape LIKE wildcards
        $value = str_replace('%', '\%', $value);
        $value = str_replace('_', '\_', $value);

        return $value;
    }

    /**
     * Build a LIKE pattern with escaped wildcards
     *
     * @param string $value The search string
     * @param string $position Where to add wildcards: 'both' (default), 'start', 'end', 'none'
     * @return string The LIKE pattern with wildcards in the specified positions
     */
    protected function buildLikePattern(string $value, string $position = 'both'): string
    {
        $escaped = $this->escapeLikeString($value);

        return match($position) {
            'start' => "%{$escaped}",
            'end' => "{$escaped}%",
            'none' => $escaped,
            default => "%{$escaped}%", // 'both'
        };
    }
}
