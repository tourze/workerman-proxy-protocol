<?php

namespace Tourze\Workerman\ProxyProtocol\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\ProxyProtocol\Enum\Version;
use Tourze\ProxyProtocol\Model\V1Header;
use Tourze\Workerman\ProxyProtocol\HeaderMap;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV1;
use Workerman\Connection\ConnectionInterface;

class ProxyProtocolV1Test extends TestCase
{
    /**
     * @var ConnectionInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        // 清理之前的测试可能留下的记录
        if (HeaderMap::has($this->connection)) {
            HeaderMap::remove($this->connection);
        }
    }

    public function testInputWithValidHeader(): void
    {
        $validHeader = "PROXY TCP4 192.168.1.1 192.168.1.2 12345 80\r\n";
        $result = ProxyProtocolV1::input($validHeader, $this->connection);

        // 应该返回整个头部的长度
        $this->assertEquals(strlen($validHeader), $result);
    }

    public function testInputWithInvalidHeader(): void
    {
        // 设置 connection 的期望行为
        $this->connection->expects($this->never())
            ->method('close');

        // 测试不完整的数据
        $incompleteHeader = "PROXY TCP4 192.168.1.1";
        $result = ProxyProtocolV1::input($incompleteHeader, $this->connection);

        // 数据不完整，应该返回0表示需要更多数据
        $this->assertEquals(0, $result);
    }

    public function testInputWithTooLongInvalidData(): void
    {
        // 设置 connection 的期望行为
        $this->connection->expects($this->once())
            ->method('close');

        // 测试过长但不符合规则的数据
        $tooLongInvalidData = str_repeat('X', 109);
        $result = ProxyProtocolV1::input($tooLongInvalidData, $this->connection);

        // 数据太长但不合法，应该关闭连接并返回0
        $this->assertEquals(0, $result);
    }

    public function testDecodeValidHeader(): void
    {
        // 设置 connection 的期望行为
        $this->connection->expects($this->never())
            ->method('close');

        $validHeader = "PROXY TCP4 192.168.1.1 192.168.1.2 12345 80\r\n";
        $result = ProxyProtocolV1::decode($validHeader, $this->connection);

        // 应该正确解析头部信息并返回空字符串
        $this->assertEquals('', $result);

        // 验证是否正确存储了头部信息
        $this->assertTrue(HeaderMap::has($this->connection));
        $header = HeaderMap::get($this->connection);
        $this->assertInstanceOf(V1Header::class, $header);
        $this->assertEquals(Version::V1, $header->getVersion());
        $this->assertEquals('TCP4', $header->getProtocol());
        $this->assertEquals('192.168.1.1', $header->getSourceIp());
        $this->assertEquals(12345, $header->getSourcePort());
    }

    public function testDecodeWithInvalidHeader(): void
    {
        // 设置 connection 的期望行为
        $this->connection->expects($this->once())
            ->method('close');

        // 无效的头部数据
        $invalidHeader = "PROXY INVALID DATA\r\n";
        $result = ProxyProtocolV1::decode($invalidHeader, $this->connection);

        // 无效数据应该关闭连接并返回空字符串
        $this->assertEquals('', $result);
        $this->assertFalse(HeaderMap::has($this->connection));
    }

    public function testDecodeWithUnknownProtocol(): void
    {
        // 设置 connection 的期望行为
        $this->connection->expects($this->once())
            ->method('close');

        // UNKNOWN协议数据
        $unknownHeader = "PROXY UNKNOWN\r\n";
        $result = ProxyProtocolV1::decode($unknownHeader, $this->connection);

        // UNKNOWN协议应该关闭连接并返回空字符串
        $this->assertEquals('', $result);
        $this->assertFalse(HeaderMap::has($this->connection));
    }

    public function testDecodeAfterHeaderParsed(): void
    {
        // 先设置一个已解析的头部
        $header = new V1Header();
        HeaderMap::set($this->connection, $header);

        // 测试解析后收到的数据
        $data = "HTTP/1.1 200 OK\r\n";
        $result = ProxyProtocolV1::decode($data, $this->connection);

        // 应该原样返回数据
        $this->assertEquals($data, $result);
    }

    public function testEncode(): void
    {
        $data = "HTTP/1.1 200 OK\r\n";
        $result = ProxyProtocolV1::encode($data, $this->connection);

        // 应该原样返回数据
        $this->assertEquals($data, $result);
    }
}
