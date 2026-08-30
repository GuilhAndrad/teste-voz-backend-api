<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait HandlesPagination
{
    protected const DEFAULT_PER_PAGE = 15;

    protected const MAX_PER_PAGE = 100;

    protected function getPerPage(Request $request): int
    {
        return min(
            $request->integer('per_page', self::DEFAULT_PER_PAGE),
            self::MAX_PER_PAGE
        );
    }
}
