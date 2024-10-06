<?php
// 应用公共文件
function Swap(&$a, &$b) {
    $tmp = $a; $a = $b; $b = $tmp;
}
function Dget($dct, $key, $defaultVal=null) {
    return isset($dct[$key]) ? $dct[$key] : $defaultVal;
}
function validate_item_range($sort, $allow_list) {
    // 不知道ThinkPHP的 order([$sort => $order])对$sort是否有注入，干脆手动过滤一下$sort
    if(!in_array($sort, $allow_list))
        return $allow_list[0];
    return $sort;
}
function ParseMarkdown($str, $highlight=false, $toc=0, $title=null)
{
    $retHtml = '';
    try {
        $Pandoc = new Pandoc();
        $text_type = "markdown";
        // 基于首行 “__LATEX__” 作为 LaTex 标识
        $firstLine = strtok(ltrim($str), "\n");
        if ($firstLine !== false && trim($firstLine) === '__LATEX__') {
            $text_type = "latex";
            $str = substr($str, strlen($firstLine) + 1); // Remove the first line
        }
        $retHtml = $Pandoc->convert($str, $text_type, "html", $highlight, $toc, $title);
    } catch (Exception $e) {
        $Parsedown = new \ParsedownExtra();
        $retHtml = $Parsedown->text($str);
    }
    return $retHtml;
}
//获取客户端真实IP
function GetRealIp()
{
    $unknown = 'unknown';
    if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown) ) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif ( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    /*
    处理多层代理的情况
    或者使用正则方式：$ip = preg_match("/[\d\.]{7,15}/", $ip, $matches) ? $matches[0] : $unknown;
    */
    if (false !== strpos($ip, ','))
    {
        $ip_explode = explode(',', $ip);
        $ip = reset($ip_explode);
    }
    return $ip;
}

//Carousel设置
function SetCarousel()
{
    //首页滚动大图设置
    //结构为：
    //        [
    //            'href'=> [三个跳转链接],
    //            'src' => [三个图片链接]
    //        ]
    $carouselConfig = config('CsgcpcConst.CAROUSEL');
    $staticPage = config('CsgcpcConst.STATIC_PAGE');
    $News = db('news');
    $news = $News->where('news_id', $staticPage['carousel'])->find();
    if(!$news)
    {
        $news = [
            'news_id'    => $staticPage['carousel'],
            'title'        => 'Carousel',
            'content'    => '',
            'time'        => date('Y-m-d H:i:s'),
            'defunct'    => '1',
        ];
        $News->insert($news);
    }
    $carousel = json_decode($news['content'], true);
    $resetCarousel = false;
    if(!is_array($carousel))
        $resetCarousel = true;
    if(!$resetCarousel)
    {
        foreach($carouselConfig['carouselItem'] as $item)
        {
            if (!array_key_exists($item, $carousel))
            {
                $resetCarousel = true;
                break;
            }
            else
            {
                for($i = 0; $i < 3; $i ++)
                {
                    if(!array_key_exists($i, $carousel[$item]))
                        $resetCarousel = true;
                }
                if($resetCarousel)
                    break;
            }
        }
    }
    if($resetCarousel)
        $carousel = [
            'href' => ['', '', ''],
            'src' => $carouselConfig['srcDefault'],
            'header' => ['', '', ''],
            'content' => ['', '', '']
        ];
    for($i = 0; $i < 3; $i ++)
    {
        if(trim($carousel['src'][$i]) == '')
            $carousel['src'][$i] = $carouselConfig['srcDefault'][$i];
    }
    return ['news'=>$news, 'carousel'=>$carousel];
}
//管理员权限验证
function IsAdmin($item='administrator', $id=null)
{
    $item = trim($item);
    if($item == 'super_admin') {
        return session('?super_admin');
    }
    $OJ_MODE = config('OJ_ENV.OJ_MODE');
    $OJ_ADMIN = config('OjAdmin.' . ($OJ_MODE == 'both' ?  'cpcsys' : $OJ_MODE));
    $ojAdminList    = $OJ_ADMIN['OJ_ADMIN_LIST'];   // 'problem_editor' => 'Problem Editor'$
    $ojItemPri      = $OJ_ADMIN['OJ_ITEM_PRI'];     // 'problem' => 'pro_'
    $ojPreAdmin     = $OJ_ADMIN['OJ_PRE_ADMIN'];    // 'pro_' => 'problem_editor'
    $ojAllPrivilegeList = array_merge($ojAdminList, $OJ_ADMIN['OJ_PRIVILEGE']);

    $ret = false;
    if(array_key_exists($item, $ojAllPrivilegeList)) {
        // 如果 $item 存在于管理员列表中，直接验证
        if($ret = session('?' . $item))
            return $ret;
    }
    else if(array_key_exists($item, $ojItemPri)) {
        // 如果 $item 存在于管理项目名称中，如'problem'，则分有没有 id 来判断
        $adminPre = $ojItemPri[$item];
        $adminName = $ojPreAdmin[$adminPre];
        if($id != null) {
            // 有id，验证对特定项目的权限
            if(session('?administrator')) {
                return true;
            }
            // if($ret = session('?' . $ojItemPri[$item] . $id) && session('?'.$adminName))
            if($ret = session('?' . $ojItemPri[$item] . $id)) {
                return $ret;
            }
        }
        else {
            if($ret = session('?'.$adminName))
                return $ret;
        }
    }
    $ret = $ret || session('?administrator') || session('?super_admin');
    return $ret;
}

