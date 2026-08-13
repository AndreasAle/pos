<?php

namespace App\Exceptions;

use Exception;

/**
 * A balance movement was refused — insufficient funds, or a credit/debit that
 * would double-apply. Always thrown from inside a DB transaction so the caller
 * gets a clean rollback.
 */
class BalanceException extends Exception
{
}
