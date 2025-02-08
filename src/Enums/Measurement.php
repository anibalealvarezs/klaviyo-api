<?php

namespace Anibalealvarezs\KlaviyoApi\Enums;

enum Measurement: string
{
    case count = 'count';
    case unique = 'unique';
    case sum = 'sum';
    case value = 'value';
}