function LangMask($languages)
{
    if(!isset($languages) || count($languages) == 0)
        return -1; //('Please select at least 1 language.');
    $ojLang = config('CsgojConfig.OJ_LANGUAGE');
    $langMask = 0;
    foreach($languages as $la)
    {
        $la = intval($la);
        if(!array_key_exists($la, $ojLang))
            return -2; //error('Some languages are not allowed for this OJ.'
        $langMask |= 1 << $la;
    }
    return $langMask;
}
function Alphabet2Num($al)
{
    $ret = 0;
    $al = strtoupper($al);
    for($i = 0; $i < strlen($al); $i ++)
    {
        $ret = $ret * 26 + ord($al[$i]) - ord('A') + 1;
    }
    return $ret - 1;
}
function HttpGet($url, $data, $json=true) {
    $datainfo = [];
    foreach($data as $k=>$v) {
        $datainfo[] = $k . "=" . $v;
    }
    $url .= "?" . implode("&", $datainfo);
    $html = file_get_contents($url);
    if($json) return json_decode($html, true);
    return $html;
}
function KeyAdd($key, &$arr, $defaultVal=[]) {
    if(!is_array($arr)) return;
    if(!array_key_exists($key, $arr)) {
        $arr[$key] = $defaultVal;
    }
}
/************************************************************/
//密码加密
/************************************************************/
// 随机密码
function RandPass($len=8)
{
    // 去掉 0、O、I、1 容易混淆的字符，做随机字符串当密码。
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $ret = '';
    for($i = 0; $i < $len; $i ++)
        $ret .= $chars[rand() % 32];
    return $ret;
}

