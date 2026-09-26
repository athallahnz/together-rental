<?php

/** SQL smoke test, standalone PDO SQLite (no Laravel or UAT database). */
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY, booking_id INTEGER, status TEXT, direction TEXT, type TEXT, amount NUMERIC)');
$pdo->exec('CREATE TABLE refunds (id INTEGER PRIMARY KEY, payment_id INTEGER, status TEXT, amount NUMERIC)');
$insertPayment = $pdo->prepare('INSERT INTO payments VALUES (?, ?, ?, ?, ?, ?)');
$insertRefund = $pdo->prepare('INSERT INTO refunds VALUES (?, ?, ?, ?)');
foreach ([
    [1, 10, 'completed', 'in', 'rental', 150000],
    [2, 10, 'completed', 'in', 'deposit', 100000],
    [3, 10, 'void', 'in', 'rental', 90000],
    [4, 10, 'completed', 'out', 'rental', 30000],
    [5, 11, 'completed', 'in', 'deposit', 200000],
    [6, 12, 'completed', 'in', 'rental', 100000],
    [7, 12, 'completed', 'in', 'rental', 50000],
] as $payment) {
    $insertPayment->execute($payment);
}
foreach ([
    [1, 1, 'paid', 50000],
    [2, 1, 'approved', 30000],
    [3, 2, 'paid', 20000],
    [4, 6, 'paid', 80000],
    [5, 6, 'paid', 40000],
    [6, 7, 'requested', 15000],
] as $refund) {
    $insertRefund->execute($refund);
}
$sql = "SELECT payments.booking_id,
    SUM(CASE WHEN payments.amount > COALESCE(paid_refunds.refunded_amount, 0)
        THEN payments.amount - COALESCE(paid_refunds.refunded_amount, 0) ELSE 0 END) AS rental_paid
    FROM payments AS payments
    LEFT JOIN (SELECT payment_id, SUM(amount) AS refunded_amount FROM refunds WHERE status = 'paid'
        GROUP BY payment_id) AS paid_refunds ON paid_refunds.payment_id = payments.id
    WHERE payments.booking_id IN (10, 11, 12)
      AND payments.status = 'completed' AND payments.direction = 'in' AND payments.type = 'rental'
    GROUP BY payments.booking_id";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
if (round((float) $rows[10], 2) !== 100000.0
    || isset($rows[11])
    || round((float) $rows[12], 2) !== 50000.0) {
    throw new RuntimeException('SQL smoke test FAILED: '.json_encode($rows));
}
echo "STAGE 6A BOOKING REPORT R1 SQL SMOKE PASS: DP net=100000; deposit-only=0; multiple paid refunds clamped=50000.\n";
