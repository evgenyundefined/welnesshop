<?php

namespace App\Actions\Admin\Categories;

use App\Models\Category;

class SaveCategory
{
    /**
     * @param  array{slug: string, name: string, description: ?string, position: int}  $attributes
     */
    public function __invoke(array $attributes, ?Category $category = null): Category
    {
        $category ??= new Category;

        $category->fill($attributes)->save();

        return $category->loadCount('products');
    }
}
