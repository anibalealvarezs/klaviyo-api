<?php

namespace Anibalealvarezs\KlaviyoApi\Enums;

enum Interval: string
{
    case hour = 'hour';
    case day = 'day';
    case week = 'week';
    case month = 'month';
    case year = 'year';
    case lifetime = 'lifetime';
}
