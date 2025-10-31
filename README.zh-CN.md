# Workerman Proxy Protocol

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-proxy-protocol.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-proxy-protocol)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-proxy-protocol.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-proxy-protocol)

这是一个用于 Workerman 框架的代理协议（Proxy Protocol）实现，支持 V1 和 V2 版本。

## 功能特性

- 支持代理协议 V1 和 V2 版本
- 兼容 Workerman 框架的协议接口
- 自动解析代理协议头部信息
- 使用 WeakMap 管理连接与协议头的映射，避免内存泄漏
- 严格遵循 HAProxy 代理协议规范
- 支持 TCP4、TCP6 和 UNKNOWN 连接类型

## 安装

```bash
composer require tourze/workerman-proxy-protocol
```

## 系统要求

- PHP >= 8.1
- Workerman >= 5.1
- tourze/proxy-protocol-core

## 快速开始

### 使用代理协议 V1

```php
<?php

use Workerman\Worker;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV1;
use Tourze\Workerman\ProxyProtocol\HeaderMap;

// 创建 Worker 实例
$worker = new Worker('tcp://0.0.0.0:8080');

// 使用代理协议 V1
$worker->protocol = ProxyProtocolV1::class;

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

### 使用代理协议 V2

```php
<?php

use Workerman\Worker;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV2;
use Tourze\Workerman\ProxyProtocol\HeaderMap;

// 创建 Worker 实例
$worker = new Worker('tcp://0.0.0.0:8080');

// 使用代理协议 V2
$worker->protocol = ProxyProtocolV2::class;

// 消息处理逻辑与 V1 相同
$worker->onMessage = function($connection, $data) {
    if (HeaderMap::has($connection)) {
        $header = HeaderMap::get($connection);
        // 处理消息...
    }
};

Worker::runAll();
```

## 配合反向代理使用

在使用 HAProxy、Nginx 等支持代理协议的服务器作为前端代理时，需要在代理配置中启用代理协议支持：

### HAProxy 配置示例

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

### Nginx 配置示例

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

## API 文档

### HeaderMap 类

静态方法用于管理连接与代理协议头的映射：

- `HeaderMap::get(ConnectionInterface $connection): ?HeaderInterface` - 获取连接的代理协议头信息
- `HeaderMap::set(ConnectionInterface $connection, HeaderInterface $header): void` - 设置连接的代理协议头信息
- `HeaderMap::has(ConnectionInterface $connection): bool` - 检查连接是否有代理协议头信息
- `HeaderMap::remove(ConnectionInterface $connection): void` - 移除连接的代理协议头信息

### 协议头接口

代理协议头对象实现 `HeaderInterface` 接口，提供以下方法：

- `getSourceIp(): string` - 获取客户端源 IP
- `getSourcePort(): int` - 获取客户端源端口
- `getTargetIp(): string` - 获取目标服务器 IP
- `getTargetPort(): int` - 获取目标服务器端口
- `getProtocol(): string` - 获取协议类型（TCP4、TCP6）

## 许可证

MIT License. 请参阅 [LICENSE](LICENSE) 文件了解更多信息。

## 参考文档

- [Proxy Protocol 规范](https://www.haproxy.org/download/1.8/doc/proxy-protocol.txt)
- [Workerman 文档](https://www.workerman.net/doc)
