<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Seed;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * A validated seed file. Records keep their tracezilla API field names; only
 * the identity keys and the purchase-order shorthand are seed-specific.
 */
final readonly class SeedDefinition
{
    public const VERSION = 1;

    /**
     * @param list<array<string, mixed>> $partners
     * @param list<array<string, mixed>> $skus
     * @param list<array<string, mixed>> $purchaseOrders
     */
    public function __construct(
        public array $partners,
        public array $skus,
        public array $purchaseOrders,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Seed file not found: {$path}");
        }

        $data = Yaml::parseFile($path);
        if (! is_array($data)) {
            throw new InvalidArgumentException("Seed file {$path} must contain a YAML mapping.");
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        $errors = [];

        if (($data['version'] ?? null) !== self::VERSION) {
            $errors[] = 'version must be '.self::VERSION.'.';
        }
        foreach (array_diff(array_keys($data), ['version', 'partners', 'skus', 'purchase_orders']) as $unknown) {
            $errors[] = "Unknown top-level key [{$unknown}].";
        }

        $partners = self::list($data, 'partners', $errors);
        $skus = self::list($data, 'skus', $errors);
        $purchaseOrders = self::list($data, 'purchase_orders', $errors);

        $partnerRefs = [];
        foreach ($partners as $index => $partner) {
            $at = "partners[{$index}]";
            self::requireFields($partner, ['ref', 'name', 'country'], $at, $errors);
            self::unique($partnerRefs, $partner['ref'] ?? null, $at, $errors);

            $locationRefs = [];
            foreach (self::list($partner, 'locations', $errors, $at) as $locationIndex => $location) {
                $locationAt = "{$at}.locations[{$locationIndex}]";
                self::requireFields($location, ['ref', 'name', 'country'], $locationAt, $errors);
                self::unique($locationRefs, $location['ref'] ?? null, $locationAt, $errors);
            }
        }

        $skuCodes = [];
        foreach ($skus as $index => $sku) {
            $at = "skus[{$index}]";
            self::requireFields($sku, ['sku_code', 'unit_of_measure', 'lot_unit', 'weight_factor_net', 'weight_factor_gross'], $at, $errors);
            self::unique($skuCodes, $sku['sku_code'] ?? null, $at, $errors);
        }

        $extRefs = [];
        foreach ($purchaseOrders as $index => $order) {
            $at = "purchase_orders[{$index}]";
            self::requireFields($order, ['ext_ref', 'supplier', 'deliver_to', 'currency', 'exchange_rate', 'lines'], $at, $errors);
            self::unique($extRefs, $order['ext_ref'] ?? null, $at, $errors);
            foreach (['supplier', 'deliver_to'] as $role) {
                if (isset($order[$role]) && ! self::isLocationReference($order[$role])) {
                    $errors[] = "{$at}.{$role} must be { partner: <ref>, location: <ref> }.";
                }
            }
            if (isset($order['receive']) && ! is_bool($order['receive'])) {
                $errors[] = "{$at}.receive must be true or false.";
            }
            foreach (self::list($order, 'lines', $errors, $at) as $lineIndex => $line) {
                self::requireFields($line, ['sku_code', 'quantity'], "{$at}.lines[{$lineIndex}]", $errors);
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException("Invalid seed definition:\n- ".implode("\n- ", $errors));
        }

        return new self($partners, $skus, $purchaseOrders);
    }

    /** @return list<array<string, mixed>> */
    private static function list(array $data, string $key, array &$errors, string $at = ''): array
    {
        $value = $data[$key] ?? [];
        $path = ltrim("{$at}.{$key}", '.');

        if (! is_array($value) || ! array_is_list($value)) {
            $errors[] = "{$path} must be a list.";

            return [];
        }
        foreach ($value as $index => $item) {
            if (! is_array($item) || array_is_list($item)) {
                $errors[] = "{$path}[{$index}] must be a mapping.";

                return [];
            }
        }

        return $value;
    }

    private static function requireFields(array $record, array $fields, string $at, array &$errors): void
    {
        foreach ($fields as $field) {
            $value = $record[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '') || $value === []) {
                $errors[] = "{$at}.{$field} is required.";
            }
        }
    }

    private static function unique(array &$seen, mixed $key, string $at, array &$errors): void
    {
        if (! is_scalar($key)) {
            return;
        }
        if (isset($seen[(string) $key])) {
            $errors[] = "{$at} repeats [{$key}].";
        }
        $seen[(string) $key] = true;
    }

    private static function isLocationReference(mixed $value): bool
    {
        return is_array($value)
            && is_string($value['partner'] ?? null) && $value['partner'] !== ''
            && is_string($value['location'] ?? null) && $value['location'] !== ''
            && count($value) === 2;
    }
}
