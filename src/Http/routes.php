<?php

use Asundust\DcatAuthGoogle2Fa\Http\Controllers\DcatAuthGoogle2FaAuthController;
use Asundust\DcatAuthGoogle2Fa\Http\Controllers\DcatAuthGoogle2FaUserController;
use Illuminate\Support\Facades\Route;

if (config('admin.auth.enable', true)) {
    Route::get('auth/login', DcatAuthGoogle2FaAuthController::class . '@getLogin');
    Route::post('auth/login', DcatAuthGoogle2FaAuthController::class . '@postLogin');
    Route::get('auth/setting', DcatAuthGoogle2FaAuthController::class . '@getSetting');
    Route::put('auth/setting', DcatAuthGoogle2FaAuthController::class . '@putSetting');
    Route::resource('auth/users', DcatAuthGoogle2FaUserController::class);
}
