<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserIpsLink;
use App\Http\Controllers\PaquetesIpsController;
use App\Services\SitraIpsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use App\Menu\Filters\MaintenanceAlertBadgeFilter;
use App\Menu\Filters\RoutePermissionFilter;
use Tests\TestCase;

class PaquetesIpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('adminlte.filters', array_values(array_filter(
            config('adminlte.filters', []),
            static fn (string $filter): bool => ! in_array($filter, [
                MaintenanceAlertBadgeFilter::class,
                RoutePermissionFilter::class,
                GateFilter::class,
            ], true),
        )));
    }

    public function test_it_lists_current_ips_packages_using_the_server_side_token(): void
    {
        config()->set('services.sitra_ips.base_url', 'https://ips.test');
        config()->set('services.sitra_ips.token', '2|secret-token');

        Http::fake([
            'https://ips.test/api/v1/ips/paquetes*' => Http::response([
                'data' => [[
                    'codigo' => 'CP556326695CN',
                    'local_id' => '12',
                    'stage' => ['label' => 'En aduana', 'tone' => 'warning'],
                    'event_at_local' => '2026-08-12T14:17:00-04:00',
                    'operational_event_cd' => 34,
                    'recipient' => 'Destinatario de prueba',
                    'phone' => null,
                    'city' => 'La Paz',
                    'address' => 'Calle de prueba',
                    'office_name' => 'La Paz LC/AO',
                    'next_office_name' => '',
                    'weight_kg' => 4.8,
                    'mail_class' => 'CP',
                    'dutiable_ind' => 'Y',
                    'allowed_actions' => ['EMI'],
                ]],
                'meta' => ['page' => 2, 'per_page' => 25, 'total' => 148],
            ]),
            'https://ips.test/api/v1/ips/catalogos/entrega' => Http::response([
                'data' => ['non_delivery_reasons' => [], 'non_delivery_measures' => []],
            ]),
        ]);

        Cache::forget('sitra:ips:delivery-catalog:v1');
        $request = HttpRequest::create('/ips', 'GET', ['page' => 2, 'q' => 'CP556326695CN']);
        $request->setUserResolver(fn () => $this->linkedOperator());
        $request->setLaravelSession(app('session')->driver());

        $response = app(PaquetesIpsController::class)->index($request, app(SitraIpsClient::class));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(148, $response->getData()['packages']->total());
        $this->assertSame(2, $response->getData()['packages']->currentPage());
        $html = view('paquetes_ips.partials.listing', $response->getData())->render();
        $this->assertStringContainsString('CP556326695CN', $html);
        $this->assertStringContainsString('12/08/2026 14:17', $html);
        $this->assertStringContainsString('Destinatario de prueba', $html);
        $this->assertStringContainsString('La Paz', $html);

        Http::assertSent(function (Request $request): bool {
            $url = parse_url($request->url());
            parse_str($url['query'] ?? '', $query);

            return ($url['path'] ?? '') === '/api/v1/ips/paquetes'
                && ($query['page'] ?? null) === '2'
                && ($query['q'] ?? null) === 'CP556326695CN'
                && ($query['office_cd'] ?? null) === '17'
                && $request->hasHeader('Authorization', 'Bearer 2|secret-token');
        });
    }

    public function test_it_rejects_an_invalid_stage_without_calling_ips(): void
    {
        Http::fake();

        $this->withoutMiddleware()
            ->get(route('ips.index', ['stage' => 'not-a-stage']))
            ->assertRedirect()
            ->assertSessionHasErrors('stage');

        Http::assertNothingSent();
    }

    public function test_legacy_ips_url_redirects_to_the_current_workbench(): void
    {
        Http::fake();

        $response = $this->withoutMiddleware()->get(route('paquetes-ips.index'));

        $response->assertStatus(301)->assertRedirect('/ips');
        Http::assertNothingSent();
    }

    private function linkedOperator(): User
    {
        $user = new User;
        $user->forceFill(['id' => 7001, 'name' => 'Operador IPS', 'email' => 'ips.test@example.invalid']);
        $user->setRelation('ipsLink', new UserIpsLink([
            'ips_user_pid' => 73,
            'ips_office_cd' => 17,
            'ips_office_name' => 'La Paz LC/AO',
            'active' => true,
        ]));

        return $user;
    }
}
