<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/admin', 'GET');
$app->instance('request', $req);

$user = App\Models\User::first();
auth()->guard('web')->setUser($user);

$res = $kernel->handle($req);
echo 'STATUS: ' . $res->getStatusCode() . PHP_EOL;
file_put_contents('rendered_admin.html', $res->getContent());
echo 'Saved rendered_admin.html, length: ' . strlen($res->getContent()) . PHP_EOL;
