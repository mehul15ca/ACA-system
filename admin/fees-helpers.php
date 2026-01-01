<?php
function aca_generate_invoice_no(mysqli $conn, string $due_date): string {
    $ts = strtotime($due_date);
    if (!$ts) {
        $ts = time();
    }
    $mm = date('m', $ts); // 01-12
    $yy = date('y', $ts); // 25
    $prefix = "ACA-INV-" . $mm . $yy . "-";

    $like = $prefix . "%";
    $sql = "SELECT invoice_no FROM fees_invoices WHERE invoice_no LIKE ? ORDER BY invoice_no DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $next = 1;
    if ($row = $res->fetch_assoc()) {
        $last = $row['invoice_no'];
        $pos  = strrpos($last, "-");
        if ($pos !== false) {
            $num = substr($last, $pos + 1);
            if (ctype_digit($num)) {
                $next = intval($num) + 1;
            }
        }
    }
    return $prefix . str_pad((string)$next, 3, "0", STR_PAD_LEFT);
}

function aca_month_period_for_date(string $date): array {
    $ts = strtotime($date);
    if (!$ts) $ts = time();
    $y = date('Y', $ts);
    $m = date('m', $ts);
    $first = date('Y-m-01', $ts);
    $last  = date('Y-m-t', $ts);
    return [$first, $last];
}
