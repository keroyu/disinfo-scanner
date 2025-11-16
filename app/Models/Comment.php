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

    protected $fillable = ['comment_id', 'video_id', 'author_channel_id', 'text', 'like_count', 'published_at'];

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
     * Filter comments by keyword across multiple fields
     * Searches: video title, author name, and comment text
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
                ->orWhere('text', 'like', '%' . $keyword . '%');
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
