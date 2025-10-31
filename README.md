# Workerman Proxy Protocol

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-proxy-protocol.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-proxy-protocol)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-proxy-protocol.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-proxy-protocol)

A Proxy Protocol implementation for Workerman framework, supporting both V1 and V2 versions.

## Features

- Support for Proxy Protocol V1 and V2 versions
- Compatible with Workerman framework protocol interface
- Automatic parsing of proxy protocol headers
- Uses WeakMap to manage connection-to-header mapping, preventing memory leaks
- Strict compliance with HAProxy Proxy Protocol specification
- Support for TCP4, TCP6, and UNKNOWN connection types

## Installation

```bash
composer require tourze/workerman-proxy-protocol
```

## Requirements

- PHP >= 8.1
- Workerman >= 5.1
- tourze/proxy-protocol-core

## Quick Start

### Using Proxy Protocol V1

```php
<?php

use Workerman\Worker;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV1;
use Tourze\Workerman\ProxyProtocol\HeaderMap;

// Create Worker instance
$worker = new Worker('tcp://0.0.0.0:8080');

// Use Proxy Protocol V1
$worker->protocol = ProxyProtocolV1::class;

// Handle incoming messages with proxy protocol information
$worker->onMessage = function($connection, $data) {
    // Check if proxy protocol header was parsed
    if (HeaderMap::has($connection)) {
        $header = HeaderMap::get($connection);
        
        // Get client's original IP and port
        $sourceIp = $header->getSourceIp();
        $sourcePort = $header->getSourcePort();
        
        echo "Received data from {$sourceIp}:{$sourcePort}: " . $data . "\n";
        
        // Respond to client
        $connection->send("Your IP is {$sourceIp}, port is {$sourcePort}\n");
    } else {
        echo "Received data without proxy protocol header: " . $data . "\n";
        $connection->send("Could not get your real IP\n");
    }
};

// Run worker
Worker::runAll();
```

### Using Proxy Protocol V2

```php
<?php

use Workerman\Worker;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV2;
use Tourze\Workerman\ProxyProtocol\HeaderMap;

// Create Worker instance
$worker = new Worker('tcp://0.0.0.0:8080');

// Use Proxy Protocol V2
$worker->protocol = ProxyProtocolV2::class;

// Message handling logic is the same as V1
$worker->onMessage = function($connection, $data) {
    if (HeaderMap::has($connection)) {
        $header = HeaderMap::get($connection);
        // Handle message...
    }
};

Worker::runAll();
```

## Using with Reverse Proxies

When using servers like HAProxy or Nginx that support proxy protocol as frontend proxies, you need to enable proxy protocol support in the proxy configuration:

### HAProxy Configuration Example

```
frontend http
    bind *:80
    mode tcp
    option tcplog
    option tcp-check
    option forwardfor
    # Enable proxy protocol
    use_backend workerman send-proxy

backend workerman
    mode tcp
    server workerman1 127.0.0.1:8080 check
```

### Nginx Configuration Example

```
upstream workerman {
    server 127.0.0.1:8080;
}

server {
    listen 80;
    
    location / {
        # Enable proxy protocol
        proxy_pass http://workerman;
        proxy_protocol on;
    }
}
```

## API Documentation

### HeaderMap Class

Static methods for managing connection-to-header mapping:

- `HeaderMap::get(ConnectionInterface $connection): ?HeaderInterface` - Get proxy protocol header for connection
- `HeaderMap::set(ConnectionInterface $connection, HeaderInterface $header): void` - Set proxy protocol header for connection
- `HeaderMap::has(ConnectionInterface $connection): bool` - Check if connection has proxy protocol header
- `HeaderMap::remove(ConnectionInterface $connection): void` - Remove proxy protocol header for connection

### Header Interface

Proxy protocol header objects implement `HeaderInterface` interface, providing these methods:

- `getSourceIp(): string` - Get client source IP
- `getSourcePort(): int` - Get client source port
- `getTargetIp(): string` - Get target server IP
- `getTargetPort(): int` - Get target server port
- `getProtocol(): string` - Get protocol type (TCP4, TCP6)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## References

- [Proxy Protocol Specification](https://www.haproxy.org/download/1.8/doc/proxy-protocol.txt)
- [Workerman Documentation](https://www.workerman.net/doc)
