<?php

namespace App\Enums;

/** Tahap penyiapan satu item di dapur atau bar. */
enum PrepStatus: string
{
    case Queued = 'queued';
    case Making = 'making';
    case Ready = 'ready';
}
