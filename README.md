<h1 align="center"> laravel-work_wechat_message </h1>

<p align="center"> 企业微信消息 for Laravel.</p>

## Installing

```shell
$ composer require azhida/laravel-work_wechat_message -vvv
```

## 配置

两种方式，任选其一

- 1.在 `config/services.php` 添加参数

```php
<?php

return [

    // ...

    // 企业微信消息
    'work_wechat_message' => [
        // 会话内容存档配置信息
        'msg_save' => [
            'corp_id' => env('WECHAT_WORK_CORP_ID', ''), // 企业ID
            'secret'   => env('WECHAT_WORK_MSG_SAVE_SECRET', ''), // 

            // server config
            'token' => env('WECHAT_WORK_MSG_SAVE_TOKEN', ''), //
            'aes_key' => env('WECHAT_WORK_MSG_SAVE_AES_KEY', ''), //

            'private_key_file_path' => env('WECHAT_WORK_PRIVATE_KEY_FILE_PATH', ''), // work_msg_save_private_key 私匙文件地址，写 绝对路径
            'media_to_cloud' => env('WECHAT_WORK_MSG_SAVE_MEDIA_TO_CLOUD', false), // 媒体文件是否上传云端
        ]
        // ...
    ]

];
```

- 2.如果存在 `config/wechat.php` ，可以在 `config/wechat.php` 企业微信那个模块 添加参数【因为我安装了 `overtrue/laravel-wechat` 有了这个文件】

```php
<?php

return [
    // ...
  
    /*
     * 企业微信
     */
     'work' => [
         // ...

         // 会话内容存档配置信息
         'msg_save' => [
             'corp_id' => env('WECHAT_WORK_CORP_ID', ''), // 企业ID
             'secret'   => env('WECHAT_WORK_MSG_SAVE_SECRET', ''), // 会话内容存档 secret

             // server config
             'token' => env('WECHAT_WORK_MSG_SAVE_TOKEN', ''), // 
             'aes_key' => env('WECHAT_WORK_MSG_SAVE_AES_KEY', ''), //

             'private_key_file_path' => env('WECHAT_WORK_PRIVATE_KEY_FILE_PATH', ''), // work_msg_save_private_key 私匙文件地址，写 绝对路径
             'media_to_cloud' => env('WECHAT_WORK_MSG_SAVE_MEDIA_TO_CLOUD', false), // 媒体文件是否上传云端
         ]
     ],  
];
```

然后在 `.env` 添加所需配置项就好了

## Usage

- 创建一个服务类并继承 WorkWechatMessage 类

```
vim app/Services/WorkWeChatMessageSaveService.php
```

- WorkWechatMessage 类 内容示例

```
<?php

namespace App\Services;

use App\Models\TestWxWorkChatData;
use App\Models\TestWxWorkChatDataPullLog;
use Azhida\LaravelWorkWechatMessage\WorkWechatMessage;

class WorkWeChatMessageSaveService extends WorkWechatMessage
{
    /**
     * 企业微信会话内容存档服务 [说明：目前仅支持 linux]
     */

    public function __construct()
    {
        parent::__construct();
    }

    protected function handleOnePullLog(array $chats, int $min_seq, int $max_seq, int $count)
    {
        // 一次拉取，写一条拉取日志
        TestWxWorkChatDataPullLog::query()->create([
            'min_seq' => $min_seq,
            'max_seq' => $max_seq,
            'count' => $count,
            'res' => $chats,
        ]);
    }

    protected function handleOneMessage(array $item = [])
    {
        // 逐条解密消息并入库
        TestWxWorkChatData::query()->create([
            'seq' => $item['seq'], // 消息的seq值，标识消息的序号
            'msgid' => $item['msgid'], // 消息id，消息的唯一标识，企业可以使用此字段进行消息去重。
            'msg' => $item['msg'], // 明文消息，json格式
            'data' => $item, // 加密数据，json格式，即chatdata里的每一项数据
        ]);
    }

}

```

- 调用

单次拉取

```
        try {
            $workWeChatMessageSaveService = new \App\Services\WorkWeChatMessageSaveService();
            $res = $workWeChatMessageSaveService->getChatData(0, 100);
            dd($res);
        } catch (\Exception $exception) {
            dd($exception->getMessage());
        }
```

批量循环拉取

```
        try {
            $workWeChatMessageSaveService = new \App\Services\WorkWeChatMessageSaveService();
            $workWeChatMessageSaveService->getChatDataBatch(0, 100);
        } catch (\Exception $exception) {
            dd($exception->getMessage());
        }
```

## Problem

如果提示 `WxworkFinanceSdk 类 不存在` 或者 `WxworkFinanceSdk 扩展未安装`，则先去安装 `wxwork_finance_sdk`扩展，安装方法：https://gitee.com/wghzhida/php7-wxwork-finance-sdk

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/azhida/laravel-work_wechat_message/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/azhida/laravel-work_wechat_message/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT
