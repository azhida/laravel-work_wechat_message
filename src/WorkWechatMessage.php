<?php

namespace Azhida\LaravelWorkWechatMessage;

use Azhida\LaravelWorkWechatMessage\Exceptions\DecryptMessageException;
use Azhida\LaravelWorkWechatMessage\Exceptions\Exception;
use Azhida\LaravelWorkWechatMessage\Exceptions\InvalidArgumentException;
use Azhida\LaravelWorkWechatMessage\Exceptions\PullChatDataException;
use Illuminate\Support\Facades\Storage;
use Azhida\Tools\Tool;

class WorkWechatMessage
{
    /**
     * 企业微信会话内容存档服务 【说明：目前仅支持 linux】
     */

    private $config = [];
    private $private_key = ''; // 私匙
    private $media_to_cloud = false; // 媒体文件是否上传云端
    private $sdk = null; // sdk 实例

    private $num = 0; // 计数器，从0开始
    private $is_end = false; // 是否拉取完毕
    private $max_seq = 0; // 已拉取的最大seq

    public function __construct()
    {
        $this->initSdk();
    }

    /**
     * 初始化 SDK
     * @throws \Exception
     */
    private function initSdk()
    {
        // 判断是否存在 类 \WxworkFinanceSdk
        if (class_exists('\WxworkFinanceSdk')) {

            // 开始检查各项参数配置
            $config = config('wechat.work.msg_save');
            if (empty($config)) {
                $config = config('services.work_wechat_message.msg_save');
            }
            $this->config = $config;
            $corp_id = $config['corp_id'] ?? '';
            $secret = $config['secret'] ?? '';
            $private_key_file_path = $config['private_key_file_path'] ?? '';
            if (!$corp_id) throw new InvalidArgumentException('WxworkFinanceSdk 初始化失败：参数 corp_id 缺失');
            if (!$secret) throw new InvalidArgumentException('WxworkFinanceSdk 初始化失败：参数 secret 缺失');
            if (!$private_key_file_path) throw new InvalidArgumentException('WxworkFinanceSdk 初始化失败：参数 private_key_file_path 缺失');

            if (!is_file($private_key_file_path)) {
                throw new InvalidArgumentException("WxworkFinanceSdk 初始化失败： 文件 {$private_key_file_path} 不存在");
            }
            $this->private_key = file_get_contents($this->config['private_key_file_path']);
            if (!$this->private_key) throw new InvalidArgumentException("WxworkFinanceSdk 初始化失败：文件 {$private_key_file_path} 内容为空");

            $this->media_to_cloud = $config['media_to_cloud'] ?? false;

            $proxy_host = $config['proxy_host'] ?? '';
            $proxy_password = $config['proxy_password'] ?? '';
            $options = [ // 可选参数
                'proxy_host' => $proxy_host,
                'proxy_password' => $proxy_password,
                'timeout' => 10, // 默认超时时间为10s
            ];
            try {
                $this->sdk = new \WxworkFinanceSdk($corp_id, $secret, $options);
            } catch (\Exception $exception) {
                throw new Exception('WxworkFinanceSdk 初始化失败：' . $exception->getMessage(), $exception->getCode(), $exception);
            }

        } else {
            throw new Exception('WxworkFinanceSdk 初始化失败：WxworkFinanceSdk 扩展未安装');
        }
    }

    // 该方法是 预留的重写入口，主要作用：
    // 当本地服务器循环请求企业微信服务器循环拉取数据时，可以通过该方法，处理每一次从微信服务器拉下来的数据
    protected function handleOnePullLog(array $chats, int $min_seq, int $max_seq, int $count)
    {
        Tool::loggerCustom(__CLASS__, __FUNCTION__, '预留的重写入口', [
            '$min_seq' => $min_seq,
            '$max_seq' => $max_seq,
            '$count' => $count
        ]);
//        TestWxWorkChatMessagePullLog::query()->create([
//            'min_seq' => $min_seq,
//            'max_seq' => $max_seq,
//            'count' => $count,
//            'res' => $chats,
//        ]);

        // 批量解密
//        $this->decryptMessageBatch($chats['chatdata']);
    }

