<?php

namespace App\Enums;

enum PromotionType: string
{
    case BUNDLE_PAY_X_TAKE_Y = 'bundle_pay_x_take_y';
    case BUY_X_GET_Y = 'buy_x_get_y';
    case BUY_X_GET_DISCOUNT = 'buy_x_get_discount';
    case DIRECT_PERCENTAGE = 'direct_percentage';
    case STRIKETHROUGH_PRICE = 'strikethrough_price';
    case BUY_SKU_GET_GIFT_ITEM = 'buy_sku_get_gift_item';
    case BRAND_AMOUNT_CHOOSE_GIFT_ITEM = 'brand_amount_choose_gift_item';
    case BRAND_AMOUNT_GET_PRODUCT = 'brand_amount_get_product';

    public function label(): string
    {
        return match ($this) {
            self::BUNDLE_PAY_X_TAKE_Y => '2x1 / 3x2',
            self::BUY_X_GET_Y => 'Compra X y llévate Y',
            self::BUY_X_GET_DISCOUNT => 'Compra X y obtén % OFF',
            self::DIRECT_PERCENTAGE => 'Descuento directo',
            self::STRIKETHROUGH_PRICE => 'Precio tachado',
            self::BUY_SKU_GET_GIFT_ITEM => 'Compra SKU y recibe regalo',
            self::BRAND_AMOUNT_CHOOSE_GIFT_ITEM => 'Monto por marca y elige regalo',
            self::BRAND_AMOUNT_GET_PRODUCT => 'Monto por marca y recibe SKU',
        };
    }
}
