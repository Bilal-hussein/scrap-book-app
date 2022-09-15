<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('avatars', function () {
    return response(['status' => true, 'data' => array('/avatars/1.png', '/avatars/2.png', '/avatars/3.png', '/avatars/4.png', '/avatars/5.png'), 'action' => 'Avatars list']);
});

Route::prefix('user')->group(function () {
    Route::post('signup', [AuthController::class, 'signUp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('forgot/password', [AuthController::class, 'forgotPassword']);
    Route::post('reset/password', [AuthController::class, 'resetPassword']);
    Route::post('change/password', [AuthController::class, 'changePassword']);
    Route::post('verify/account', [AuthController::class, 'verifyAccount']);
    Route::post('update', [AuthController::class, 'updateUser']);
    Route::post('search/{id}', [HomeController::class, 'users']);
    Route::get('search/{id}', [HomeController::class, 'users']);
    Route::get('legacy/code/{id}', [AuthController::class, 'addLegacyCode']);
    Route::post('legacy/code/find', [AuthController::class, 'findLegacyCode']);
    Route::post('legacy/code/find/v1', [AuthController::class, 'findLegacyCodeV1']);
});

Route::prefix('friend')->group(function () {
    Route::post('add', [HomeController::class, 'addFriend']);
    Route::post('remove', [HomeController::class, 'unFriend']);
    Route::post('request/accept', [HomeController::class, 'acceptRequest']);
    Route::get('list/{id}', [HomeController::class, 'friendsList']);
    Route::get('requests/{id}', [HomeController::class, 'requestsList']);
});

Route::prefix('notes')->group(function () {
    Route::post('add', [HomeController::class, 'addNote']);
    Route::post('share', [HomeController::class, 'shareNote']);
    Route::get('my/{id}', [HomeController::class, 'myNotes']);
    Route::get('shared/list/{id}', [HomeController::class, 'sharedNotes']);
});

Route::prefix('files')->group(function () {
    Route::post('share', [HomeController::class, 'shareFiles']);
    Route::get('{type}/list/{id}', [HomeController::class, 'shared']);
    Route::get('delete/{type}/list/{id}', [HomeController::class, 'deleteFiles']);
    // Route::get('picture/list/{id}', [HomeController::class, 'sharedPictures']);
    // Route::get('audio/list/{id}', [HomeController::class, 'sharedMusic']);
    // Route::get('video/list/{id}', [HomeController::class, 'sharedVideos']);
    // Route::get('voice/list/{id}', [HomeController::class, 'sharedVoices']);
});

Route::prefix('passwords')->group(function () {
    Route::post('save', [HomeController::class, 'savePasswords']);
    Route::get('show/{id}', [HomeController::class, 'ViewPassword']);
    Route::get('list/{id}', [HomeController::class, 'AllPasswords']);
});

Route::prefix('reminder')->group(function () {
    Route::post('save', [HomeController::class, 'saveReminder']);
    Route::post('send', [HomeController::class, 'sendReminder']);
    Route::get('list/{id}', [HomeController::class, 'sharedReminders']);
});
