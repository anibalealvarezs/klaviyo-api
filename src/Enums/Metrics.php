<?php

namespace Anibalealvarezs\KlaviyoApi\Enums;

enum Metrics: string
{
    case clicked_email = 'Clicked Email';
    case opened_email = 'Opened Email';
    case checkout_completed = 'Checkout Completed';
    case placed_order = 'Placed Order';
    case ordered_product = 'Ordered Product';
    case bounced_email = 'Bounced Email';
    case refunded_order = 'Refunded Order';
    case subscribed_to_list = 'Subscribed to List';
    case unsubscribed_from_list = 'Unsubscribed from List';
    case marked_email_as_spam = 'Marked Email as Spam';
    case received_email = 'Received Email';
   
    public static function fromValue(string $name): self
    {
        return match ($name) {
            self::clicked_email->value => self::clicked_email,
            self::opened_email->value => self::opened_email,
            self::checkout_completed->value => self::checkout_completed,
            self::placed_order->value => self::placed_order,
            self::ordered_product->value => self::ordered_product,
            self::bounced_email->value => self::bounced_email,
            self::refunded_order->value => self::refunded_order,
            self::subscribed_to_list->value => self::subscribed_to_list,
            self::unsubscribed_from_list->value => self::unsubscribed_from_list,
            self::marked_email_as_spam->value => self::marked_email_as_spam,
            self::received_email->value => self::received_email,
        };
    }

    public function getMeasurements(): array
    {
        return match ($this) {
            self::clicked_email,
            self::opened_email,
            self::bounced_email,
            self::subscribed_to_list,
            self::unsubscribed_from_list,
            self::marked_email_as_spam,
            self::received_email => [
                AggregatedMeasurement::count,
                AggregatedMeasurement::unique,
            ],
            self::checkout_completed,
            self::placed_order,
            self::refunded_order => [
                AggregatedMeasurement::count,
                AggregatedMeasurement::sum_value,
            ],
            self::ordered_product => [
                AggregatedMeasurement::count,
                AggregatedMeasurement::sum_value,
                AggregatedMeasurement::unique,
            ],
        };
    }
}
