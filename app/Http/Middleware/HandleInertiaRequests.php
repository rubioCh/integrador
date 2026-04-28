<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = [];

        if ($user) {
            $permissions = $user->roles()
                ->with('permissions:id,slug')
                ->get()
                ->flatMap(static fn ($role) => $role->permissions->pluck('slug'))
                ->unique()
                ->values()
                ->all();
        }

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
            ],
            'auth' => [
                'user' => $user?->only(['id', 'username', 'first_name', 'last_name', 'name', 'email']),
                'permissions' => $permissions,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'clients' => Client::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(static fn (Client $client): array => $client->only(['id', 'name', 'slug'])),
        ]);
    }
}
