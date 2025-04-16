# Workerman Proxy Protocol

这是一个用于 Workerman 框架的代理协议（Proxy Protocol）实现，支持 V1 和 V2 版本。

## 功能特性

- 支持代理协议 V1 和 V2 版本
- 兼容 Workerman 框架的协议接口
- 自动解析代理协议头部信息
- 使用 WeakMap 管理连接与协议头的映射，避免内存泄漏

## 安装

```bash
composer require tourze/workerman-proxy-protocol
```

## 使用方法

### 在 Workerman 中使用

```php
<?php

use Workerman\Worker;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV1;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV2;
use Tourze\Workerman\ProxyProtocol\HeaderMap;

// 创建 Worker 实例
$worker = new Worker('tcp://0.0.0.0:8080');

// 使用代理协议 V1（或者使用 ProxyProtocolV2::class 支持 V2 版本）
$worker->protocol = ProxyProtocolV1::class;

// 在连接回调中处理连接
$worker->onConnect = function($connection) {
    echo "新连接建立\n";
};

// 在消息回调中可以访问代理协议信息
$worker->onMessage = function($connection, $data) {
    // 检查是否解析了代理协议头
    if (HeaderMap::has($connection)) {
        $header = HeaderMap::get($connection);
        
        // 获取客户端原始 IP 和端口信息
        $sourceIp = $header->getSourceIp();
        $sourcePort = $header->getSourcePort();
        
        echo "收到来自 {$sourceIp}:{$sourcePort} 的数据：" . $data . "\n";
        
        // 响应客户端
        $connection->send("你的 IP 是 {$sourceIp}，端口是 {$sourcePort}\n");
    } else {
        echo "收到数据，但未解析代理协议头：" . $data . "\n";
        $connection->send("未能获取你的真实 IP\n");
    }
};

// 运行 worker
Worker::runAll();
```

### 配合反向代理使用

在使用 HAProxy、Nginx 等支持代理协议的服务器作为前端代理时，需要在代理配置中启用代理协议支持：

#### HAProxy 配置示例

```
frontend http
    bind *:80
    mode tcp
    option tcplog
    option tcp-check
    option forwardfor
    # 启用代理协议
    use_backend workerman send-proxy

backend workerman
    mode tcp
    server workerman1 127.0.0.1:8080 check
```

#### Nginx 配置示例

```
upstream workerman {
    server 127.0.0.1:8080;
}

server {
    listen 80;
    
    location / {
        # 启用代理协议
        proxy_pass http://workerman;
        proxy_protocol on;
    }
}
```

## 依赖

- PHP >= 8.1
- Workerman >= 5.1
- tourze/proxy-protocol-core

## 参考资料

- [Proxy Protocol 规范](https://www.haproxy.org/download/1.8/doc/proxy-protocol.txt)
- [Workerman 文档](https://www.workerman.net/doc)
