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

    protected $fillable = ['channel_id', 'channel_name', 'tag_ids', 'first_import_at', 'last_import_at'];

    protected $casts = [
        'first_import_at' => 'datetime',
        'last_import_at' => 'datetime',
    ];

    public function videos()
    {
        return $this->hasMany(Video::class, 'channel_id');
    }

    /**
     * Get tag IDs as array from comma-separated string
     *
     * @return array
     */
    public function getTagIdsArray(): array
    {
        if (empty($this->tag_ids)) {
            return [];
        }

        return array_map('intval', explode(',', $this->tag_ids));
    }

    /**
     * Set tag IDs from array to comma-separated string
     *
     * @param array $tagIds
     * @return void
     */
    public function setTagIdsFromArray(array $tagIds): void
    {
        $this->tag_ids = empty($tagIds) ? null : implode(',', $tagIds);
    }

    /**
     * Get tags collection based on tag_ids
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function tags()
    {
        $tagIds = $this->getTagIdsArray();
        if (empty($tagIds)) {
            return Tag::whereRaw('1 = 0')->get(); // Return empty collection
        }

        return Tag::whereIn('tag_id', $tagIds)->get();
    }
}
