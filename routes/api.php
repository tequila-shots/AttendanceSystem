<?php

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['auth:api']], function () {
    Route::get('test', function () {
        return response()->json(['foo' => 'bar']);
    });
    Route::get('testing', function() {
        $obj = App\Student::query()->where('class', 'SSMCA-II');
        return response()->json(['type' => get_class($obj)]);
    });
});

Route::prefix('department')->group(function ($router) {
    Route::get('all', 'DepartmentController@getAll');
    Route::post('add', 'DepartmentController@add');
});

Route::prefix('program')->group(function ($router) {
    Route::get('all', 'ProgramController@getAll');
    Route::get('get/dept/{name}', 'ProgramController@get');
    Route::post('add', 'ProgramController@add');
});

Route::prefix('class')->group(function ($router) {
    Route::get('all', 'ClassController@getByDepartment');
    Route::post('add', 'ClassController@add');
});

Route::prefix('student')->group(function ($router) {
    Route::get('class/{prn}', 'StudentController@getClassByPRN');
    Route::post('add/single', 'StudentController@addOne');
    Route::get('list/{class}', 'StudentController@getList');
    Route::get('list/export/{class}', 'StudentController@export');
    Route::get('list/export/{class}/{group}', 'StudentController@export');
    Route::get('list/mail/{class}', 'StudentController@mailExport');
    Route::get('list/mail/{class}/{group}', 'StudentController@mailExport');
});

Route::prefix('subject')->group(function ($router) {
    Route::post('add', 'SubjectController@add');
    Route::get('get/class/{class}', 'SubjectController@getByClass');
    Route::get('get/program/{program}', 'SubjectController@getByProgram');
});

Route::prefix('lecture')->group(function ($router) {
    Route::get('get/{day}/{time}', 'LectureController@getByDay');
    Route::post('proxy', 'LectureController@addProxyLecture');
    Route::post('/', 'LectureController@get');
});

Route::prefix('attendance')->group(function ($router) {
    Route::post('mark', 'AttendanceController@markAttendance');
    Route::post('get', 'AttendanceController@previousAttendance');
    Route::post('get/excel','AttendanceController@getExcel');
});

Route::prefix('stats')->group(function ($router) {
    Route::get('prn/{prn}', 'AttendanceController@getStatsForAll');
    Route::get('prn/{prn}/{subject_id}', 'AttendanceController@getStats');
    Route::get('roll/{roll}/{subject_id}', 'AttendanceController@getStatsByRollNo');
});

Route::post('register', 'AuthController@register');
Route::post('login', 'AuthController@login');
Route::post('recover', 'AuthController@recover');
Route::middleware('auth:api')->get('logout', 'AuthController@logout');

# For 404 error
Route::fallback(function () {
    return response()->json(['success' => false, 'error' => 'Endpoint is invalid'], 404);
});
