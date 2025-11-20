<?php

namespace App\ValueObjects;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * TimeRange Value Object
 *
 * Represents a time range for filtering comments with timezone conversion
 * All database times are stored in UTC, but filtering happens in GMT+8 (Asia/Taipei)
 */
class TimeRange
{
    private Carbon $fromTime;
    private Carbon $toTime;
    private string $timezone;

    /**
     * Create a TimeRange from GMT+8 ISO timestamp
     *
     * @param string $timestampIso ISO 8601 timestamp in GMT+8 (e.g., "2025-11-20T14:00:00")
     * @param string $timezone Target timezone (default: 'Asia/Taipei')
     * @throws InvalidArgumentException
     */
    public function __construct(string $timestampIso, string $timezone = 'Asia/Taipei')
    {
        $this->timezone = $timezone;

        try {
            // Parse the timestamp as GMT+8
            $timestamp = Carbon::parse($timestampIso, $this->timezone);

            // Create 1-hour range: timestamp to timestamp + 1 hour
            $this->fromTime = $timestamp->copy();
            $this->toTime = $timestamp->copy()->addHour()->subSecond(); // e.g., 14:00:00 to 14:59:59

        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid ISO timestamp format: {$timestampIso}");
        }
    }

    /**
     * Get start time in UTC for database queries
     */
    public function getFromTimeUtc(): Carbon
    {
        return $this->fromTime->copy()->setTimezone('UTC');
    }

    /**
     * Get end time in UTC for database queries
     */
    public function getToTimeUtc(): Carbon
    {
        return $this->toTime->copy()->setTimezone('UTC');
    }

    /**
     * Get start time in GMT+8 for display
     */
    public function getFromTimeGmt8(): Carbon
    {
        return $this->fromTime->copy();
    }

    /**
     * Get end time in GMT+8 for display
     */
    public function getToTimeGmt8(): Carbon
    {
        return $this->toTime->copy();
    }

    /**
     * Get display string in GMT+8 (e.g., "14:00-15:00")
     */
    public function getDisplayString(): string
    {
        return $this->fromTime->format('H:i') . '-' . $this->toTime->copy()->addSecond()->format('H:i');
    }

    /**
     * Validate ISO timestamp format
     */
    public static function isValidIsoTimestamp(string $timestamp): bool
    {
        try {
            Carbon::parse($timestamp);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create multiple TimeRange objects from comma-separated ISO timestamps
     *
     * @param string $timePointsIso Comma-separated ISO timestamps (e.g., "2025-11-20T08:00:00,2025-11-20T10:00:00")
     * @return array Array of TimeRange objects
     * @throws InvalidArgumentException
     */
    public static function createMultiple(string $timePointsIso): array
    {
        $timestamps = array_map('trim', explode(',', $timePointsIso));
        $timeRanges = [];

        foreach ($timestamps as $timestamp) {
            if (empty($timestamp)) {
                continue;
            }
            $timeRanges[] = new self($timestamp);
        }

        return $timeRanges;
    }
}
