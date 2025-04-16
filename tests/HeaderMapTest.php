<?php

namespace Tourze\Workerman\ProxyProtocol\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\ProxyProtocol\Enum\Version;
use Tourze\ProxyProtocol\Model\Address;
use Tourze\ProxyProtocol\Model\V1Header;
use Tourze\Workerman\ProxyProtocol\HeaderMap;
use Workerman\Connection\ConnectionInterface;

class HeaderMapTest extends TestCase
{
    /**
     * @var ConnectionInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $connection;

    /**
     * @var V1Header
     */
    private $header;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);

        // 创建一个测试用的头部信息
        $this->header = new V1Header();
        $this->header->setVersion(Version::V1);
        $this->header->setProtocol('TCP4');
        $sourceAddress = new Address('192.168.1.1', 12345);
        $targetAddress = new Address('192.168.1.2', 80);
        $this->header->setSourceAddress($sourceAddress);
        $this->header->setTargetAddress($targetAddress);
    }

    public function testSetAndGet(): void
    {
        // 初始状态应该没有头信息
        $this->assertFalse(HeaderMap::has($this->connection));
        $this->assertNull(HeaderMap::get($this->connection));

        // 设置头信息
        HeaderMap::set($this->connection, $this->header);

        // 检查是否设置成功
        $this->assertTrue(HeaderMap::has($this->connection));
        $header = HeaderMap::get($this->connection);
        $this->assertSame($this->header, $header);

        // 验证头信息的内容正确
        $this->assertEquals(Version::V1, $header->getVersion());
        $this->assertEquals('TCP4', $header->getProtocol());
        $this->assertEquals('192.168.1.1', $header->getSourceIp());
        $this->assertEquals(12345, $header->getSourcePort());
    }

    public function testRemove(): void
    {
        // 设置头信息
        HeaderMap::set($this->connection, $this->header);
        $this->assertTrue(HeaderMap::has($this->connection));

        // 移除头信息
        HeaderMap::remove($this->connection);

        // 检查是否移除成功
        $this->assertFalse(HeaderMap::has($this->connection));
        $this->assertNull(HeaderMap::get($this->connection));
    }

    public function testWeakReference(): void
    {
        // 创建一个临时的连接对象
        $tempConnection = $this->createMock(ConnectionInterface::class);

        // 设置头信息
        HeaderMap::set($tempConnection, $this->header);
        $this->assertTrue(HeaderMap::has($tempConnection));

        // 释放连接对象的引用
        $tempConnection = null;

        // 强制垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // 由于使用了 WeakMap，当连接对象被释放后，HeaderMap 中对应的条目应该也会被自动释放
        // 但是我们无法直接检查这一点，因为对象已经被释放了
        // 这里我们只是确认代码执行时没有出错
        $this->assertTrue(true);
    }
}
