<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Seed;

interface SeedGateway
{
    /**
     * Existing partners that have a ref, with their locations that have a ref.
     *
     * @return array<string, array{id: string, locations: array<string, string>}>
     */
    public function partnersByRef(): array;

    /** @return array<string, true> */
    public function skuCodes(): array;

    public function purchaseOrderExists(string $extRef): bool;

    /** @return string The new partner ID. */
    public function createPartner(array $payload): string;

    /** @return string The new location ID. */
    public function createLocation(string $partnerId, array $payload): string;

    public function createSku(array $payload): void;

    public function createPurchaseOrder(array $payload): void;
}
