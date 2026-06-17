<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\InvalidatesPromptCache;

class ProductObserver
{
    use InvalidatesPromptCache;

    public function saved(Product $product): void
    {
        $this->invalidarCachePrompt();
    }

    public function deleted(Product $product): void
    {
        $this->invalidarCachePrompt();
    }
}
