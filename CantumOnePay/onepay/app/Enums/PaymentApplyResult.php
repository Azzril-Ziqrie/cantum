<?php

namespace App\Enums;

/** Hasil menerapkan kejadian pembayaran (webhook atau sync) ke pesanan. Nilainya dicatat di log webhook. */
enum PaymentApplyResult: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    /** id tagihan dari Kasera tidak sama dengan yang tersimpan: kejadian diabaikan. */
    case RefMismatch = 'ref_mismatch';
    /** nominal yang dibayar tidak sama dengan tagihan: tidak otomatis paid, ditandai untuk ditinjau. */
    case AmountMismatch = 'amount_mismatch';
}
