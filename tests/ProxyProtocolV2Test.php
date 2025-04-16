<?php

namespace Tourze\Workerman\ProxyProtocol\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\ProxyProtocol\Model\V2Header;
use Tourze\Workerman\ProxyProtocol\HeaderMap;
use Tourze\Workerman\ProxyProtocol\ProxyProtocolV2;
use Workerman\Connection\ConnectionInterface;

class ProxyProtocolV2Test extends TestCase
{
    /**
     * @var ConnectionInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $connection;

    /**
     * @var string 模拟的V2协议头部数据
     */
    private $validHeaderData;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        // 清理之前的测试可能留下的记录
        if (HeaderMap::has($this->connection)) {
            HeaderMap::remove($this->connection);
        }

        // 创建一个模拟的V2协议头部数据
        // 由于V2是二进制协议，这里我们需要mock V2Header::parseHeader 方法
        // 创建有效的协议头数据示例，包含V2签名
        $this->validHeaderData = V2Header::SIG_DATA . // 12字节签名
            "\x21" . // 版本和命令(2)
            "\x11" . // 地址族和传输协议(TCP4)
            "\x00\x0C" . // 地址长度(12)
            "\xC0\xA8\x01\x01" . // 源地址(192.168.1.1)
            "\xC0\xA8\x01\x02" . // 目标地址(192.168.1.2)
            "\x30\x39" . // 源端口(12345)
            "\x00\x50"; // 目标端口(80)
    }

    public function testInputWithAlreadyParsedHeader(): void
    {
        // 先设置一个已解析的头部
        $header = $this->createMock(V2Header::class);
        HeaderMap::set($this->connection, $header);

        $data = "HTTP/1.1 200 OK\r\n";
        $result = ProxyProtocolV2::input($data, $this->connection);

        // 应该返回整个数据的长度
        $this->assertEquals(strlen($data), $result);
    }

    public function testInputWithTooShortData(): void
    {
        // 数据太短
        $shortData = V2Header::SIG_DATA . "\x21"; // 只有13字节，少于最小要求的16字节
        $result = ProxyProtocolV2::input($shortData, $this->connection);

        // 数据不完整，应该返回0表示需要更多数据
        $this->assertEquals(0, $result);
    }

    public function testInputWithInvalidSignature(): void
    {
        // 设置 connection 的期望行为
        $this->connection->expects($this->once())
            ->method('close');

        // 无效的签名
        $invalidSignatureData = str_repeat('X', 16); // 长度够但签名错误
        $result = ProxyProtocolV2::input($invalidSignatureData, $this->connection);

        // 无效签名应该关闭连接并返回0
        $this->assertEquals(0, $result);
    }

    public function testInputWithValidHeader(): void
    {
        // 有效的头部数据
        $result = ProxyProtocolV2::input($this->validHeaderData, $this->connection);

        // 应该返回头部的总长度
        $this->assertEquals(16 + 12, $result); // 16(固定部分) + 12(地址长度)
    }

    public function testDecodeFirstTime(): void
    {
        // 需要模拟 V2Header::parseHeader 方法
        // 这需要使用 PHPUnit 的静态方法模拟，但由于限制，我们在这里只测试代码分支

        $result = ProxyProtocolV2::decode($this->validHeaderData, $this->connection);

        // 成功解析后应该返回空字符串
        $this->assertEquals('', $result);

        // 验证是否存储了头部信息
        $this->assertTrue(HeaderMap::has($this->connection));
    }

    public function testDecodeAfterHeaderParsed(): void
    {
        // 先设置一个已解析的头部
        $header = $this->createMock(V2Header::class);
        HeaderMap::set($this->connection, $header);

        // 测试解析后收到的数据
        $data = "HTTP/1.1 200 OK\r\n";
        $result = ProxyProtocolV2::decode($data, $this->connection);

        // 应该原样返回数据
        $this->assertEquals($data, $result);
    }

    public function testEncode(): void
    {
        $data = "HTTP/1.1 200 OK\r\n";
        $result = ProxyProtocolV2::encode($data, $this->connection);

        // 应该原样返回数据
        $this->assertEquals($data, $result);
    }
}
