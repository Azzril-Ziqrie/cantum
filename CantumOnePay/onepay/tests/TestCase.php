<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Satu sesi = satu pengguna (AuthenticateSession menolak sesi yang hash kata sandinya berbeda).
     * Tes yang berganti pengguna di tengah jalan harus memulai sesi baru, seperti browser baru.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }
}
