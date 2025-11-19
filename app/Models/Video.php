<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $primaryKey = 'video_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['video_id', 'channel_id', 'title', 'published_at', 'comment_count'];

    protected $casts = [
        'published_at' => 'datetime',
        'comment_count' => 'integer',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'video_id');
    }

    /**
     * Scope to add computed comment statistics (actual_comment_count and last_comment_time)
     */
    public function scopeWithCommentStats($query)
    {
        return $query->selectRaw('
            videos.*,
            (SELECT COUNT(*) FROM comments WHERE comments.video_id = videos.video_id) as actual_comment_count,
            (SELECT MAX(published_at) FROM comments WHERE comments.video_id = videos.video_id) as last_comment_time
        ');
    }

    /**
     * Scope to filter videos that have at least one comment
     * Note: Must be called AFTER withCommentStats() as it uses the computed actual_comment_count
     */
    public function scopeHasComments($query)
    {
        return $query->having('actual_comment_count', '>', 0);
    }

    /**
     * Scope to search videos by keyword (case-insensitive search in title and channel name)
     */
    public function scopeSearchByKeyword($query, $keyword)
    {
        if (empty($keyword)) {
            return $query;
        }

        return $query->where(function($q) use ($keyword) {
            $q->where('title', 'LIKE', "%{$keyword}%")
              ->orWhereHas('channel', function($channelQuery) use ($keyword) {
                  $channelQuery->where('channel_name', 'LIKE', "%{$keyword}%");
              });
        });
    }

    /**
     * Scope to sort by specified column with direction validation
     */
    public function scopeSortByColumn($query, $column = 'published_at', $direction = 'desc')
    {
        // Whitelist of allowed sort columns
        $allowedColumns = ['published_at', 'actual_comment_count', 'last_comment_time'];

        // Validate column
        if (!in_array($column, $allowedColumns)) {
            $column = 'published_at';
        }

        // Validate direction
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction);
    }

    /**
     * Get the URL for the video analysis page (008-video-comment-density)
     */
    public function analysisUrl(): string
    {
        return route('videos.analysis', ['video' => $this->video_id]);
    }
}
