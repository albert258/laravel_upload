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
Route::group(['middleware' => 'web'], function () {
    Route::get('/','UploadController@index');
    if ( \AetherUpload\Util::isStorageHost() ) {
        Route::post('upload/preprocess', 'UploadController@preprocess')->middleware(config('aetherupload.middleware_preprocess'));
        Route::options('upload/preprocess', 'UploadController@options');
        Route::post('upload/uploading', 'UploadController@saveChunk')->middleware(config('aetherupload.middleware_uploading'));
        Route::options('upload/uploading', 'UploadController@options');
        Route::post('upload/delete', 'UploadController@delete');
        Route::post('upload/download', 'UploadController@download');
    }
});
