<?php

namespace App\Http\Controllers;

use AetherUpload\ConfigMapper;
use AetherUpload\RedisSavedPath;
use AetherUpload\Responser;
use AetherUpload\UploadController as Ucontroller;
use App\Components\Upload\MyResource;
use App\Components\Upload\MyUtil;
use App\Components\Upload\MyPartialResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Http\Request as HttpRequest;
use App\Models\Upload;
use Illuminate\Support\Facades\App;

class UploadController extends Ucontroller
{
    public $sCacheKey;
    public function __construct()
    {
        parent::__construct();
        $this->sCacheKey = Upload::getCacheKey();
        App::setLocale(Request::input('locale', 'en'));

        // add AetherUploadCORS middleware to the storage server when distributed deployment is enabled
        if (MyUtil::isDistributedStorageHost()) {
            $this->middleware(ConfigMapper::get('distributed_deployment_middleware_cors'));
        }
    }

    public function index()
    {
        $model = new Upload();
        $aDatas = $model->getData();
        return view('upload.index')->with('aDatas', $aDatas);
    }

    /**
     * Preprocess the upload request
     * @return \Illuminate\Http\JsonResponse
     */
    public function preprocess()
    {
        $resourceName = Request::input('resource_name', false);
        $resourceSize = Request::input('resource_size', false);
        $resourceHash = Request::input('resource_hash', false);
        $product = Request::input('product', false);
        $identify = Request::input('identify', false);
        $group = Request::input('group', false);

        $result = [
            'error'                => 0,
            'chunkSize'            => 0,
            'groupSubDir'          => '',
            'resourceTempBaseName' => '',
            'resourceExt'          => '',
            'savedPath'            => '',
        ];

        try {

            // prevents uploading files to the application server when distributed deployment is enabled
            if (MyUtil::isDistributedWebHost()) {
                throw new \Exception(trans('aetherupload::messages.upload_error'));
            }

            if ($resourceSize === false || $resourceName === false || $group === false) {
                throw new \Exception(trans('aetherupload::messages.invalid_resource_params'));
            }

            ConfigMapper::instance()->applyGroupConfig($group);

            $result['resourceTempBaseName'] = $resourceTempBaseName = MyUtil::generateTempName($identify);
            $result['resourceExt'] = $resourceExt = strtolower(pathinfo($resourceName, PATHINFO_EXTENSION));
            $result['groupSubDir'] = $groupSubDir = MyUtil::generateSubDirName($product,$identify);
            $result['chunkSize'] = ConfigMapper::get('chunk_size');

            $partialResource = new MyPartialResource($resourceTempBaseName, $resourceExt, $groupSubDir);

            $partialResource->filterBySize($resourceSize);

            $partialResource->filterByExtension($resourceExt);

            // determine if this upload meets the condition of instant completion
            if (empty($resourceHash) === false && ConfigMapper::get('instant_completion') === true && RedisSavedPath::exists($savedPathKey = MyUtil::getSavedPathKey($group, $resourceHash)) === true) {
                $result['savedPath'] = RedisSavedPath::get($savedPathKey);

                return Responser::returnResult($result);
            }

            $partialResource->create();

            $partialResource->chunkIndex = 0;

        } catch (\Exception $e) {

            return Responser::reportError($result, $e->getMessage());
        }

        return Responser::returnResult($result);
    }

