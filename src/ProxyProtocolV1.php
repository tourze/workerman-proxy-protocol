<?php

namespace Tourze\Workerman\ProxyProtocol;

use Tourze\ProxyProtocol\Enum\Version;
use Tourze\ProxyProtocol\Model\Address;
use Tourze\ProxyProtocol\Model\V1Header;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\ProtocolInterface;

/**
 * 使用 \r\n 分割，协议内容是 PROXY TCP4 192.168.0.1 192.168.0.11 56324 443\r\n
 *
 * @see https://www.haproxy.org/download/1.8/doc/proxy-protocol.txt
 */
class ProxyProtocolV1 implements ProtocolInterface
{
    public const RULE = '@PROXY (TCP4|TCP6|UNKNOWN)(.*?)\r\n@';
    public const HEADER = 'PROXY';

    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        $length = strlen($buffer);
        // 已经解析过了，就有多少读多少
        if (HeaderMap::has($connection)) {
            return $length;
        }

        // 未解析过，开始判断是否有协议头咯
        if (str_starts_with($buffer, self::HEADER) && 1 === preg_match(self::RULE, $buffer, $match)) {
            // 第一个包只处理协议头部分
            return strlen($match[0]);
        }

        // 如果已经很长，但是依然没找到，说明数据不对
        // The maximum line lengths the receiver must support including the CRLF are :
        //  - TCP/IPv4 :
        //      "PROXY TCP4 255.255.255.255 255.255.255.255 65535 65535\r\n"
        //    => 5 + 1 + 4 + 1 + 15 + 1 + 15 + 1 + 5 + 1 + 5 + 2 = 56 chars
        //
        //  - TCP/IPv6 :
        //      "PROXY TCP6 ffff:f...f:ffff ffff:f...f:ffff 65535 65535\r\n"
        //    => 5 + 1 + 4 + 1 + 39 + 1 + 39 + 1 + 5 + 1 + 5 + 2 = 104 chars
        //
        //  - unknown connection (short form) :
        //      "PROXY UNKNOWN\r\n"
        //    => 5 + 1 + 7 + 2 = 15 chars
        //
        //  - worst case (optional fields set to 0xff) :
        //      "PROXY UNKNOWN ffff:f...f:ffff ffff:f...f:ffff 65535 65535\r\n"
        //    => 5 + 1 + 7 + 1 + 39 + 1 + 39 + 1 + 5 + 1 + 5 + 2 = 107 chars
        // So a 108-byte buffer is always enough to store all the line and a trailing zero for string processing.
        if ($length > 108) {
            $connection->close();

            return 0;
        }

        return 0;
    }

    public static function decode(string $buffer, ConnectionInterface $connection): string
    {
        if (!HeaderMap::has($connection)) {
            // 处理协议头
            if (!str_starts_with($buffer, self::HEADER)) {
                $connection->close();

                return '';
            }

            if (1 !== preg_match(self::RULE, $buffer, $match)) {
                $connection->close();

                return '';
            }

            if ('UNKNOWN' === $match[1]) {
                // UNKNOWN我们直接不处理，断开算了
                $connection->close();

                return '';
            }

            // 匹配其他信息
            if (1 !== preg_match('@ (.*?) (.*?) (\d+) (\d+)@', $match[2], $infoMatch)) {
                // 匹配不到目标和端口信息
                $connection->close();

                return '';
            }

            $header = new V1Header();
            $header->setVersion(Version::V1);
            $header->setProtocol($match[1]);

            // 创建源地址和目标地址
            $sourceAddress = new Address($infoMatch[1], (int) $infoMatch[3]);
            $targetAddress = new Address($infoMatch[2], (int) $infoMatch[4]);

            $header->setSourceAddress($sourceAddress);
            $header->setTargetAddress($targetAddress);
            HeaderMap::set($connection, $header);

            // 抛弃协议头
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
