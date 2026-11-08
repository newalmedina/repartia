<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rutas de la API para la app de Flutter (se completarán más adelante con Sanctum).
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
