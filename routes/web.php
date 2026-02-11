<?php

use App\Jobs\ImportAllData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/setup/import-data', function (Request $request) {
    // Proteção simples: exige ?key=SEU_APP_KEY (ou outra string secreta)
    if ($request->query('key') !== config('app.key')) {
        abort(403, 'Unauthorized');
    }

    ImportAllData::dispatch();

    return 'Importação iniciada em background! Verifique os logs.';
});
