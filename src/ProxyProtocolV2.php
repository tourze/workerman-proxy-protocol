<?php

namespace Tourze\Workerman\ProxyProtocol;

use Tourze\ProxyProtocol\Model\V2Header;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\ProtocolInterface;

/**
 * 代理协议V2版本的协议实现
 *
 * @see https://www.haproxy.org/download/1.8/doc/proxy-protocol.txt
 */
class ProxyProtocolV2 implements ProtocolInterface
{
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        $length = strlen($buffer);
        if (HeaderMap::has($connection)) {
            return $length;
        }

        // 按照协议定义，起码有16字节：固定头部12 + 协议版本和命令1 + 传输协议和地址系列1 + 网络字节序排列的地址长度2
        if ($length < 16) {
            return 0;
        }
        // 二进制标头格式以一个恒定的12字节块开始
        if (!str_starts_with($buffer, V2Header::SIG_DATA)) {
            // 不符合要求，直接退出
            $connection->close();

            return 0;
        }
        // 第 15 和第 16 个字节是按网络字节序排列的地址长度（以字节为单位）。
        // 协议头的长度（以字节为单位）始终正好是 16 + 此值
        $unpackResult = unpack('n', substr($buffer, 12 + 1 + 1, 2));
        if (false === $unpackResult) {
            $connection->close();

            return 0;
        }
        $destLen = $unpackResult[1];

        // 总的协议头长度可以计算出来了
        return 12 + 1 + 1 + 2 + $destLen;
    }

    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        if (!HeaderMap::has($connection)) {
            // 头包的处理
            $result = V2Header::parseHeader($buffer);
            if (null === $result['header']) {
                $connection->close();

                return '';
            }
            HeaderMap::set($connection, $result['header']);

            return '';
        }

        return $buffer;
    }

    public static function encode(mixed $data, ConnectionInterface $connection): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        // For objects or arrays, attempt string conversion
        if (is_object($data) && method_exists($data, '__toString')) {
            return (string) $data;
        }

        // Fallback: JSON encode for complex types
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        return false !== $encoded ? $encoded : '';
    }
}
