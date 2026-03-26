<?php

namespace App\Services;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    protected static array $gateways = [
        'knet' => KnetGatewayService::class,
        'paytabs' => PaytabsGatewayService::class,
        'myfatoorah' => MyFatoorahGatewayService::class,
    ];

    public static function make(string $gateway): PaymentGatewayInterface
    {
        if (!isset(self::$gateways[$gateway])) {
            throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}");
        }

        $gatewayClass = self::$gateways[$gateway];
        
        if (!class_exists($gatewayClass)) {
            throw new InvalidArgumentException("Gateway class not found: {$gatewayClass}");
        }

        return new $gatewayClass();
    }

    public static function supported(): array
    {
        return array_keys(self::$gateways);
    }

    public static function register(string $name, string $class): void
    {
        if (!in_array(PaymentGatewayInterface::class, class_implements($class) ?? [])) {
            throw new InvalidArgumentException("Gateway class must implement PaymentGatewayInterface");
        }
        self::$gateways[$name] = $class;
    }
}
