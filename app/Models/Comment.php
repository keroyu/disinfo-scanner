<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $primaryKey = 'comment_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['comment_id', 'video_id', 'author_channel_id', 'text', 'like_count', 'published_at'];

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_channel_id');
    }
}
