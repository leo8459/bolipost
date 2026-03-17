<?php

<<<<<<< HEAD
return [
    App\Providers\AppServiceProvider::class,
];
=======
$providers = [
    App\Providers\AppServiceProvider::class,
];

if ((bool) env('TELESCOPE_ENABLED', false)) {
    $providers[] = App\Providers\TelescopeServiceProvider::class;
}

return $providers;
>>>>>>> a41ccfb (Uchazara)
