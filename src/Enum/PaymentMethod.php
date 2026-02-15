<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case DIGITAL_WALLET = 'digital_wallet';
    case CASH = 'cash';
    case MERCADO_PAGO = 'mercado_pago';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::DIGITAL_WALLET => 'Digital Wallet',
            self::CASH => 'Cash',
            self::MERCADO_PAGO => 'Mercado Pago',
        };
    }
}
