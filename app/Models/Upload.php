<?php

namespace App\Models;

use AetherUpload\ConfigMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Upload extends Model
{
    public $_errors = [];
    public $_model = [
        'time' => "--",
        'md5'  => "--",
        'url'  => "--",
    ];
    public $_mapping = [
        'A' => 0,
        'B' => 1,
        'C' => 2,
        'D' => 3,
        'E' => 4,
    ];

    public function getData()
    {
        $aProducts = config('params.product');
        $sCacheKey = self::getCacheKey();
        if (!Cache::has($sCacheKey)) {
            $aCacheData = [];
            foreach ($aProducts as $sProduct) {
                if ($sProduct == '请选择产品') {
                    continue;
                }
                foreach ($this->_mapping as $key => $item) {
                    $aCacheData[$sProduct][$sProduct . '-' . $key] = $this->_model;
                }
            }
            Cache::forever($sCacheKey, $aCacheData);
            return $aCacheData;
        }
        return Cache::get($sCacheKey);
    }

    public static function getCacheKey()
    {
        return 'upload_ipa_key_all';
    }

    public function deleteIdentify($identify)
    {
        $aIdentify = explode('-', $identify);
        $sCacheKey = self::getCacheKey();
        $aCacheData = Cache::get($sCacheKey);
        $file = config('filesystems.disks.local.root') . DIRECTORY_SEPARATOR . ConfigMapper::get('root_dir') . DIRECTORY_SEPARATOR . config('aetherupload.groups.app.group_dir') . DIRECTORY_SEPARATOR . strtolower($aIdentify[0]) . DIRECTORY_SEPARATOR .$identify. DIRECTORY_SEPARATOR . strtolower($aIdentify[0]) . '_' . $aIdentify[1] . '.ipa';
        if (!is_file($file)) {
            $this->_errors[] = '文件不存在';
            return false;
        }
        if (!unlink($file)) {
            $this->_errors[] = '删除失败';
            return false;
        }
        $aCacheData[$aIdentify[0]][$identify] = $this->_model;
        Cache::forever($sCacheKey, $aCacheData);
        return true;
    }
    public function getDownLoadUrl($identify){
        $aIdentify = explode('-', $identify);
        $sCacheKey = self::getCacheKey();
        $aCacheData = Cache::get($sCacheKey);
        return $aCacheData[$aIdentify[0]][$identify]['url'] == '--' ? false:$aCacheData[$aIdentify[0]][$identify]['url'];
    }
}
