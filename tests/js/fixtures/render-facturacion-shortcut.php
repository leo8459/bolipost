<?php

// Isolated renderer for the browser test: never connect to the application database.
foreach ([
    'APP_ENV' => 'testing', 'APP_URL' => $argv[1], 'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false',
] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Browser fixture requires an in-memory SQLite database.');
}

Illuminate\Support\Facades\Schema::create('app_settings', function ($table): void {
    $table->string('key');
    $table->text('value')->nullable();
});
$app['session']->start();
$request = Illuminate\Http\Request::create($argv[1].'/inicio');
$request->setLaravelSession($app['session']->driver());
$app->instance('request', $request);
$app['url']->forceRootUrl($argv[1]);

$user = Mockery::mock(App\Models\User::class)->makePartial();
$user->fill(['name' => 'Caja de prueba', 'email' => 'test@example.test']);
$user->shouldReceive('can')->andReturn(true);
$user->id = 1;
$user->setRelation('roles', new Illuminate\Database\Eloquent\Collection);
Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
Illuminate\Support\Facades\Gate::before(fn () => true);

$service = Mockery::mock(App\Services\FacturacionCartService::class);
$service->shouldReceive('getRemoteContextForUser')->andReturn(['draft' => null, 'last' => null]);
$service->shouldReceive('fetchCajaEstado')->andReturn(['estado' => 'SIN_APERTURA']);
$app->instance(App\Services\FacturacionCartService::class, $service);
$policy = Mockery::mock(App\Support\FacturacionModePolicy::class);
$policy->shouldReceive('allows')->andReturn(true);
$app->instance(App\Support\FacturacionModePolicy::class, $policy);

echo json_encode([
    'launcher' => view('partials.facturacion-shortcut-launcher')->render(),
    'fragment' => view('partials.facturacion-shortcut')->render(),
], JSON_THROW_ON_ERROR);