    /**
     * 该方法是 预留的重写入口，可以直接在该方法中做业务逻辑的处理
     * @param array $item 单条包含已解密的会话数据
     */
    protected function handleOneMessage(array $item = [])
    {
        Tool::loggerCustom(__CLASS__, __FUNCTION__, '预留的重写入口', [
            'msgid' => $item['msgid']
        ]);
//        TestWxWorkChatMessage::query()->create([
//            'seq' => $item['seq'], // 消息的seq值，标识消息的序号
//            'msgid' => $item['msgid'], // 消息id，消息的唯一标识，企业可以使用此字段进行消息去重。
//            'msg' => $item['msg'], // 明文消息，json格式
//            'data' => $item, // 加密数据，json格式，即chatdata里的每一项数据
//        ]);
    }

    /**
     * 拉取聊天数据 -- 批量
     * 考虑到聊天内容数据量很大，该方法不做最终的数据返回，
     * 逻辑的处理，应该在 handleOnePullLog() 和 handleOneMessage() 两个方法中处理
     * 每一次的
     * @param int $start_seq 开始拉取的消息序号
     * @param int $limit 每次拉取的条数，最大 1000
     */
    public function getChatDataBatch(int $start_seq = 0, int $limit = 1000)
    {
        $start_time = time();
        $echo = '数据已全部拉取';
        while (true) {
            try {
                $this->getChatData($start_seq, $limit);
                if ($this->is_end) break;
                $start_seq = $this->max_seq;
            } catch (\Exception $exception) {
                $echo = $exception->getMessage();
                break;
            }
            $end_time = time();
            $used_time = $end_time - $start_time;
            $log_content = [
                '$num' => $this->num,
                '$start_seq' => $start_seq,
                '$start_time' => date('Y-m-d H:i:s', $start_time),
                '$end_time' => date('Y-m-d H:i:s', $end_time),
                '$used_time' => $used_time,
            ];
            echo Tool::loggerCustom(__CLASS__, __FUNCTION__, '批量拉取数据--执行中', $log_content, true);
        }

        $end_time = time();
        $used_time = $end_time - $start_time;
        $log_content = [
            '$num' => $this->num,
            '$start_seq' => $start_seq,
            '$start_time' => date('Y-m-d H:i:s', $start_time),
            '$end_time' => date('Y-m-d H:i:s', $end_time),
            '$used_time' => $used_time,
            '$echo' => $echo,
        ];
        echo Tool::loggerCustom(__CLASS__, __FUNCTION__, '批量拉取数据--结束', $log_content, true);
    }

