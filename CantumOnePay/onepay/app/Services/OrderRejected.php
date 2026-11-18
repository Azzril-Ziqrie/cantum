<?php

namespace App\Services;

use RuntimeException;

/** Pesanan atau aksi ditolak dengan alasan yang aman ditampilkan ke pengguna. */
class OrderRejected extends RuntimeException
{
}
