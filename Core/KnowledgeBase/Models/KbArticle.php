<?php

namespace Core\KnowledgeBase\Models;

use Core\Auth\Models\User;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Database\Factories\KbArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KbArticle extends Model
{
    /** @use HasFactory<KbArticleFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'author_id',
        'published_at',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KbArticleStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<KbCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<KbArticle>  $query
     * @return Builder<KbArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', KbArticleStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    protected static function newFactory(): KbArticleFactory
    {
        return KbArticleFactory::new();
    }
}
