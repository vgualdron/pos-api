<?php

namespace Database\Seeders;

use App\Models\Report;
use Illuminate\Database\Seeder;

class PosReportsSeeder extends Seeder
{
    public function run(): void
    {
        $reports = [
            [
                'name' => 'POS · Ventas del día',
                'order' => 10,
                'permission' => 'reports.list',
                'background' => '#E8F1F8',
                'color' => '#2A4F73',
                'sql' => <<<'SQL'
SELECT
  s.id AS venta,
  DATE_FORMAT(s.sold_at, '%Y-%m-%d %H:%i') AS fecha,
  p.name AS sede,
  st.name AS caja,
  u.name AS cajero,
  COALESCE(
    NULLIF(TRIM(c.legal_name), ''),
    NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.first_last_name, c.second_last_name)), ''),
    'Mostrador'
  ) AS cliente,
  CASE s.payment_method
    WHEN 'cash' THEN 'Efectivo'
    WHEN 'card' THEN 'Tarjeta'
    WHEN 'transfer' THEN 'Transferencia'
    ELSE s.payment_method
  END AS pago,
  CASE s.status WHEN 'cancelled' THEN 'Anulada' ELSE 'Completada' END AS estado,
  ROUND(s.subtotal, 0) AS subtotal,
  ROUND(s.discount_amount, 0) AS descuento,
  ROUND(s.tax_total, 0) AS impuestos,
  ROUND(s.total, 0) AS total,
  ROUND(s.total - s.cost_total, 0) AS utilidad
FROM sales s
LEFT JOIN places p ON p.id = s.place_id
LEFT JOIN stations st ON st.id = s.station_id
LEFT JOIN users u ON u.id = s.user_id
LEFT JOIN customers c ON c.id = s.customer_id
WHERE DATE(s.sold_at) = CURDATE()
ORDER BY s.sold_at DESC, s.id DESC
SQL,
            ],
            [
                'name' => 'POS · Ventas por producto (7 días)',
                'order' => 20,
                'permission' => 'reports.list',
                'background' => '#EEF8F0',
                'color' => '#1B5E20',
                'sql' => <<<'SQL'
SELECT
  p.name AS sede,
  pr.name AS producto,
  COALESCE(pp.name, '—') AS presentacion,
  ROUND(SUM(sl.qty), 2) AS cantidad,
  ROUND(SUM(sl.line_total), 0) AS total_vendido,
  ROUND(SUM(sl.cost_total), 0) AS costo,
  ROUND(SUM(sl.line_total - sl.cost_total), 0) AS utilidad
FROM sale_lines sl
INNER JOIN sales s ON s.id = sl.sale_id
INNER JOIN products pr ON pr.id = sl.product_id
LEFT JOIN product_presentations pp ON pp.id = sl.product_presentation_id
LEFT JOIN places p ON p.id = s.place_id
WHERE s.status = 'completed'
  AND s.sold_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY p.name, pr.name, pp.name
ORDER BY total_vendido DESC, producto ASC
SQL,
            ],
            [
                'name' => 'POS · Ventas por cajero (7 días)',
                'order' => 30,
                'permission' => 'reports.list',
                'background' => '#FFF8E7',
                'color' => '#8A5A00',
                'sql' => <<<'SQL'
SELECT
  p.name AS sede,
  u.name AS cajero,
  COUNT(*) AS ventas,
  SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) AS anuladas,
  ROUND(SUM(CASE WHEN s.status = 'completed' THEN s.total ELSE 0 END), 0) AS total_vendido,
  ROUND(SUM(CASE WHEN s.status = 'completed' THEN s.total - s.cost_total ELSE 0 END), 0) AS utilidad
FROM sales s
LEFT JOIN places p ON p.id = s.place_id
LEFT JOIN users u ON u.id = s.user_id
WHERE s.sold_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY p.name, u.name
ORDER BY total_vendido DESC, cajero ASC
SQL,
            ],
            [
                'name' => 'POS · Ventas por forma de pago (7 días)',
                'order' => 40,
                'permission' => 'reports.list',
                'background' => '#F3EEF9',
                'color' => '#5E35B1',
                'sql' => <<<'SQL'
SELECT
  p.name AS sede,
  CASE s.payment_method
    WHEN 'cash' THEN 'Efectivo'
    WHEN 'card' THEN 'Tarjeta'
    WHEN 'transfer' THEN 'Transferencia'
    ELSE COALESCE(s.payment_method, 'Sin dato')
  END AS forma_pago,
  COUNT(*) AS ventas,
  ROUND(SUM(s.total), 0) AS total_vendido
FROM sales s
LEFT JOIN places p ON p.id = s.place_id
WHERE s.status = 'completed'
  AND s.sold_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY p.name, s.payment_method
ORDER BY p.name ASC, total_vendido DESC
SQL,
            ],
            [
                'name' => 'POS · Cierres de caja (7 días)',
                'order' => 50,
                'permission' => 'reports.list',
                'background' => '#E8F5F5',
                'color' => '#00695C',
                'sql' => <<<'SQL'
SELECT
  p.name AS sede,
  st.name AS caja,
  u.name AS cajero,
  DATE_FORMAT(rs.opened_at, '%Y-%m-%d %H:%i') AS apertura,
  DATE_FORMAT(rs.closed_at, '%Y-%m-%d %H:%i') AS cierre,
  ROUND(rs.opening_cash, 0) AS base,
  ROUND(COALESCE(rs.cash_sales_total, 0), 0) AS ventas_efectivo,
  ROUND(COALESCE(rs.expected_cash, 0), 0) AS efectivo_esperado,
  ROUND(COALESCE(rs.closing_cash, 0), 0) AS efectivo_contado,
  ROUND(COALESCE(rs.difference_cash, 0), 0) AS diferencia,
  ROUND(
    COALESCE(rs.cash_sales_total, 0)
    + COALESCE(rs.card_sales_total, 0)
    + COALESCE(rs.transfer_sales_total, 0),
    0
  ) AS total_turno
FROM register_sessions rs
LEFT JOIN places p ON p.id = rs.place_id
LEFT JOIN stations st ON st.id = rs.station_id
LEFT JOIN users u ON u.id = rs.user_id
WHERE rs.status = 'closed'
  AND rs.closed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
ORDER BY rs.closed_at DESC
SQL,
            ],
            [
                'name' => 'POS · Top productos (30 días)',
                'order' => 60,
                'permission' => 'reports.list',
                'background' => '#FDECEE',
                'color' => '#B71C1C',
                'sql' => <<<'SQL'
SELECT
  p.name AS sede,
  pr.name AS producto,
  ROUND(SUM(sl.qty), 2) AS unidades,
  ROUND(SUM(sl.line_total), 0) AS total_vendido,
  ROUND(SUM(sl.line_total - sl.cost_total), 0) AS utilidad
FROM sale_lines sl
INNER JOIN sales s ON s.id = sl.sale_id
INNER JOIN products pr ON pr.id = sl.product_id
LEFT JOIN places p ON p.id = s.place_id
WHERE s.status = 'completed'
  AND s.sold_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
GROUP BY p.name, pr.name
ORDER BY total_vendido DESC
LIMIT 50
SQL,
            ],
        ];

        foreach ($reports as $row) {
            Report::updateOrCreate(
                ['name' => $row['name']],
                [
                    'sql' => $row['sql'],
                    'order' => $row['order'],
                    'permission' => $row['permission'],
                    'background' => $row['background'],
                    'color' => $row['color'],
                ]
            );
        }
    }
}
