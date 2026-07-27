<?php

namespace App\Http\Controllers;

use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserLoginLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $logs = UserLoginLog::query()
            ->with('user:id,name,alias,email')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($like) {
                    $query->whereRaw('LOWER(user_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(user_alias) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(ip_address) LIKE ?', [$like])
                        ->orWhereHas('user', function ($query) use ($like) {
                            $query->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(alias) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                        });
                });
            })
            ->when($from !== '', fn ($query) => $query->whereDate('logged_in_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('logged_in_at', '<=', $to))
            ->latestLogin()
            ->paginate(25)
            ->withQueryString();

        $totalLogs = UserLoginLog::count();
        $uniqueUsers = UserLoginLog::query()
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        return view('ingresos.index', compact('logs', 'search', 'from', 'to', 'totalLogs', 'uniqueUsers'));
    }
}
