<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| API de la aplicación móvil (Flutter).
| Todas las rutas protegidas exigen token de Sanctum y el permiso "Acceso a la aplicación móvil".
*/

Route::middleware(['auth:sanctum', 'mobile.app'])->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
});
