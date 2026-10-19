<?php

namespace App\Support\Time;

/**
 * The report periods of BR-COST-37, by the `period` values of ROUTE_CONTRACTS
 * RP-502: Hari Ini, 7 Hari, Bulan Ini, Bulan Lalu and Rentang Kustom.
 */
enum PeriodKind: string
{
    case Today = 'today';
    case SevenDays = '7d';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case Custom = 'custom';
}
