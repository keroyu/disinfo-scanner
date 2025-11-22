<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Comment extends Model
{
    use HasFactory;

    protected $primaryKey = 'comment_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['comment_id', 'video_id', 'author_channel_id', 'text', 'like_count', 'published_at', 'parent_comment_id'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_channel_id');
    }

    /**
     * Get the parent comment (for reply comments)
     */
    public function parentComment()
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id', 'comment_id');
    }

    /**
     * Get the child replies to this comment
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_comment_id', 'comment_id');
    }

    /**
     * Filter comments by keyword across multiple fields
     * Searches: video title, author name, author_channel_id, and comment text
     * Note: Escapes LIKE wildcards to prevent performance attacks
     */
    public function scopeFilterByKeyword(Builder $query, string $keyword): Builder
    {
        // Escape LIKE wildcards (%, _, \) to prevent performance attacks
        $escapedKeyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword) . '%';

        return $query->where(function (Builder $q) use ($escapedKeyword) {
            $q->whereHas('video', function (Builder $vq) use ($escapedKeyword) {
                $vq->where('title', 'like', $escapedKeyword);
            })
                ->orWhereHas('author', function (Builder $aq) use ($escapedKeyword) {
                    $aq->where('name', 'like', $escapedKeyword);
                })
                ->orWhere('author_channel_id', 'like', $escapedKeyword)
                ->orWhere('text', 'like', $escapedKeyword);
        });
    }

    /**
     * Filter comments by channel name
     * Note: Escapes LIKE wildcards to prevent performance attacks
     */
    public function scopeFilterByChannel(Builder $query, string $channelKeyword): Builder
    {
        // Escape LIKE wildcards (%, _, \) to prevent performance attacks
        $escapedKeyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $channelKeyword) . '%';

        return $query->whereHas('video.channel', function (Builder $q) use ($escapedKeyword) {
            $q->where('channel_name', 'like', $escapedKeyword);
        });
    }

    /**
     * Filter comments by video ID
     */
    public function scopeFilterByVideo(Builder $query, string $videoId): Builder
    {
        return $query->where('video_id', $videoId);
    }

    /**
     * Filter comments by date range (inclusive)
     */
    public function scopeFilterByDateRange(Builder $query, $fromDate, $toDate): Builder
    {
        $from = $fromDate instanceof Carbon ? $fromDate : Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        $to = $toDate instanceof Carbon ? $toDate : Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();

        return $query->whereBetween('published_at', [$from, $to]);
    }

    /**
     * Filter comments by time period of day (Taipei timezone)
     * Database stores UTC time, but we filter by Taipei time (UTC+8)
     *
     * @param Builder $query
     * @param string $timePeriod - 'daytime' (06:00-17:59), 'evening' (18:00-00:59), 'late_night' (01:00-05:59)
     * @return Builder
     */
    public function scopeFilterByTimePeriod(Builder $query, string $timePeriod): Builder
    {
        return $query->where(function (Builder $q) use ($timePeriod) {
            switch ($timePeriod) {
                case 'daytime': // 白天 Taipei 06:00-17:59
                    $q->whereRaw('HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) >= 6 AND HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) < 18');
                    break;
                case 'evening': // 夜間 Taipei 18:00-00:59 (includes 18:00-23:59 and 00:00-00:59)
                    $q->whereRaw('(HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) >= 18 AND HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) <= 23) OR HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) = 0');
                    break;
                case 'late_night': // 深夜 Taipei 01:00-05:59
                    $q->whereRaw('HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) >= 1 AND HOUR(CONVERT_TZ(published_at, "+00:00", "+08:00")) < 6');
                    break;
            }
        });
    }

    /**
     * Sort comments by like count
     */
    public function scopeSortByLikes(Builder $query, string $direction = 'DESC'): Builder
    {
        return $query->orderBy('like_count', strtoupper($direction));
    }

    /**
     * Sort comments by publication date
     */
    public function scopeSortByDate(Builder $query, string $direction = 'DESC'): Builder
    {
        return $query->orderBy('published_at', strtoupper($direction));
    }

    /**
     * Filter comments by multiple time ranges (OR logic)
     * Used for time-based filtering from Comments Density chart
     *
     * @param Builder $query
     * @param array $timeRanges Array of TimeRange value objects
     * @return Builder
     */
    public function scopeByTimeRanges(Builder $query, array $timeRanges): Builder
    {
        if (empty($timeRanges)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($timeRanges) {
            foreach ($timeRanges as $timeRange) {
                $q->orWhereBetween('published_at', [
                    $timeRange->getFromTimeUtc(),
                    $timeRange->getToTimeUtc()
                ]);
            }
        });
    }
}
