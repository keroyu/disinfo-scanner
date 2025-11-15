<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $primaryKey = 'channel_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['channel_id', 'channel_name', 'video_count', 'comment_count', 'first_import_at', 'last_import_at'];

    public function videos()
    {
        return $this->hasMany(Video::class, 'channel_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'channel_tags', 'channel_id', 'tag_id');
    }
}