//生成加密密码
function MkPasswd($raw_passwd, $recoverable=False)
{
    if(!$recoverable) {
        $hash = password_hash($raw_passwd, PASSWORD_DEFAULT);
        return $hash;
    } else {
        // 可还原密码
        $secretKey = config('OJ_ENV.OJ_SECRET');
        $cipher = 'aes-128-gcm';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = '';
        $encrypted = openssl_encrypt(
            $raw_passwd, 
            $cipher, 
            $secretKey, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );
        return base64_encode($iv.'#@#'.$tag.'#@#'.$encrypted);
    }
}
//验证密码
function CkPasswd($password, $saved, $recoverable=False)
{
    if(!$recoverable) {
        if (IsMd5PW($saved)){
            $mpw = md5($password);
            if ($mpw==$saved) return True;
            else return False;
        }
        else if(IsB64PW($saved)) {
            $svd=base64_decode($saved);
            $salt=substr($svd,20);
            $hash = base64_encode( sha1(md5($password) . $salt, true) . $salt );
            return $hash == $saved;
        }
        return password_verify($password, $saved);
    }
    else {
        return RecoverPasswd($saved) == $password;
    }
}
// 弱密码场景下可还原密码的明文还原
function RecoverPasswd($saved) {
    try {
        $secretKey = config('OJ_ENV.OJ_SECRET');
        $cipher = 'aes-128-gcm';
        $passInfoList = explode('#@#', base64_decode($saved));
        $iv = $passInfoList[0];
        $tag = $passInfoList[1];
        return openssl_decrypt($passInfoList[2], $cipher, $secretKey, OPENSSL_RAW_DATA, $iv, $tag);
    } catch (Exception $e) {
        return $saved;
    }
}
//如果密码是旧的md5
function IsMd5PW($password) {
    for ($i=strlen($password)-1;$i>=0;$i--)
    {
        $c = $password[$i];
        if ('0'<=$c && $c<='9') continue;
        if ('a'<=$c && $c<='f') continue;
        if ('A'<=$c && $c<='F') continue;
        return False;
    }
    return True;
}
//如果密码是base64转码的版本
function IsB64PW($password) {
    return $password == base64_encode(base64_decode($password)) ? true : false;
}
// 一些需要部分加密的信息加星。
function StrAddStar($str)
{
    $len = strlen($str);
    $start = intval($len / 4);
    return substr_replace($str, str_repeat('*',$start * 2), $start, $start << 1);
}
//发送邮件
function SendMail($toAddr, $toUser, $subject, $content)
{
    $mailConfig = config('MailConfig');
    $mail = new \PHPMailer;

//    $mail->SMTPDebug = 3;                                   // Enable verbose debug output

    $mail->isSMTP();                                        // Set mailer to use SMTP
    $mail->Host = $mailConfig['smtp'];                         // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                                   // Enable SMTP authentication
    $mail->Username = $mailConfig['account'];                // SMTP username
    $mail->Password = $mailConfig['password'];                 // SMTP password
    $mail->SMTPSecure = $mailConfig['secure'];                // Enable TLS encryption, `ssl` also accepted
    $mail->Port = $mailConfig['port'];                        // TCP port to connect to

    $mail->setFrom($mailConfig['from'], $mailConfig['from_name']);
    $mail->addAddress($toAddr, $toUser);                    // Add a recipient
    $mail->isHTML(true);

    $mail->Subject = $subject;
    $mail->Body    = $content;
    $mail->AltBody = 'AltBody';

    if(!$mail->send())
    {
//        dump($mail->ErrorInfo);
        return false;
    }

    return true;
}
// **************************************************
// 文件相关
// 获取文件扩展名
function GetExtension($filename)
{
    $info = pathinfo($filename);
    return array_key_exists('extension', $info) ? $info['extension'] : '';
}
// 写文件
function WriteFile($filePath, $contents) {
    $handle = fopen($filePath, "w") or die("Unable to open file!");
    fwrite($handle, $contents);
    fclose($handle);
}
// 读文件
function LoadFile($filePath) {
    $handle = fopen($filePath, "r");
    $contents = fread($handle, filesize($filePath));
    fclose($handle);
    return $contents;
}
//递归建立文件夹
function MakeDirs($dir, $mode=0777)
{
    if (is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
    if (!MakeDirs(dirname($dir), $mode)) return FALSE;
    return @mkdir($dir, $mode);
}
function DelWhatever($filePath)
{
    //无论是文件、文件夹 还是其他什么，删除。
    if(is_file($filePath))
    {
        if(!unlink($filePath))
            return false;
        return "File";
    }
    else if(is_link($filePath))
    {
        if(!unlink($filePath))
            return false;
        return "Link";
    }
    else if(is_dir($filePath))
    {
        if(!DelDirs($filePath))
            return false;
        return "Folder";
    }
    return false;
}
//删除指定文件夹及其子目录
function DelDirs($dir)
{
    //给定的目录不是一个文件夹
    if(!is_dir($dir)){
        return null;
    }
    $fh = opendir($dir);
    while(($row = readdir($fh)) !== false){
        //过滤掉虚拟目录
        if($row == '.' || $row == '..'){
            continue;
        }

        if(!is_dir($dir.'/'.$row)){
            unlink($dir.'/'.$row);
        }
        DelDirs($dir.'/'.$row);
    }
    //关闭目录句柄，否则出Permission denied
    closedir($fh);
    //删除文件之后再删除自身
    if(!rmdir($dir)){
        return false;
    }
    return true;
}
//获取目标文件夹文件列表
function GetDir($dirpath, $filter=null)
{
    $filelist = [];
    if ($handle  = opendir($dirpath))
    {
        $i = 1;
        while (($file = readdir($handle)) !== false)
        {
            if ($file!="." && $file!="..")
            {
                if($filter !== null && !in_array(pathinfo($file, PATHINFO_EXTENSION), $filter)) continue;
                $filelist[] = [
                    'file_lastmodify' => date("Y-m-d h:i:s", filemtime($dirpath . '/' . $file)),
                    'file_name'       => $file,
                    'file_size'       => round(filesize($dirpath . '/' . $file) / 1024, 2),
                    'file_type'       => mime_content_type($dirpath . '/' . $file),
                ];
                $i ++;
            }
        }
        rsort($filelist);
        //关闭句柄
        closedir ( $handle );
    }
    return $filelist;
}
//强制文件下载
function GzipFile($filepath, $compress) {
    $ojPath = config('OjPath');
    $exportTempRoot = $ojPath['export_problem_temp'];
    $exportMakeFolder = $exportTempRoot . DIRECTORY_SEPARATOR . 'txt_download_compress';
    if(!MakeDirs($exportMakeFolder)) {
        die('Folder permission denied.');
    }
    $file_gzip_path = $exportMakeFolder . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, '-', ltrim($filepath, DIRECTORY_SEPARATOR)) . '.' . filemtime($filepath);
    if(file_exists($file_gzip_path)) {
        return $file_gzip_path;
    }
    $compress_handle = gzopen($file_gzip_path, 'w' . $compress);
    if (!$compress_handle) {
        die("Cannot open file compress");
    }
    $handle = fopen($filepath, "r");
    if (!$handle) {
        die("Cannot open file");
    }
    while (!feof($handle)) {
        $buffer = fread($handle, 2048);
        gzwrite($compress_handle, $buffer);
    }
    fclose($handle);
    gzclose($compress_handle);
    return $file_gzip_path;
}
function downloads($file_dir, $filename, $file_download_name=null, $compress=false) {
    if($file_download_name === null) {
        $file_download_name = $filename;
    }
    $filepath = $file_dir . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($filepath)) {
        die("File not found!");
    }
    else {
        $filesize = filesize($filepath);
        if ($compress !== false  && $filesize > 1024 && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
            // 虽然浏览器下载器显示下载完整文件，但下载速度其实是个伪速度，数据实际完成了压缩
            Header("Content-Encoding: gzip");
            $filepath = GzipFile($filepath, $compress);
        }
        header('Content-Description: File Transfer');
        Header("Content-type: application/octet-stream; charset=utf-8");
        Header("Accept-Ranges: bytes");
        Header("Accept-Length: ".filesize($filepath));
        header("Content-Length: ". filesize($filepath));
        Header("Content-Disposition: attachment; filename=".$file_download_name);
        readfile($filepath);

        // // 下述方法是在缓存区压缩，无法暂存已压缩数据优化重复下载
        // if ($compress !== false  && $filesize > 1024 && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
        //     ini_set('zlib.output_compression_level', $compress);
        //     ob_start("ob_gzhandler"); 
        // } else {
        //     ob_start();
        // }
        // header('Expires: 0');                           // 让历览器不缓存
        // header('Cache-Control: must-revalidate');       // 即使缓存，浏览器在使用缓存的内容之前必须先验证其有效性

        // header('Content-Description: File Transfer');
        // Header("Content-type: application/octet-stream; charset=utf-8");
        // Header("Accept-Ranges: bytes");
        // Header("Accept-Length: ".filesize($filepath));
        // header("Content-Length: ". filesize($filepath));
        // Header("Content-Disposition: attachment; filename=".$file_download_name);
        // readfile($filepath);
        // ob_end_flush();
    }
}

