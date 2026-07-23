<?php

namespace Core\KnowledgeBase\Models;

use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Database\Factories\KbCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KbCategory extends Model
{
    /** @use HasFactory<KbCategoryFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KbCategoryStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<KbArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    protected static function newFactory(): KbCategoryFactory
    {
        return KbCategoryFactory::new();
    }
}
