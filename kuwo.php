<?php

/**
 * @Author:Hmily
 * @Date:2023-06-23
 * @Function:酷我音乐解析
 * @Github:https://github.com/ihmily
 **/


header('Access-Control-Allow-Origin:*');
header('content-type: application/json;');
$headers = array(
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:106.0) Gecko/20100101 Firefox/106.0',
    
    'Accept: application/json, text/plain, */*',
    'Referer: https://kuwo.cn',
    'Secret: 10373b58aee58943f95eaf17d38bc9cf50fbbef8e4bf4ec6401a3ae3ef8154560507f032',
);

$cookies='Hm_lvt_cdb524f42f0ce19b169a8071123a4797=1687520303,1689840209; _ga=GA1.2.2021483490.1666455184; _ga_ETPBRPM9ML=GS1.2.1689840210.4.1.1689840304.60.0.0; Hm_Iuvt_cdb524f42f0ce19b169b8072123a4727=NkA4TadJGeBWwmP2mNGpYRrM8f62K8Cm; Hm_lpvt_cdb524f42f0ce19b169a8071123a4797=1689840223; _gid=GA1.2.1606176174.1689840209; _gat=1';

$msg = $_GET['msg'];//需要搜索的歌名
$n = $_GET['n'];//选择(序号)
$type = empty($_GET['type']) ? 'song': $_GET['type'];
$page_limit = empty($_GET['page']) ? 1 : $_GET['page'];//页数(默认第一页)
$count_limit = empty($_GET['count']) ? 20 : $_GET['count'];//列表数量(默认10个)

switch ($type) {
    case '':
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请输入要解析的歌曲或者MV名称'),448));
        break;
    case 'mv':
        $data_list=get_kuwo_mv($msg,$page_limit,$count_limit,$n,$headers,$cookies);
        exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'MV解析','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
        break;
    case 'song':
        get_kuwo_song($msg,$page_limit,$count_limit,$n,$headers,$cookies);
        break;
    case 'rid':
        if(!empty($_GET['id'])){
            $song_data=get_mp3_data($_GET['id'],$headers,$cookies);
            exit(json_encode($song_data,448));
        }
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请检查歌曲rid值是否正确','type'=>'歌曲解析'),448));
        break;
    case 'mid':
        if(!empty($_GET['id'])){
            $mv_data=get_mv_data($_GET['id'],$headers,$cookies);
            if(!empty(($mv_data))){
                exit(json_encode($mv_data,448));
            }
        }
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请检查MV mid值是否正确','type'=>'MV解析'),448));
        break;
    default:
        exit(json_encode(array('code'=>200,'text'=>'请求参数不存在'),448));
}




function get_kuwo_song($msg,$page_limit,$count_limit,$n,$headers,$cookies){

    // 歌曲搜索接口
    $url="http://kuwo.cn/api/www/search/searchMusicBykeyWord?key=".urlencode($msg)."&pn=".$page_limit."&rn=".$count_limit."&httpsStatus=1";
    
    $json_str=get_curl($url,$headers,$cookies);

    $json_data = json_decode($json_str,true);

    $info_list=$json_data['data']['list'];
    $data_list=array();
    if ($n !=''){
        $info = $info_list[$n];
        // 获取歌曲mp3链接
        $song_rid = $info['rid'];
        
        if($song_rid !=""){
            $json_data2 = get_mp3_data($song_rid,$headers,$cookies);
            $song_url = empty($json_data2['data'])?"付费歌曲暂时无法获取歌曲下载链接":$json_data2['data']['url'];
        }
        
        $data_list=[
            "name" => $info['name'],
            "singername" => $info['artist'],
            "duration" => gmdate("i:s", $info['duration']),
            "file_size" => null, // 将字节转换为MB并保留两位小数,
            "song_url" => $song_url,
            "mv_url" => get_mv_data($song_rid,$headers,$cookies)['data']['url'],
            "album_img" => $info['pic'],
        ];
        
        
    }else{
        // 未选择第几个歌曲，显示歌曲列表
        foreach ($info_list as $info ){
        $data=[
            "name" => $info['name'],
            "singername" => $info['artist'],
            "duration" => gmdate("i:s", $info['duration']),
            "rid" => $info['rid']
        ];
        array_push($data_list, $data);
        }

    }
    exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌曲解析','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
}



function get_kuwo_mv($msg,$page_limit,$count_limit,$n,$headers,$cookies){

    // 歌曲搜索接口
    $url="http://www.kuwo.cn/api/www/search/searchMvBykeyWord?key=".urlencode($msg)."&pn=".$page_limit."&rn=".$count_limit."&httpsStatus=1";
    
    $json_str=get_curl($url,$headers,$cookies);
    // exit($json_str);
    $json_data = json_decode($json_str,true);
    
    $info_list=$json_data['data']['mvlist'];
    $data_list=array();
    if ($n !=''){
        $info = $info_list[$n];
        $json_data2 = get_mv_data($info['id'],$headers,$cookies);
        $mv_url = $json_data2['data']['url'];
        
        $data_list=[
            "name" => $info['name'],
            "singername" => $info['artist'],
            "duration" => gmdate("i:s", $info['duration']),
            "file_size" => null, // 将字节转换为MB并保留两位小数,
            "mv_url" => $mv_url,
            "cover_url" => $info['pic'],
            "publish_date" => null
        ];
        
        
    }else{
        // 未选择第几个歌曲，显示歌曲列表
        foreach ($info_list as $info ){
        $data=[
            "name" => $info['name'],
            "singername" => $info['artist'],
            "duration" => gmdate("i:s", $info['duration']),
            "cover_url" => $info['pic'],
        ];
        array_push($data_list, $data);
        }
    }
    return $data_list;
}



// 获取歌曲数据
function get_mp3_data($song_rid,$headers,$cookies){
    $url = 'http://kuwo.cn/api/v1/www/music/playUrl?mid='.$song_rid.'&type=music&httpsStatus=1';
    $json_str=get_curl($url,$headers,$cookies);
    // exit($json_str2);
    $json_data = json_decode($json_str,true);
    return $json_data;
}

// 获取MV视频数据
function get_mv_data($mv_mid,$headers,$cookies){
    // 获取MV视频
    $url = 'http://www.kuwo.cn/api/v1/www/music/playUrl?mid='.$mv_mid.'&type=mv&httpsStatus=1';
    $json_str=get_curl($url,$headers,$cookies);
    $json_data = json_decode($json_str,true);
    return $json_data;
}



function get_response_headers($url) {
    // 设置 CURL 请求的 URL 和一些选项
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    // 发送请求并获取响应和响应头信息
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    // 打印响应头信息
    // echo $header;
    // 关闭 CURL 请求
    curl_close($ch);
    return $header;
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
