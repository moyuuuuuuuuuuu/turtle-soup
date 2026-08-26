<?php
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
Route::post('/api/v1/anonymous/session', [App\Auth\Controllers\AnonymousSessionController::class, 'issue']);
Route::post('/api/v1/anonymous/session/renew', [App\Auth\Controllers\AnonymousSessionController::class, 'renew']);
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