    /**
     * 拉取聊天数据 -- 单次
     * @param int $seq
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function getChatData(int $seq = 0, int $limit = 1000): array
    {
        try {
            $chats = $this->sdk->getChatData($seq, $limit);
            $chats = json_decode($chats, true);
            Tool::loggerCustom(__CLASS__, __FUNCTION__, '单次拉取数据', $chats);

            $count = count($chats['chatdata']);
            if ($count == 0) throw new PullChatDataException('数据已全部拉取');
            if ($count < $limit) $this->is_end = true;

            $seqs = array_column($chats['chatdata'], 'seq');
            $min_seq = min($seqs);
            $this->max_seq = $max_seq = max($seqs);

            // 单次拉取的处理
            $this->handleOnePullLog($chats, $min_seq, $max_seq, $count);

            // 批量解密
//            $this->decryptMessageBatch($chats['chatdata']);

            return $chats['chatdata'];

        } catch (\Exception $exception) {
            throw new \Exception('数据拉取失败：' . $exception->getMessage());
        }
    }

    // 解密消息 -- 批量
    public function decryptMessageBatch(array $chatdata)
    {
        $start_time = time();
        foreach ($chatdata as $key => &$val) {

            $end_time = time();
            $used_time = $end_time - $start_time;
            $log_content = [
                '$num' => $this->num,
                '$key' => $key,
                '$start_time' => date('Y-m-d H:i:s', $start_time),
                '$used_time' => $used_time,
                '$seq' => $val['seq'],
                '$msgid' => $val['msgid'],
            ];
            echo Tool::loggerCustom(__CLASS__, __FUNCTION__, '解密会话内容', $log_content, true);
            $this->num++;

            if ($key == 0) {
                $min_seq = $max_seq = $val['seq'];
            } else {
                if ($val['seq'] < $min_seq) $min_seq = $val['seq'];
                if ($val['seq'] > $max_seq) $max_seq = $val['seq'];
            }

            $msg = $this->decryptMessage($val); // 解密消息
            Tool::loggerCustom(__CLASS__, __FUNCTION__, '解密会话内容 - 1', $msg);

            $val['msg'] = $msg;

            // 单条聊天内容的处理
            $this->handleOneMessage($val);
        }
    }

    // 解密消息 -- 单条
    public function decryptMessage(array $chatdata_item)
    {
        try {
            $decryptRandKey = null;
            $privateKey = $this->private_key;
            openssl_private_decrypt(base64_decode($chatdata_item['encrypt_random_key']), $decryptRandKey, $privateKey, OPENSSL_PKCS1_PADDING);
            $msg = $this->sdk->decryptData($decryptRandKey, $chatdata_item['encrypt_chat_msg']); // 解密

            $msg = $this->downloadMedia($msg); // 下载媒体文件
            Tool::loggerCustom(__CLASS__, __FUNCTION__, '解密会话内容 - 2', $msg);

            return json_decode($msg, true);
        } catch (\Exception $exception) {
            throw new DecryptMessageException('数据解密失败：' . $exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    // 下载媒体文件 todo 待优化完善
    protected function downloadMedia($msg)
    {
        $msgtype = $msg['msgtype'] ?? '';
        if (!$msgtype) return $msg;
        $sdkFileId = $msg[$msgtype]['sdkfileid'] ?? '';
        if (!$sdkFileId) return $msg;

        if ($msgtype == 'image') { // 图片 jpg
            $file_name = "{$msg['msgid']}.jpg";
        }
        else if ($msgtype == 'voice') { // 语音 amr
            $file_name = "{$msg['msgid']}.amr";
        }
//        else if ($msgtype == 'video') { // 视频 mp4  emotion
//            $file_name = "{$msg['msgid']}.mp4";
//        }
        else if ($msgtype == 'emotion') { // 表情 要看 $msg['emotion']['type'] , 表情类型，png或者gif.1表示gif 2表示png。Uint32类型
            if ($msg['emotion']['type'] == 1) {
                $file_name = "{$msg['msgid']}.gif";
            } else if ($msg['emotion']['type'] == 2) {
                $file_name = "{$msg['msgid']}.png";
            } else {
                $file_name = "{$msg['msgid']}";
            }
        }
        else if ($msgtype == 'file') { // 文件
            $file_name = $msg['file']['filename'];
        }
        else {
            return $msg;
        }

        $file_path = Storage::disk('public')->path("work_wechat_messages/{$msgtype}");
        if (!is_dir($file_path)) mkdir($file_path, 0777, true);
        $file_path = $file_path . ("/{$file_name}");

        $file_url = $file_url_local = Storage::disk('public')->url("work_wechat_messages/{$msgtype}/{$file_name}");

        $this->sdk->downloadMedia($sdkFileId, $file_path);

        if ($this->media_to_cloud) { // 媒体文件是否上传云端
            try {
                $res = Storage::putFileAs("work_wechat_messages/{$msgtype}", $file_path, $file_name);
                $file_url = Storage::url($res);
            } catch (\Exception $exception) {
                $storage_disk_name = config('filesystems.default');
                Tool::loggerCustom(__CLASS__, __FUNCTION__, "上传 {$storage_disk_name} 云盘失败，异常信息：{$exception->getMessage()}", []);
            }
        }
        $log_content = [
            '$file_path' => $file_path,
            '$file_url' => $file_url,
            '$file_url_local' => $file_url_local
        ];
        Tool::loggerCustom(__CLASS__, __FUNCTION__, '文件存储路径：', $log_content);

        $msg[$msgtype]['file_url'] = $file_url;
        $msg[$msgtype]['file_url_local'] = $file_url_local;

        return $msg;
    }
}