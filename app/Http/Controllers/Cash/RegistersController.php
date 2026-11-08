<?php

namespace App\Http\Controllers\Cash;

use App\Queries\Cash\CashRegisterRows;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-609 and RP-610, the Owner's cash registers (ROUTE_CONTRACTS section 2; COPY_DECK sections 11.9
 * and 11.10; TASK-027 scope item 4): the registers with their OPEN-session state, and the form of a
 * new register or of one register, with whether a session is OPEN on it, which UXP-610 shows as its
 * reason (UXS-13; BR-CASH-02). Each takes no parameter but its path (TR-HTTP-02).
 */
final class RegistersController
{
    public function index(CashRegisterRows $registers): Response
    {
        return Inertia::render('cash/Registers', ['registers' => $registers->list()]);
    }

    public function create(): Response
    {
        return Inertia::render('cash/RegisterForm', ['register' => null]);
    }

    public function edit(string $register, CashRegisterRows $registers): Response
    {
        return Inertia::render('cash/RegisterForm', ['register' => $registers->one($register)]);
    }
}
