<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesIncludes
{
    private function wantsInclude(Request $request, string $relation): bool
    {
        if (! $request->isMethod('get')) {
            return true;
        }

        return in_array($relation, $this->requestedIncludes($request), true);
    }
    /**
     * @return list<string>
     */
    private function requestedIncludes(Request $request): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $request->query('include', '')),
        )));
    }
}