<?php

declare(strict_types=1);
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

Route::options('/api/v1/{path:.*}', static fn () => response('', 204));
Route::get('/api/v1/health', [App\Health\Controllers\HealthController::class, 'index']);
Route::get('/api/v1/home/stats', [App\Home\Controllers\HomeStatsController::class, 'index']);
Route::post('/api/v1/anonymous/session', [App\Auth\Controllers\AnonymousSessionController::class, 'issue']);
Route::post('/api/v1/anonymous/session/renew', [App\Auth\Controllers\AnonymousSessionController::class, 'renew']);
Route::post('/api/v1/auth/email-code/send', [App\Auth\Controllers\PlayerAuthController::class, 'sendEmailCode']);
Route::post('/api/v1/auth/register', [App\Auth\Controllers\PlayerAuthController::class, 'register']);
Route::post('/api/v1/auth/login/password', [App\Auth\Controllers\PlayerAuthController::class, 'passwordLogin']);
Route::post('/api/v1/auth/login/email-code', [App\Auth\Controllers\PlayerAuthController::class, 'emailCodeLogin']);
Route::post('/api/v1/auth/token/refresh', [App\Auth\Controllers\PlayerAuthController::class, 'refresh']);
Route::post('/api/v1/auth/logout', [App\Auth\Controllers\PlayerAuthController::class, 'logout']);
Route::post('/api/v1/auth/logout-all', [App\Auth\Controllers\PlayerAuthController::class, 'logoutAll']);
Route::post('/api/v1/auth/password/change', [App\Auth\Controllers\PlayerAuthController::class, 'changePassword']);
Route::post('/api/v1/auth/password/reset', [App\Auth\Controllers\PlayerAuthController::class, 'resetPassword']);
Route::get('/api/v1/me', [App\Auth\Controllers\PlayerAuthController::class, 'me']);
Route::patch('/api/v1/me/username', [App\Auth\Controllers\PlayerAuthController::class, 'changeUsername']);
Route::post('/api/v1/me/username', [App\Auth\Controllers\PlayerAuthController::class, 'changeUsername']);
Route::patch('/api/v1/me/profile', [App\Auth\Controllers\PlayerAuthController::class, 'updateProfile']);
Route::post('/api/v1/me/profile', [App\Auth\Controllers\PlayerAuthController::class, 'updateProfile']);
Route::post('/api/v1/me/email/change', [App\Auth\Controllers\PlayerAuthController::class, 'changeEmail']);
Route::get('/api/v1/me/sessions', [App\Auth\Controllers\PlayerAuthController::class, 'sessions']);
Route::delete('/api/v1/me/sessions', [App\Auth\Controllers\PlayerAuthController::class, 'revokeSession']);
Route::get('/api/v1/questions', [App\Question\Controllers\PublicQuestionController::class, 'index']);
Route::get('/api/v1/questions/read', [App\Question\Controllers\PublicQuestionController::class, 'read']);
Route::get('/api/v1/questions/random', [App\Question\Controllers\PublicQuestionController::class, 'random']);
Route::post('/api/v1/games', [App\Game\Controllers\GameController::class, 'create']);
Route::get('/api/v1/games/read', [App\Game\Controllers\GameController::class, 'read']);
Route::get('/api/v1/games/history', [App\Game\Controllers\GameController::class, 'history']);
Route::post('/api/v1/games/ask', [App\Game\Controllers\GameController::class, 'ask']);
Route::post('/api/v1/games/hint', [App\Game\Controllers\GameController::class, 'hint']);
Route::post('/api/v1/games/guess', [App\Game\Controllers\GameController::class, 'guess']);
Route::post('/api/v1/games/abandon', [App\Game\Controllers\GameController::class, 'abandon']);
Route::get('/api/v1/rooms', [App\Room\Controllers\RoomController::class, 'index']);
Route::get('/api/v1/rooms/read', [App\Room\Controllers\RoomController::class, 'read']);
Route::get('/api/v1/rooms/mine', [App\Room\Controllers\RoomController::class, 'mine']);
Route::post('/api/v1/rooms', [App\Room\Controllers\RoomController::class, 'create']);
Route::post('/api/v1/rooms/join', [App\Room\Controllers\RoomController::class, 'join']);
Route::get('/api/v1/rooms/resolve-question', [App\Room\Controllers\RoomController::class, 'resolveQuestion']);
Route::post('/api/v1/rooms/ready', [App\Room\Controllers\RoomController::class, 'ready']);
Route::post('/api/v1/rooms/start', [App\Room\Controllers\RoomController::class, 'start']);
Route::post('/api/v1/rooms/next', [App\Room\Controllers\RoomController::class, 'next']);
Route::post('/api/v1/rooms/leave', [App\Room\Controllers\RoomController::class, 'leave']);
Route::post('/api/v1/rooms/close', [App\Room\Controllers\RoomController::class, 'close']);
Route::get('/api/v1/donations', [App\Donation\Controllers\PublicDonationController::class, 'index']);
