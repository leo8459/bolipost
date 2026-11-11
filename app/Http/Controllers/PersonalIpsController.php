<?php

namespace App\Http\Controllers;

use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

class PersonalIpsController extends Controller
{
    public function index(Request $request, SitraIpsClient $ips)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 100);
        $error = null;
        $rows = collect();
        $hasMore = false;

        try {
            $response = $ips->users(['q' => $search, 'page' => $page, 'per_page' => $perPage]);
            $rows = collect($response['data'] ?? [])->map(function (array $user): array {
                $validInd = trim((string) ($user['valid_ind'] ?? ''));

                // SITRA considers VALID_IND 1 and 3 current. Never substitute
                // the state of a local BoliPost link for the IPS account state.
                $user['active_status'] = $validInd === '' ? null : in_array($validInd, ['1', '3'], true);
                $user['assigned_offices'] = UserIpsLink::officesFromSnapshot($user);

                return $user;
            });
            $hasMore = (bool) data_get($response, 'meta.has_more', false);
        } catch (\Throwable $exception) {
            report($exception);
            $error = 'No fue posible consultar el personal IPS en SITRA. Intenta nuevamente en unos minutos.';
        }

        $users = new Paginator($rows, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->except('page'),
        ]);
        $users->hasMorePagesWhen($hasMore);

        return view('personal_ips.index', compact('users', 'search', 'perPage', 'error'));
    }
}
