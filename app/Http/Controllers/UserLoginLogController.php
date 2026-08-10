<?php

namespace App\Http\Controllers;

use App\Models\UserLoginLog;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class UserLoginLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $view = $request->query('view') === 'unregistered' ? 'unregistered' : 'history';
        $period = in_array($request->query('period'), ['day', 'month', 'range'], true)
            ? (string) $request->query('period')
            : 'all';
        $day = trim((string) $request->query('day', ''));
        $month = trim((string) $request->query('month', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $timeFrom = trim((string) $request->query('time_from', ''));
        $timeTo = trim((string) $request->query('time_to', ''));

        // Mantiene compatibles los enlaces creados con los filtros anteriores.
        if ($period === 'all' && ($from !== '' || $to !== '')) {
            $period = 'range';
        }

        $logs = UserLoginLog::query()
            ->with([
                'user:id,name,alias,email,empresa_id',
                'user.empresa:id,nombre,sigla',
                'user.roles:id,name',
            ])
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
            ->when($period === 'day' && $this->isDate($day), function ($query) use ($day, $timeFrom, $timeTo) {
                $query->whereDate('logged_in_at', $day)
                    ->when($this->isTime($timeFrom), fn ($query) => $query->whereTime('logged_in_at', '>=', $timeFrom.':00'))
                    ->when($this->isTime($timeTo), fn ($query) => $query->whereTime('logged_in_at', '<=', $timeTo.':59'));
            })
            ->when($period === 'month' && $this->isMonth($month), function ($query) use ($month) {
                [$year, $monthNumber] = array_map('intval', explode('-', $month));
                $query->whereYear('logged_in_at', $year)->whereMonth('logged_in_at', $monthNumber);
            })
            ->when($period === 'range' && $this->isDate($from), fn ($query) => $query->where('logged_in_at', '>=', $from.' 00:00:00'))
            ->when($period === 'range' && $this->isDate($to), fn ($query) => $query->where('logged_in_at', '<=', $to.' 23:59:59'))
            ->latestLogin()
            ->paginate(25)
            ->withQueryString();

        $totalLogs = UserLoginLog::count();
        $uniqueUsers = UserLoginLog::query()
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $unregisteredQuery = $this->activeUnregisteredSessionsQuery();
        $unregisteredCount = $unregisteredQuery ? (clone $unregisteredQuery)->count() : 0;
        $unregisteredSessions = $view === 'unregistered'
            ? $this->paginateUnregisteredSessions($unregisteredQuery)
            : null;

        return view('ingresos.index', compact(
            'logs',
            'search',
            'view',
            'period',
            'day',
            'month',
            'from',
            'to',
            'timeFrom',
            'timeTo',
            'totalLogs',
            'uniqueUsers',
            'unregisteredCount',
            'unregisteredSessions',
        ));
    }

    private function activeUnregisteredSessionsQuery(): ?Builder
    {
        $sessionTable = (string) config('session.table', 'sessions');

        if ($sessionTable === '' || ! Schema::hasTable($sessionTable) || ! Schema::hasTable('users')) {
            return null;
        }

        $activeSince = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        return DB::table($sessionTable.' as active_sessions')
            ->leftJoin('users as session_users', 'session_users.id', '=', 'active_sessions.user_id')
            ->whereNotNull('active_sessions.user_id')
            ->where('active_sessions.last_activity', '>=', $activeSince)
            ->where(function (Builder $query) {
                $query->whereNull('session_users.id')
                    ->orWhereNotNull('session_users.deleted_at');
            });
    }

    private function paginateUnregisteredSessions(?Builder $query): LengthAwarePaginator
    {
        if (! $query) {
            return new LengthAwarePaginator([], 0, 25);
        }

        $sessions = $query
            ->select([
                'active_sessions.id',
                'active_sessions.user_id',
                'active_sessions.ip_address',
                'active_sessions.user_agent',
                'active_sessions.last_activity',
            ])
            ->orderByDesc('active_sessions.last_activity')
            ->paginate(25)
            ->withQueryString();

        $loginLogs = UserLoginLog::query()
            ->whereIn('session_id', collect($sessions->items())->pluck('id'))
            ->latestLogin()
            ->get()
            ->unique('session_id')
            ->keyBy('session_id');

        $sessions->getCollection()->transform(function ($session) use ($loginLogs) {
            $session->login_log = $loginLogs->get($session->id);

            return $session;
        });

        return $sessions;
    }

    private function isDate(string $value): bool
    {
        return $this->matchesFormat($value, 'Y-m-d');
    }

    private function isMonth(string $value): bool
    {
        return $this->matchesFormat($value, 'Y-m');
    }

    private function isTime(string $value): bool
    {
        return $this->matchesFormat($value, 'H:i');
    }

    private function matchesFormat(string $value, string $format): bool
    {
        if ($value === '') {
            return false;
        }

        try {
            return Carbon::createFromFormat('!'.$format, $value)->format($format) === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
