<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Entities;

class Payment {
    private ?int $id;
    private ?int $bookingId;
    private ?int $packagePurchaseId;
    private float $amount;
    private string $currency;
    private string $gateway;
    private ?string $transactionId;
    private string $status;
    private ?array $gatewayResponse;
    private array $meta;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        ?int $bookingId,
        ?int $packagePurchaseId,
        float $amount,
        string $currency,
        string $gateway,
        ?string $transactionId,
        string $status = 'pending'
    ) {
        $this->id = null;
        $this->bookingId = $bookingId;
        $this->packagePurchaseId = $packagePurchaseId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->gateway = $gateway;
        $this->transactionId = $transactionId;
        $this->status = $status;
        $this->gatewayResponse = null;
        $this->meta = [];
        $this->createdAt = null;
        $this->updatedAt = null;
    }

    public static function fromArray(array $data): self {
        $payment = new self(
            isset($data['booking_id']) ? (int) $data['booking_id'] : null,
            isset($data['package_purchase_id']) ? (int) $data['package_purchase_id'] : null,
            (float) $data['amount'],
            $data['currency'],
            $data['gateway'],
            $data['transaction_id'] ?? null,
            $data['status']
        );

        $payment->id = isset($data['id']) ? (int) $data['id'] : null;
        $payment->gatewayResponse = !empty($data['gateway_response']) ? json_decode($data['gateway_response'], true) : null;
        $payment->meta = !empty($data['meta']) ? json_decode($data['meta'], true) : [];
        
        if (isset($data['created_at'])) {
            $payment->createdAt = new \DateTime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $payment->updatedAt = new \DateTime($data['updated_at']);
        }

        return $payment;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'booking_id' => $this->bookingId,
            'package_purchase_id' => $this->packagePurchaseId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'transaction_id' => $this->transactionId,
            'status' => $this->status,
            'gateway_response' => json_encode($this->gatewayResponse),
            'meta' => json_encode($this->meta),
        ];
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getBookingId(): ?int {
        return $this->bookingId;
    }

    public function getPackagePurchaseId(): ?int {
        return $this->packagePurchaseId;
    }

    public function getAmount(): float {
        return $this->amount;
    }

    public function getCurrency(): string {
        return $this->currency;
    }

    public function getGateway(): string {
        return $this->gateway;
    }

    public function getTransactionId(): ?string {
        return $this->transactionId;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getGatewayResponse(): ?array {
        return $this->gatewayResponse;
    }

    public function getMeta(): array {
        return $this->meta;
    }

    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function setTransactionId(?string $transactionId): void {
        $this->transactionId = $transactionId;
    }

    public function setGatewayResponse(?array $gatewayResponse): void {
        $this->gatewayResponse = $gatewayResponse;
    }

    public function setMeta(array $meta): void {
        $this->meta = $meta;
    }
    
    public function setMetaValue(string $key, $value): void {
        $this->meta[$key] = $value;
    }
    
    public function getMetaValue(string $key, $default = null) {
        return $this->meta[$key] ?? $default;
    }

    // Helper methods
    public function isCompleted(): bool {
        return $this->status === 'completed';
    }

    public function isPending(): bool {
        return $this->status === 'pending';
    }

    public function isFailed(): bool {
        return $this->status === 'failed';
    }

    public function isRefund(): bool {
        return $this->amount < 0;
    }

    public function getFormattedAmount(): string {
        $symbol = $this->getCurrencySymbol();
        return $symbol . number_format(abs($this->amount), 2);
    }

    private function getCurrencySymbol(): string {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
        ];
        
        return $symbols[$this->currency] ?? $this->currency . ' ';
    }
}