<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

trait HandlesRestrictedDelete
{
    protected function restrictedDelete(Model $model, string $redirectRoute, string $errorMessage): RedirectResponse
    {
        try {
            $model->delete();
        } catch (QueryException $e) {
            if ($e->errorInfo[0] === '23503') {
                return back()->withErrors(['delete' => $errorMessage]);
            }

            throw $e;
        }

        return to_route($redirectRoute);
    }
}
