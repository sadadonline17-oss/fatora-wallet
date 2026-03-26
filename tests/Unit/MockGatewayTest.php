<?php

namespace Tests\Unit;

use App\Models\Topup;
use App\Services\Gateways\MockGateway;
use PHPUnit\Framework\TestCase;

class MockGatewayTest extends TestCase
{
    private MockGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new MockGateway();
    }

    public function test_mock_gateway_returns_mock_checkout_url(): void
    {
        $topup = new Topup();
        $topup->id = 1;
        $topup->amount = 100.000;
        $topup->currency = 'KWD';

        $result = $this->gateway->createCheckout($topup);

        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('payment_id', $result);
        $this->assertStringContainsString('mock', $result['payment_id']);
    }

    public function test_mock_gateway_always_verifies(): void
    {
        $this->assertTrue($this->gateway->verifyCallback(['test' => 'data']));
    }

    public function test_mock_gateway_returns_success_on_parse(): void
    {
        $response = $this->gateway->parseCallback(['mock_id' => 'test123']);

        $this->assertTrue($response['success']);
        $this->assertEquals('00', $response['result_code']);
    }

    public function test_mock_gateway_refund_always_succeeds(): void
    {
        $result = $this->gateway->refund('test_ref', 50.000);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('MOCK_REFUND', $result['refund_id']);
    }
}
