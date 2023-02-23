<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

/*
|--------------------------------------------------------------------------
| administrator
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['role:administrator']], function () {
    Route::GET('/users', 'Backend\Users\UsersController@index')->name('users');
    Route::GET('/users/add', 'Backend\Users\UsersController@add')->name('users.add');
    Route::POST('/users/create', 'Backend\Users\UsersController@create')->name('users.create');
    Route::GET('/users/edit/{id}', 'Backend\Users\UsersController@edit')->name('users.edit');
    Route::POST('/users/update', 'Backend\Users\UsersController@update')->name('users.update');
    Route::GET('/users/delete/{id}', 'Backend\Users\UsersController@delete')->name('users.delete');

    Route::GET('/settings', 'Backend\Setting\SettingsController@index')->name('settings');
    Route::POST('/settings/update', 'Backend\Setting\SettingsController@update')->name('settings.update');

    Route::GET('/analytic', 'Backend\Analytic\AnalyticController@index')->name('analytic');
});

/*
|--------------------------------------------------------------------------
| administrator|admin|editor|guest
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['role:administrator|admin|staff|guest']], function () {
    Route::GET('/checkProductVerify', 'MainController@checkProductVerify')->name('checkProductVerify');

    Route::GET('/profile/details', 'Backend\Profile\ProfileController@details')->name('profile.details');
    Route::POST('/profile/update', 'Backend\Profile\ProfileController@update')->name('profile.update');
});


/*
|--------------------------------------------------------------------------
| administrator|admin|staff
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['role:administrator|admin|staff']], function () {
    Route::GET('/attendances', 'Backend\Attendance\AttendanceController@index')->name('attendances');
});

/*
|--------------------------------------------------------------------------
| administrator|admin
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['role:administrator|admin']], function () {
    Route::GET('/histories', 'Backend\History\HistoryController@index')->name('histories');
    Route::GET('/histories/add', 'Backend\History\HistoryController@add')->name('histories.add');
    Route::POST('/histories/create', 'Backend\History\HistoryController@create')->name('histories.create');
    Route::GET('/histories/edit/{id}', 'Backend\History\HistoryController@edit')->name('histories.edit');
    Route::POST('/histories/update', 'Backend\History\HistoryController@update')->name('histories.update');
    Route::GET('/histories/delete/{id}', 'Backend\History\HistoryController@delete')->name('histories.delete');

    Route::GET('/histories/import', 'Backend\History\HistoryController@import')->name('histories.import');
    Route::POST('/histories/importData', 'Backend\History\HistoryController@importData')->name('histories.importData');
});

Route::post('reinputkey/index/{code}', 'Utils\Activity\ReinputKeyController@index');
