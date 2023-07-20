<?php

/**
 * @Author:Hmily
 * @Date:2023-07-20
 * @Function:qq音乐解析
 * @Github:https://github.com/ihmily
 **/


header('Access-Control-Allow-Origin:*');
header('content-type: application/json;');

$msg = $_GET['msg'];//需要搜索的歌名
$n = $_GET['n'];//选择(序号)
$type = empty($_GET['type']) ? 'song': $_GET['type'];
$page_limit = empty($_GET['page']) ? 1 : $_GET['page'];//页数(默认第一页)
$count_limit = empty($_GET['count']) ? 20 : $_GET['count'];//列表数量(默认20个)



switch ($type) {
    case 'song':
        if(empty($msg)){
            exit(json_encode(array('code'=>200,'text'=>'请输入要解析的歌名'),448));
        }
        get_qq_song($msg,$page_limit,$count_limit,$n);
        break;
    case 'songid':
        if(!empty($_GET['id'])){
            $json_data=get_mp3_data($_GET['id']);
            $song_url = $json_data["songList"][0]["url"];
            exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌曲解析','now'=>date("Y-m-d H:i:s"),'song_url'=>$song_url),448));
        }
        exit(json_encode(array('code'=>200,'text'=>'解析失败，请检查歌曲id值是否正确','type'=>'歌曲解析'),448));
        break;
    default:
        exit(json_encode(array('code'=>200,'text'=>'请求参数不存在'.$type),448));
}





function get_qq_song($msg,$page_limit,$count_limit,$n){
    
    // 歌曲搜索接口
    $post_data = '{"comm":{"_channelid":"19","_os_version":"6.2.9200-2","authst":"Q_H_L_5tvGesDV1E9ywCVIuapBeYL7IYKKtbZErLj5HeBkyXeqXtjfQYhP5tg","ct":"19","cv":"1873","guid":"B69D8BC956E47C2B65440380380B7E9A","patch":"118","psrf_access_token_expiresAt":1697829214,"psrf_qqaccess_token":"A865B8CA3016A74B1616F8919F667B0B","psrf_qqopenid":"2AEA845D18EF4BCE287B8EFEDEA1EBCA","psrf_qqunionid":"6EFC814008FAA695ADD95392D7D5ADD2","tmeAppID":"qqmusic","tmeLoginType":2,"uin":"961532186","wid":"0"},"music.search.SearchCgiService":{"method":"DoSearchForQQMusicDesktop","module":"music.search.SearchCgiService","param":{"grp":1,"num_per_page":'.$count_limit.',"page_num":'.$page_limit.',"query":"'.$msg.'","remoteplace":"txt.newclient.history","search_type":0,"searchid":"6254988708H54D2F969E5D1C81472A98609002"}}}';
    $headers = array(
        "Content-Type: application/json; charset=UTF-8",
        "Charset: UTF-8",
        "Accept: */*",
        "User-Agent: Mozilla/5.0 (Linux; Android 6.0.1; OPPO R9s Plus Build/MMB29M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/55.0.2883.91 Mobile Safari/537.36",
        "Host: u.y.qq.com"
    );
    $post_url="https://u.y.qq.com/cgi-bin/musicu.fcg";
    // $jsonp_str=get_curl($url,array('User-Agent:'.$user_agent));
    // $json_str = preg_replace('/^\w+\((.*)\)$/', '$1', $jsonp_str);
    $json_str=post_curl($post_url,$post_data,$headers);
    // var_dump($json_str);
    // exit();
    $json_data = json_decode($json_str,true);
    
    $info_list=$json_data["music.search.SearchCgiService"]["data"]["body"]["song"]["list"];
    $data_list=array();
    if ($n !=''){
        $info = $info_list[$n];
        
        // 获取歌曲mp3链接
        $song_mid = $info["mid"];
        
        if($song_mid !=""){
            $json_data2 = get_mp3_data($song_mid);
            $song_url = $json_data2["songList"][0]["url"];
        }
        $song_name=$info["name"];
        if($song_url==""){
            $song_url=null;
            $song_name =$song_name."[付费歌曲]";
        }
        $data_list=[
            "name" => $song_name,
            "singername" => $info["singer"][0]["name"],
            "duration" => null,
            "file_size" => null, // 将字节转换为MB并保留两位小数,
            "song_url" => $song_url,
            "mv_url" => null,
            "album_img" => "https://y.qq.com/music/photo_new/T002R300x300M000".$info["album"]["pmid"].".jpg",
        ];
        
        
    }else{
        // 未选择第几个歌曲，显示歌曲列表
        foreach ($info_list as $info ){
        $data=[
            "name" => $info["name"],
            "singername" => $info["singer"][0]["name"],
            "duration" => null,
            "mid" => $info["mid"],
            "vid" => $info['mv']['vid']==""?null:$info['mv']['vid'],
            "time_public"=>$info['time_public'],
            "mvhash" => null
        ];
        array_push($data_list, $data);
        }
    }
    exit(json_encode(array('code'=>200,'text'=>'解析成功','type'=>'歌曲解析','now'=>date("Y-m-d H:i:s"),'data'=>$data_list),448));
}


// 获取歌曲数据
function get_mp3_data($song_mid){

    $url="https://i.y.qq.com/v8/playsong.html?ADTAG=ryqq.songDetail&songmid=".$song_mid."&songid=0&songtype=0";
    $html_str=get_curl($url);
    preg_match('/>window.__ssrFirstPageData__ =(.*?)<\/script/',$html_str,$json_str);
    // echo($json_str[1]);
    $json_data=json_decode($json_str[1], true);
    return $json_data;
}


// 获取MV视频数据
// function get_mv_data($mv_vid){
//     // 获取MV视频
//     $url = 'https://y.qq.com/n/ryqq/mv/'.$mv_vid;
//     $json_str=get_curl($url);
//     $json_data = json_decode($json_str,true);
//     return $json_data;
// }


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

function post_curl($post_url,$post_data,$headers,$cookies='') {

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
    
