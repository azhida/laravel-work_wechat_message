<?php

namespace Azhida\LaravelWorkWechatMessage;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(WorkWechatMessage::class, function(){
            return new WorkWechatMessage();
        });

        $this->app->alias(WorkWechatMessage::class, 'workWechatMessage');
    }

    public function provides()
    {
        return [WorkWechatMessage::class, 'workWechatMessage'];
    }
}