<?php

namespace Anibalealvarezs\KlaviyoApi\Enums;

enum AggregatedMeasurement: string
{
    case count = 'count';
    case unique = 'unique';
    case sum_value = 'sum_value';
   
    public static function fromName(string $name)
    {
        return constant("self::$name") ?? null;
    }

    public function getLabel(): string|null
    {
        return match ($this) {
            self::count => 'Count events',
            self::unique => 'Unique customers',
            self::sum_value => 'Sum Value',
        };
    }
}
