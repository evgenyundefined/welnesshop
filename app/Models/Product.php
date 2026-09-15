<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\StockLevel;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'category_id',
    'slug',
    'name',
    'summary',
    'maturity',
    'supplier',
    'source_url',
    'status',
    'price_minor',
    'currency',
    'stock',
    'weight_grams',
])]
#[RouteKey('slug')]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    // Present on every new instance, so a product reads as freshly unseen
    // rather than as null before it has been read back from the database.
    protected $attributes = [
        'views' => 0,
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The cover leads the gallery, so a single ordering serves both the product
     * page and the admin list.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return HasOne<ProductImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }

    public function isAvailable(): bool
    {
        return $this->status->isVisibleInCatalog() && $this->stock > 0;
    }

    public function stockLevel(): StockLevel
    {
        return StockLevel::fromQuantity($this->stock);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price_minor' => 'integer',
            'weight_grams' => 'integer',
            'stock' => 'integer',
            'views' => 'integer',
        ];
    }
}
