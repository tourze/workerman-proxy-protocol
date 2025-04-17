<?php

namespace Tourze\Workerman\ProxyProtocol;

use Tourze\ProxyProtocol\Model\HeaderInterface;
use WeakMap;
use Workerman\Connection\ConnectionInterface;

/**
 * 代理协议头部管理类
 *
 * 使用 WeakMap 来管理连接对象与代理协议头之间的映射关系，
 * 避免在连接对象上直接添加属性
 */
class HeaderMap
{
    /**
     * 存储连接与头信息的映射
     *
     * @var WeakMap<ConnectionInterface, object>
     */
    private static WeakMap $map;

    /**
     * 获取单例实例
     *
     * @return WeakMap
     */
    private static function getInstance(): WeakMap
    {
        if (!isset(self::$map)) {
            self::$map = new WeakMap();
        }

        return self::$map;
    }

    /**
     * 获取连接的代理协议头信息
     *
     * @param ConnectionInterface $connection 连接对象
     * @return HeaderInterface|null 头信息，不存在时返回null
     */
    public static function get(ConnectionInterface $connection): ?HeaderInterface
    {
        return self::getInstance()[$connection] ?? null;
    }

    /**
     * 设置连接的代理协议头信息
     *
     * @param ConnectionInterface $connection 连接对象
     * @param HeaderInterface $header 头信息
     */
    public static function set(ConnectionInterface $connection, HeaderInterface $header): void
    {
        self::getInstance()[$connection] = $header;
    }

    /**
     * 检查连接是否有代理协议头信息
     *
     * @param ConnectionInterface $connection 连接对象
     * @return bool 是否存在头信息
     */
    public static function has(ConnectionInterface $connection): bool
    {
        return isset(self::getInstance()[$connection]);
    }

    /**
     * 移除连接的代理协议头信息
     *
     * @param ConnectionInterface $connection 连接对象
     */
    public static function remove(ConnectionInterface $connection): void
    {
        if (self::has($connection)) {
            unset(self::getInstance()[$connection]);
        }
    }
}
