<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;

    protected $primaryKey = 'author_channel_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['author_channel_id', 'name', 'profile_url'];

    public function comments()
    {
        return $this->hasMany(Comment::class, 'author_channel_id');
    }
}
