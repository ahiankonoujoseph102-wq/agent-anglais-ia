<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->with('activeSubscription')
            ->when($search !== '', function (Builder $query) use ($search) {
                $digits = preg_replace('/\D+/', '', PhoneNumber::normalize($search) ?? $search);

                $query->where(function (Builder $query) use ($search, $digits) {
                    $query->where('name', 'like', '%'.$search.'%');

                    if (strlen($digits) >= 3) {
                        $query->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'total' => User::count(),
        ]);
    }
}