function DelTimeExpireFolders($dir, $expireTime)
{
    // 因为种种原因可能程序没执行完退出，导致临时文件夹有许多未删除旧文件
    // 此程序在每次生成临时文件夹的时候删除旧的未删除文件夹
    $now = time();
    if(is_dir($dir) && ($handle = opendir($dir)))
    {
        while (($file = readdir($handle)) !== false)
        {
            if ($file == "." || $file == "..")
                continue;
            $filetime = filemtime($dir . '/' . $file);
            if(($now - $filetime) / 60 / 60 / 24 > $expireTime)
            {
                if(is_file($dir . '/' . $file))
                    unlink($dir . '/' . $file);
                else
                    DelDirs($dir . '/' . $file);
            }
        }
        //关闭句柄
        closedir ( $handle );
    }
}
function RequestSend($url, $data, $header=['Content-type: application/x-www-form-urlencoded', 'X-Requested-With: XMLHttpRequest'], $post=1, $json=0) {
    if($json == 1) {
        $header[] = 'Content-Type: application/json';
    }
    if($post) {
        $options = [
            CURLOPT_URL             => $url,
            CURLOPT_POST            => 1,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => $header,
            CURLOPT_POSTFIELDS      => $json == 1 ? json_encode($data): http_build_query($data)
        ];
    } else {
        $options = [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => $header
        ];
    }
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    $ret = json_decode($result, true);
    if($ret == null) {
        $ret = [
            'status_code'   => 500,
            'detail'        => "数据请求失败"
        ];
    } else if(!array_key_exists('status_code', $ret)) {
        $ret['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
    curl_close($ch);
    return $ret;
}