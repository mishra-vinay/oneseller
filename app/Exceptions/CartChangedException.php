<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown at checkout when stock or availability changed since the buyer looked at the cart.
 */
class CartChangedException extends RuntimeException {}
