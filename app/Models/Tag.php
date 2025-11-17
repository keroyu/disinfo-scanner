<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $primaryKey = 'tag_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'description', 'color'];
}
