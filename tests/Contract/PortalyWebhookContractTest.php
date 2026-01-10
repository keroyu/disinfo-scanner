<?php

namespace Tests\Contract;

use Tests\TestCase;

/**
 * T011: Contract tests for Portaly webhook payload structure
 */
class PortalyWebhookContractTest extends TestCase
{
    /**
     * Valid webhook payload structure for testing.
     */
    protected function getValidPayload(): array
    {
        return [
            'data' => [
                'id' => 'test-order-001',
                'amount' => 190,
                'netTotal' => 171,
                'currency' => 'TWD',
                'productId' => '07eMToUCpzTcsg8zKSDM',
                'paymentMethod' => 'tappay',
                'createdAt' => '2024-01-31T07:42:32.151Z',
                'customerData' => [
                    'email' => 'user@example.com',
                    'name' => 'User Name',
                    'phone' => '0987654321',
                    'customFields' => [],
                ],
                'couponCode' => '',
                'discount' => 0,
                'feeAmount' => 19,
                'commissionAmount' => 0,
                'systemCommissionAmount' => 0,
                'taxFeeAmount' => 0,
            ],
            'event' => 'paid',
            'timestamp' => '2024-01-31T07:42:32.151Z',
        ];
    }

    public function test_valid_payload_has_required_data_fields(): void
    {
        $payload = $this->getValidPayload();

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('id', $payload['data']);
        $this->assertArrayHasKey('amount', $payload['data']);
        $this->assertArrayHasKey('productId', $payload['data']);
        $this->assertArrayHasKey('customerData', $payload['data']);
    }

    public function test_customer_data_has_required_fields(): void
    {
        $payload = $this->getValidPayload();
        $customerData = $payload['data']['customerData'];

        $this->assertArrayHasKey('email', $customerData);
        $this->assertArrayHasKey('name', $customerData);
    }

    public function test_event_field_is_paid_or_refund(): void
    {
        $payload = $this->getValidPayload();

        $this->assertContains($payload['event'], ['paid', 'refund']);
    }

    public function test_amount_is_positive_integer(): void
    {
        $payload = $this->getValidPayload();

        $this->assertIsInt($payload['data']['amount']);
        $this->assertGreaterThan(0, $payload['data']['amount']);
    }

    public function test_product_id_is_non_empty_string(): void
    {
        $payload = $this->getValidPayload();

        $this->assertIsString($payload['data']['productId']);
        $this->assertNotEmpty($payload['data']['productId']);
    }

    public function test_order_id_is_non_empty_string(): void
    {
        $payload = $this->getValidPayload();

        $this->assertIsString($payload['data']['id']);
        $this->assertNotEmpty($payload['data']['id']);
    }

    public function test_refund_payload_has_event_refund(): void
    {
        $payload = $this->getValidPayload();
        $payload['event'] = 'refund';

        $this->assertEquals('refund', $payload['event']);
    }
}
