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
}
