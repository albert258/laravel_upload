<?php


namespace App\Components\Upload;


use AetherUpload\ConfigMapper;
use Illuminate\Support\Facades\Storage;

class MyResource
{
    public $disk;
    public $name;
    public $group;
    public $groupDir;
    public $groupSubDir;
    public $path;
    public $realPath;

    public function __construct($group, $groupSubDir, $name)
    {
        $this->disk = Storage::disk('local');
        $this->name = $name;
        $this->group = $group;
        $this->groupDir = ConfigMapper::get('group_dir');
        $this->groupSubDir = $groupSubDir;
        $this->path = $this->getPath();
        $this->realPath = $this->getRealPath();
    }

    public function getPath()
    {
        return ConfigMapper::get('root_dir') . DIRECTORY_SEPARATOR . $this->groupDir . DIRECTORY_SEPARATOR . $this->groupSubDir . DIRECTORY_SEPARATOR . $this->name;
    }

    public function getRealPath()
    {
        return storage_path('app/') . $this->path;
    }

    public function getSavedPath()
    {
        return '/' . $this->name;
    }

    public function __call($name, $arguments)
    {
        if ( method_exists($this->disk, $name) ) {
            return call_user_func_array([$this->disk, $name], $arguments);
        }

        return null;
    }

}