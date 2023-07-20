<?php

/**
 * @Author:Hmily
 * @Date:2023-05-20
 * @Function:网易云音乐解析
 * @Github:https://github.com/ihmily
 **/

header('Access-Control-Allow-Origin:*');
header('content-type: application/json;');

$msg = $_GET['msg'];  //  需要搜索的歌名
$n = $_GET['n'];  // 你要获取下载链接的序号
$type = empty($_GET['type']) ? 'song': $_GET['type'];  // 解析类型
$count_limit = empty($_GET['count']) ? 10 : $_GET['count'];  //  列表数量(默认10个)
$page_limit = empty($_GET['page']) ? 1 : $_GET['page'];  //  页数(默认第一页)
$offset_limit = (($page_limit -1)*$count_limit);  // 偏移数
$id = $_GET['id'];  // 歌曲id或者歌单id

switch ($type) {
    case 'song':
        if(empty($msg)){
            exit(json_encode(array('code'=>200,'text'=>'请输入要解析的歌名'),448));
        }
        get_netease_song($msg,$offset_limit,$count_limit,$n);
        break;
    case 'songid':
        if(!empty($_GET['id'])){
            $song_url='http://music.163.com/song/media/outer/url?id='.$_GET['id'];  // 构造网易云歌曲下载链接
            $song_url = get_redirect_url($song_url);  // 歌曲直链
            exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌曲解析','now'=>date("Y-m-d H:i:s"),'song_url'=>$song_url),448));
        }
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请检查歌曲id值是否正确','type'=>'歌曲解析'),448));
        break;
    case 'random':
        $album_data=get_album_songs($_GET['id']);
        get_random_song($album_data);
        break;
    default:
        exit(json_encode(array('code'=>200,'text'=>'请求参数不存在'.$type),448));
}


function get_netease_song($msg,$offset_limit,$count_limit,$n){
    // 网易云歌曲列表接口
    $url = "https://s.music.163.com/search/get/?src=lofter&type=1&filterDj=false&limit=".$count_limit."&offset=".$offset_limit."&s=".urlencode($msg);
    $json_str=get_curl($url);
    $json_data = json_decode($json_str,true);
    $song_list = $json_data['result']['songs'];
    $data_list=array();
    if ($n !=''){
        $song_info = $song_list[$n];
        $song_url = 'http://music.163.com/song/media/outer/url?id='.$song_info['id'];  // 构造网易云歌曲下载链接
        $song_url = get_redirect_url($song_url);  // 歌曲直链
        $data_list=[
            "id" => $song_info['id'],
            "name" => $song_info['name'],
            "singername" => $song_info['artists'][0]['name'],
            "page" => $song_info['page'],
            "song_url" => $song_url  
        ];
    }else{
         // 未选择第几个歌曲，显示歌曲列表
        foreach ($song_list as $song ){
            $data=[
                "id" => $song['id'],
                "name" => $song['name'],
                "singername" => $song['artists'][0]['name']
            ];
            array_push($data_list, $data);
        }
    }
    exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌曲解析','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
}


// 随机输出歌单内的一首歌
function get_random_song($album_data){
    $json_str = $album_data;
    $json_data = json_decode($json_str,true);
    $playlist = $json_data['playlist'];
    $id = $playlist['id'];
    $name = $playlist['name'];
    $description = $playlist['description'];
    $trackIds = $playlist['trackIds'];
    $random_number = rand(0, count($trackIds)-1);
    $random_id = $trackIds[$random_number]['id'];
    $song_url = 'http://music.163.com/song/media/outer/url?id='.$random_id;  // 构造网易云歌曲下载链接
    $song_url = get_redirect_url($song_url);  // 歌曲直链
    $data_list = [
        "id" => $id,
        "album_name" => $name,
        "album_description" => $description,
        "song_id" => $random_id,
        "song_url" => $song_url
    ];
    exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌单随机歌曲','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
}


// 通过歌单id 解析歌单的方式
// ['热歌榜':'3778678','原创榜':2884035,'新歌榜':3779629,'飙升榜':19723756,'云音乐说唱榜':19723756] 
function get_album_songs($id){
    $id = empty($id)?3778678:$id;  // 如果歌单id为空，则解析热歌榜歌单的歌曲
    $post_data = http_build_query(array('s'=>'100','id' =>$id,'n'=>'100','t'=>'100'));
    $url = "http://music.163.com/api/v6/playlist/detail";
    $json_str = post_curl($url,$post_data);
    // exit($json_str);
    return $json_str;
}


//CURL函数
function get_curl($url,$headers=array(),$cookies=''){
        $default_headers=array("User-Agent:Mozilla/6.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36");
        $headers = empty($headers)?$default_headers:$headers;
        $curl=curl_init((string)$url);
        curl_setopt($curl,CURLOPT_HEADER,false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
        curl_setopt($curl, CURLOPT_COOKIE, $cookies);
        curl_setopt($curl,CURLOPT_TIMEOUT,20);
        $data = curl_exec($curl);
        // var_dump($data);
        curl_close($curl);
        return $data;
}


function post_curl($post_url,$post_data,$headers=array(),$cookies='') {
        $default_headers=array("User-Agent:Mozilla/6.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36");
        $headers = empty($headers)?$default_headers:$headers;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $post_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_COOKIE, $cookies);
        curl_setopt($curl, CURLOPT_NOBODY, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($curl,CURLOPT_TIMEOUT,20);
        $data = curl_exec($curl);
        // var_dump($data);
        curl_close($curl);
        return $data;
}

//  获取下载链接重定向链接
function get_redirect_url($url,$headers=array()) {
    $default_headers=array("User-Agent:Mozilla/6.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36");
    $headers = empty($headers)?$default_headers:$headers;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_NOBODY, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl,CURLOPT_TIMEOUT,20);
    $ret = curl_exec($curl);
    curl_close($curl);
    preg_match("/Location: (.*?)\r\n/iU",$ret,$location);
    return $location[1];
}

