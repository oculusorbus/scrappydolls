<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/shop/cart.php');
}

cart_reset_suggestions();
redirect('/shop/cart.php');
