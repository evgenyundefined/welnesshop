<?php

namespace App\Actions\Admin\Categories;

use App\Exceptions\CategoryHasProducts;
use App\Models\Category;

class DeleteCategory
{
    /**
     * @throws CategoryHasProducts
     */
    public function __invoke(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new CategoryHasProducts;
        }

        $category->delete();
    }
}
