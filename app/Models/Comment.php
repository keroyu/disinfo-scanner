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
     */
    public function scopeFilterByKeyword(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword) {
            $q->whereHas('video', function (Builder $vq) use ($keyword) {
                $vq->where('title', 'like', '%' . $keyword . '%');
            })
                ->orWhereHas('author', function (Builder $aq) use ($keyword) {
                    $aq->where('name', 'like', '%' . $keyword . '%');
                })
                ->orWhere('author_channel_id', 'like', '%' . $keyword . '%')
                ->orWhere('text', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * Filter comments by channel name
     */
    public function scopeFilterByChannel(Builder $query, string $channelKeyword): Builder
    {
        return $query->whereHas('video.channel', function (Builder $q) use ($channelKeyword) {
            $q->where('channel_name', 'like', '%' . $channelKeyword . '%');
        });
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
}
