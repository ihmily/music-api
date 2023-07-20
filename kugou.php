<?php

/**
 * @Author:Hmily
 * @Date:2023-05-28
 * @Function:酷狗音乐解析
 * @Github:https://github.com/ihmily
 **/


header('Access-Control-Allow-Origin:*');
header('content-type: application/json;');

$msg = $_GET['msg'];//需要搜索的歌名
$n = $_GET['n'];//选择(序号)
$type = empty($_GET['type']) ? 'song': $_GET['type'];
$page_limit = empty($_GET['page']) ? 1 : $_GET['page'];//页数(默认第一页)
$count_limit = empty($_GET['count']) ? 10 : $_GET['count'];//列表数量(默认10个)

switch ($type) {
    case '':
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请输入要解析的歌名'),448));
        break;
    case 'mv':
        $data_list=get_kugou_mv($msg,$page_limit,$count_limit,$n);
        exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'MV解析','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
        break;
    case 'song':
        get_kugou_song($msg,$page_limit,$count_limit,$n);
        break;
    case 'shash':
        if(!empty($_GET['hash'])){
            $song_data=get_mp3_data($_GET['hash']);
            exit(json_encode($song_data,448));
        }
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请检查歌曲hash值是否正确','type'=>'歌曲解析'),448));
        break;
    case 'mhash':
        if(!empty($_GET['hash'])){
            $mv_data=get_mv_data($_GET['hash']);
            if(!empty(($mv_data))){
                exit(json_encode($mv_data,448));
            }
        }
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请检查MV hash值是否正确','type'=>'MV解析'),448));
        break;
    default:
        exit(json_encode(array('code'=>200,'text'=>'请求参数不存在'),448));
}


function get_kugou_mv($msg,$page_limit,$count_limit,$n){
    // MV搜索接口1
    // $url = 'http://mvsearch.kugou.com/mv_search?page='.$page_limit.'&pagesize='.$count_limit.'&userid=-1&clientver=&platform=WebFilter&tag=em&filter=10&iscorrection=1&privilege_filter=0&keyword='.urlencode($msg);
    // MV搜索接口2
    $url = "https://mobiles.kugou.com/api/v3/search/mv?format=json&keyword=".urlencode($msg)."&page=".$page_limit."&pagesize=".$count_limit."&showtype=1";
    // $jsonp_str=get_curl($url,array('User-Agent:'.$user_agent));
    // $json_str = preg_replace('/^\w+\((.*)\)$/', '$1', $jsonp_str);
    $json_str=get_curl($url);

    $json_data = json_decode($json_str,true);
    
    $info_list=$json_data['data']['info'];
    $data_list=array();
    if ($n !=''){
        $info = $info_list[$n];
        $json_data2 = get_mv_data($info['hash']);
        $mvdata_list = $json_data2['mvdata'];

    
        $mvdata = null;
        if (array_key_exists('sq', $mvdata_list)) {
            $mvdata = $mvdata_list['sq'];
        } else if (array_key_exists('le', $mvdata_list)) {
            $mvdata = $mvdata_list['le'];
        } else if (array_key_exists('rq', $mvdata_list)) {
            $mvdata = $mvdata_list['rq'];
        }
        
        $data_list=[
            "name" => $info['filename'],
            "singername" => $info['singername'],
            "duration" => gmdate("i:s", $info['duration']),
            "file_size" => round($mvdata['filesize'] / (1024 * 1024), 2).' MB', // 将字节转换为MB并保留两位小数,
            "play_count" => $json_data['play_count'],
            "like_count" => $json_data['like_count'],
            "comment_count" => $json_data['comment_count'],
            "collect_count" => $json_data['collect_count'],
            "mv_url" => $mvdata['downurl'],
            "cover_url" => str_replace('/{size}', '', $info['imgurl']),
            "publish_date" => $json_data['publish_date']
        ];
        
        
    }else{
        // 未选择第几个歌曲，显示歌曲列表
        foreach ($info_list as $info ){
        $data=[
            "name" => $info['filename'],
            "singername" => $info['singername'],
            "duration" => gmdate("i:s", $info['duration']),
            "cover_url" => str_replace('/{size}', '', $info['imgurl'])
        ];
        array_push($data_list, $data);
        }
    }
    return $data_list;
}


function get_kugou_song($msg,$page_limit,$count_limit,$n){
    
    // 歌曲搜索接口
    $url = "https://mobiles.kugou.com/api/v3/search/song?format=json&keyword=".urlencode($msg)."&page=".$page_limit."&pagesize=".$count_limit."&showtype=1";
    // $jsonp_str=get_curl($url,array('User-Agent:'.$user_agent));
    // $json_str = preg_replace('/^\w+\((.*)\)$/', '$1', $jsonp_str);
    $json_str=get_curl($url);
    $json_data = json_decode($json_str,true);
    
    $info_list=$json_data['data']['info'];
    $data_list=array();
    if ($n !=''){
        $info = $info_list[$n];
        
        // 获取歌曲mp3链接
        $song_hash = $info['hash'];
        
        if($song_hash !=""){
            $json_data2 = get_mp3_data($song_hash);
            $song_url = empty($json_data2['error'])?$json_data2['url']:"付费歌曲暂时无法获取歌曲下载链接";
        }
        
        $data_list=[
            "name" => $info['filename'],
            "singername" => $info['singername'],
            "duration" => gmdate("i:s", $info['duration']),
            "file_size" => round($json_data2['fileSize'] / (1024 * 1024), 2).' MB', // 将字节转换为MB并保留两位小数,
            "song_url" => $song_url,
            "mv_url" => get_kugou_mv($msg,$page_limit,$count_limit,$n)['mv_url'],
            "album_img" => str_replace('/{size}', '', $json_data2['album_img']),
        ];
        
        
    }else{
        // 未选择第几个歌曲，显示歌曲列表
        foreach ($info_list as $info ){
        $data=[
            "name" => $info['filename'],
            "singername" => $info['singername'],
            "duration" => gmdate("i:s", $info['duration']),
            "hash" => $info['hash'],
            "mvhash" => empty($info['mvhash'])?null:$info['mvhash']
        ];
        array_push($data_list, $data);
        }
    }
    exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌曲解析','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
}


// 获取歌曲数据
function get_mp3_data($song_hash){
    $url = 'https://m.kugou.com/app/i/getSongInfo.php?hash='.$song_hash.'&cmd=playInfo';
    $json_str=get_curl($url);
    // exit($json_str2);
    $json_data = json_decode($json_str,true);
    return $json_data;
}


// 获取MV视频数据
function get_mv_data($mv_hash){
    // 获取MV视频
    $url = 'http://m.kugou.com/app/i/mv.php?cmd=100&hash='.$mv_hash.'&ismp3=1&ext=mp4';
    $json_str=get_curl($url);
    $json_data = json_decode($json_str,true);
    return $json_data;
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