    /**
     * Handle and save the uploaded chunks
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function saveChunk()
    {
        $chunkTotalCount = Request::input('chunk_total', false);
        $chunkIndex = Request::input('chunk_index', false);
        $resourceTempBaseName = Request::input('resource_temp_basename', false);
        $resourceExt = Request::input('resource_ext', false);
        $chunk = Request::file('resource_chunk', false);
        $groupSubDir = Request::input('group_subdir', false);
        $resourceHash = Request::input('resource_hash', false);
        $group = Request::input('group', false);
        $product = Request::input('product', false);
        $identify = Request::input('identify', false);
        $savedPathKey = MyUtil::getSavedPathKey($group, $resourceHash);
        $partialResource = null;

        $result = [
            'error'     => 0,
            'savedPath' => '',
        ];

        try {

            if ($chunkTotalCount === false || $chunkIndex === false || $resourceExt === false || $resourceTempBaseName === false || $groupSubDir === false || $chunk === false || $resourceHash === false || $group === false) {
                throw new \Exception(trans('aetherupload::messages.invalid_chunk_params'));
            }

            ConfigMapper::instance()->applyGroupConfig($group);

            $partialResource = new MyPartialResource($resourceTempBaseName, $resourceExt, $groupSubDir);

            // do a check to prevent security intrusions
            if ($partialResource->exists() === false) {
                throw new \Exception(trans('aetherupload::messages.invalid_operation'));
            }

            // determine if this upload meets the condition of instant completion
            if ($resourceHash !== false && ConfigMapper::get('instant_completion') === true && RedisSavedPath::exists($savedPathKey) === true) {
                $partialResource->delete();
                unset($partialResource->chunkIndex);
                $result['savedPath'] = RedisSavedPath::get($savedPathKey);

                return Responser::returnResult($result);
            }

            if ($chunk->getError() > 0) {
                throw new \Exception(trans('aetherupload::messages.upload_error'));
            }

            if ($chunk->isValid() === false) {
                throw new \Exception(trans('aetherupload::messages.http_post_only'));
            }

            // validate the data in header file to avoid the errors when network issue occurs
            if ((int)($partialResource->chunkIndex) !== (int)$chunkIndex - 1) {
                return Responser::returnResult($result);
            }

            $partialResource->append($chunk->getRealPath());

            $partialResource->chunkIndex = $chunkIndex;

            // determine if the resource file is completed
            if ($chunkIndex === $chunkTotalCount) {

                $partialResource->checkSize();

                $partialResource->checkMimeType();

                // trigger the event before an upload completes
                if (empty($beforeUploadCompleteEvent = ConfigMapper::get('event_before_upload_complete')) === false) {
                    event(new $beforeUploadCompleteEvent($partialResource));
                }

                $sHash = $partialResource->calculateHash();
                $aIdentify = explode('-',$identify);
                $resourceHash = strtolower($aIdentify[0]) . '_' . $aIdentify[1];

                $partialResource->rename($completeName = MyUtil::getFileName($resourceHash, $resourceExt));

                $resource = new MyResource($group, $groupSubDir, $completeName);

                $savedPath = $resource->getSavedPath();
                $aData = [
                    'time' => date('Y-m-d H:i:s'),
                    'md5'  => $sHash,
                    'url'  => config('params.plist_url') . $resourceHash . '.' . 'plist',
                ];
                $aCacheData = Cache::get($this->sCacheKey);
                $aCacheData[$product][$identify] = $aData;
                Cache::forever($this->sCacheKey, $aCacheData);
                if (ConfigMapper::get('instant_completion') === true) {
                    RedisSavedPath::set($savedPathKey, $savedPath);
                }

                unset($partialResource->chunkIndex);

                // trigger the event when an upload completes
                if (empty($uploadCompleteEvent = ConfigMapper::get('event_upload_complete')) === false) {
                    event(new $uploadCompleteEvent($resource));
                }

                $result['savedPath'] = $savedPath;

            }

            return Responser::returnResult($result);

        } catch (\Exception $e) {

            $partialResource->delete();
            unset($partialResource->chunkIndex);

            return Responser::reportError($result, $e->getMessage());
        }

    }

    /**
     * Handle the request of option method in CORS
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function options()
    {
        return response('');
    }
    public function delete(HttpRequest $request){
        if (!$request->ajax()){
            return json_encode(['status' => 0 ,'msg' => '非法请求']);
        }
        $model = new Upload();
        $identify = $request->post('identify','');
        if (!$identify) return json_encode(['status' => 0 ,'msg' => 'identify 不能为空']);
        if (!$model->deleteIdentify($identify)) {
            return json_encode(['status' => 0 ,'msg' => $model->_errors]);
        }
        return json_encode(['status' => 1 ,'msg' => 'success']);
    }
    public function download(HttpRequest $request){
        if (!$request->ajax()){
            return json_encode(['status' => 0 ,'msg' => '非法请求']);
        }
        $model = new Upload();
        $identify = $request->post('identify','');
        if (!$identify) return json_encode(['status' => 0 ,'msg' => 'identify 不能为空']);
        if (!$sReturnUrl = $model->getDownLoadUrl($identify)) {
            return json_encode(['status' => 0 ,'msg' => '获取url失败']);
        }
        return json_encode(['status' => 1 ,'msg' => 'success','url' => $sReturnUrl]);
    }
}
