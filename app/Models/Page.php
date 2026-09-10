<?php

namespace App\Models;

use App\Enums\PageVisibility;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title', 'body', 'position', 'visibility'])]
#[RouteKey('slug')]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    #[Scope]
    protected function listedInMenus(Builder $query): void
    {
        $query->where('visibility', PageVisibility::Published);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'visibility' => PageVisibility::class,
        ];
    }
}
