<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => function () use ($user) {
                    if (!$user) {
                        return null;
                    }

                    return array_merge($user->toArray(), [
                        'balanceFloat' => (float) $user->balanceFloat,
                        'balanceUpdatedAt' => $user->updated_at
                    ]);
                },
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'warning' => fn () => $request->session()->get('warning'),
                'data' => fn () => $request->session()->get('data'),
                'event' => fn () => $request->session()->get('event'),
            ],
        ];
    }
}
