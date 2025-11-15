<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelTag extends Model
{
    use HasFactory;

    protected $table = 'channel_tags';
    public $timestamps = false;

    protected $fillable = ['channel_id', 'tag_id'];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
