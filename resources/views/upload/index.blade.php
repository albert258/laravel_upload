@extends('layouts.default')
@section('content-header')
    <h1>
        PKK
        <small>上传</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{url('/')}}"><i class="fa fa-dashboard"></i>首页</a></li>
        <li class="active">上传</li>
    </ol>
@endsection
@section('content')
    <div class="box-header with-border">
        <ul id="myTab" class="nav nav-tabs">
            @foreach($aDatas as $key => $aData)
                <li class="{{$key == 'PKW' ? 'active':''}}"><a href="#{{$key}}" data-toggle="tab">{{$key}}</a></li>
            @endforeach
            <li><a href="#up" data-toggle="tab">UPLOAD</a></li>
        </ul>
        <div id="myTabContent" class="tab-content" style="padding-top: 60px">
            @foreach($aDatas as $key => $aData)
                <div class="tab-pane fade {{$key == 'PKW' ? ' in active':''}}" id="{{$key}}">
                    <table class="table table-hover">
                        <thead class="thead-dark">
                        <tr>
                            <th scope="col">产品</th>
                            <th scope="col">时间</th>
                            <th scope="col">MD5</th>
                            <th scope="col">url</th>
                            <th scope="col">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($aData as $dkey => $value)
                            <tr>
                                <th scope="row">{{$dkey}}</th>
                                <td>{{$value['time']}}</td>
                                <td>{{$value['md5']}}</td>
                                <td>{{$value['url']}}</td>
                                <td>
                                    <button class="btn btn-primary download-identify"
                                            data-download-identify="{{$dkey}}">下载
                                    </button>
                                    <button class="btn btn-danger delete-identify" data-delete-identify="{{$dkey}}">删除
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            <div class="tab-pane fade" id="up">
                <form action="/" enctype="multipart/form-data" method="post">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="product">请选择产品</label>
                        <select class="form-control product" name="product" id="product">
                            @foreach(config('params.product') as $pkey => $pvalue)
                                <option value="{{$pkey}}">{{$pvalue}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="identify">请选择包</label>
                        <select class="form-control identify" name="identify" id="identify">
                        </select>
                    </div>
                    <div class="form-group " id="aetherupload-wrapper"><!--组件最外部需要一个名为aetherupload-wrapper的id，用以包装组件-->
                        <div class="controls">
                            <input type="file" id="aetherupload-resource"
                                   onchange="aetherupload(this).setPrduct().setGroup('app').setSavedPathField('#aetherupload-savedpath').setPreprocessRoute('/upload/preprocess').setUploadingRoute('/upload/uploading').success(someCallback).upload()"/>
                            <!--需要一个名为aetherupload-resource的id，用以标识上传的文件，setGroup(...)设置分组名，setSavedPathField(...)设置资源存储路径的保存节点，setPreprocessRoute(...)设置预处理路由，setUploadingRoute(...)设置上传分块路由，success(...)可用于声名上传成功后的回调方法名。默认为选择文件后触发上传，也可根据需求手动更改为特定事件触发，如点击提交表单时-->
                            <div class="progress "
                                 style="height: 6px;margin-bottom: 2px;margin-top: 10px;width: 200px;">
                                <div id="aetherupload-progressbar" style="background:blue;height:6px;width:0;"></div>
                                <!--需要一个名为aetherupload-progressbar的id，用以标识进度条-->
                            </div>
                            <span style="font-size:16px;color:red;" id="aetherupload-output"></span>
                            <!--需要一个名为aetherupload-output的id，用以标识提示信息-->
                            <input type="hidden" name="file" id="aetherupload-savedpath">
                            <!--需要一个自定义名称的id，以及一个自定义名称的name值, 用以标识资源储存路径自动填充位置，默认id为aetherupload-savedpath，可根据setSavedPathField(...)设置为其它任意值-->
                        </div>
                    </div>
                </form>
                <hr/>
                <div id="result"></div>
            </div>
        </div>
    </div>
    <script src="{{ asset('vendor/aetherupload/js/spark-md5.min.js') }}"></script>
    <script src="{{ asset('js/bootstrap.js') }}"></script>
    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="{{ asset('vendor/aetherupload/js/aetherupload.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('.product').change(function () {
                var data = ['A', 'B', 'C', 'D', 'E'];
                var pro = $('.product').val();
                var options = '';
                for (i in data) {
                    if (pro === '') {
                        options += "<option value=" + '' + ">" + '请选择包' + "</option>";
                    } else {
                        identify = pro + "-" + data[i];
                        options += "<option value=" + identify + ">" + identify + "</option>";
                    }
                }
                $(".identify").html(options);
            });
        });
        $('.delete-identify').click(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            var identify = $(this).data('delete-identify');
            $.ajax({
                url: "/upload/delete",
                data: {identify: identify},
                type: 'POST',
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                cache: false,
                async: false,
                success: function (data) {
                    alert(data.msg);
                    window.location.reload();
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    if (XMLHttpRequest.status === 0) {
                        $('#aetherupload-output').text('网络错误，失败');
                        return false;
                    } else {
                        $('#aetherupload-output').text('网络错误，失败2');
                        return false;
                    }
                }
            })
        });
        $('.download-identify').click(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            var identify = $(this).data('download-identify');
            $.ajax({
                url: "/upload/download",
                data: {identify: identify},
                type: 'POST',
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                cache: false,
                async: false,
                success: function (data) {
                    console.log(data);
                    if (data.status === 1) {
                        window.location.href = data.url
                        return false;
                    }
                    alert(data.msg);
                    window.location.reload();
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    if (XMLHttpRequest.status === 0) {
                        $('#aetherupload-output').text('网络错误，失败');
                        return false;
                    } else {
                        $('#aetherupload-output').text('网络错误，失败2');
                        return false;
                    }
                }
            })
        });
        // success(someCallback)中声名的回调方法需在此定义，参数someCallback可为任意名称，此方法将会在上传完成后被调用
        // 可使用this对象获得resourceName,resourceSize,resourceTempBaseName,resourceExt,groupSubdir,group,savedPath等属性的值
        someCallback = function () {
            // Example
            $('#result').append(
                '<p>执行回调 - 文件已上传，原名：<span >' + this.resourceName + '</span> | 大小：<span >' + parseFloat(this.resourceSize / (1000 * 1000)).toFixed(2) + 'MB' + '</span> | 储存名：<span >' + this.savedPath.substr(this.savedPath.lastIndexOf('/') + 1) + '</span></p>'
            );
        }

    </script>
@endsection