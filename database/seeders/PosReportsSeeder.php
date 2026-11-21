<?php

namespace Database\Seeders;

use App\Models\Report;
use Illuminate\Database\Seeder;

class PosReportsSeeder extends Seeder
{
    public function run(): void
    {
        Report::query()->where('name', 'like', 'POS · %')->delete();

        $periods = [
            'actual' => [
                'label' => 'mes actual',
                'sales' => "s.sold_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND s.sold_at < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')",
                'sessions' => "rs.closed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND rs.closed_at < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')",
            ],
            'previous' => [
                'label' => 'mes anterior',
                'sales' => "s.sold_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
  AND s.sold_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')",
                'sessions' => "rs.closed_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
  AND rs.closed_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            ],
        ];

        $templates = [
            [
                'base_order' => 10,
                'title' => 'Ventas',
                'background' => '#E8F1F8',
                'color' => '#2A4F73',
                'sql' => static fn (string $salesWhere): string => <<<SQL
SELECT
  s.id AS venta,
  DATE_FORMAT(s.sold_at, '%d/%m/%Y %H:%i') AS fecha,
  p.name AS sede,
  st.name AS caja,
  u.name AS cajero,
  COALESCE(
    NULLIF(TRIM(c.legal_name), ''),
    NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.first_last_name, c.second_last_name)), ''),
    'Mostrador'
  ) AS cliente,
  COALESCE(
    (
      SELECT GROUP_CONCAT(
        CONCAT(
          CASE sp.payment_method
            WHEN 'cash' THEN 'Efectivo'
            WHEN 'card' THEN 'Tarjeta'
            WHEN 'transfer' THEN 'Transferencia'
            ELSE sp.payment_method
          END,
          ' ',
          FORMAT(sp.amount, 0)
        )
        ORDER BY sp.sort_order SEPARATOR ' + '
      )
      FROM sale_payments sp
      WHERE sp.sale_id = s.id
    ),
    CASE s.payment_method
      WHEN 'cash' THEN 'Efectivo'
      WHEN 'card' THEN 'Tarjeta'
      WHEN 'transfer' THEN 'Transferencia'
      WHEN 'mixed' THEN 'Mixto'
      ELSE s.payment_method
    END
  ) AS pago,
  CASE s.status
    WHEN 'cancelled' THEN 'Anulada'
    WHEN 'partially_returned' THEN 'Con devolución'
    ELSE 'Completada'
  END AS estado,
  ROUND(s.subtotal, 0) AS _subtotal,
  ROUND(s.discount_amount, 0) AS _descuento,
  ROUND(s.tax_total, 0) AS _impuestos,
  ROUND(s.total, 0) AS _total_bruto,
  ROUND(COALESCE(s.returned_total, 0), 0) AS _devoluciones,
  ROUND(s.total - COALESCE(s.returned_total, 0), 0) AS _total_neto,
  ROUND(
    (s.total - COALESCE(s.returned_total, 0))
    - (s.cost_total - COALESCE(s.returned_cost_total, 0)),
    0
  ) AS _utilidad
FROM sales s
LEFT JOIN places p ON p.id = s.place_id
LEFT JOIN stations st ON st.id = s.station_id
LEFT JOIN users u ON u.id = s.user_id
LEFT JOIN customers c ON c.id = s.customer_id
WHERE {$salesWhere}
ORDER BY s.sold_at DESC, s.id DESC
SQL,
            ],
            [
                'base_order' => 20,
                'title' => 'Ventas por producto',
                'background' => '#EEF8F0',
                'color' => '#1B5E20',
                'sql' => static fn (string $salesWhere): string => <<<SQL
SELECT
  p.name AS sede,
  pr.name AS producto,
  COALESCE(pp.name, '—') AS presentacion,
  ROUND(SUM(sl.qty - COALESCE(sl.qty_returned, 0)), 2) AS cantidad,
  ROUND(SUM(
    sl.line_total * ((sl.qty - COALESCE(sl.qty_returned, 0)) / NULLIF(sl.qty, 0))
  ), 0) AS _total_vendido,
  ROUND(SUM(
    sl.cost_total * ((sl.qty - COALESCE(sl.qty_returned, 0)) / NULLIF(sl.qty, 0))
  ), 0) AS _costo,
  ROUND(SUM(
    (sl.line_total - sl.cost_total) * ((sl.qty - COALESCE(sl.qty_returned, 0)) / NULLIF(sl.qty, 0))
  ), 0) AS _utilidad
FROM sale_lines sl
INNER JOIN sales s ON s.id = sl.sale_id
INNER JOIN products pr ON pr.id = sl.product_id
LEFT JOIN product_presentations pp ON pp.id = sl.product_presentation_id
LEFT JOIN places p ON p.id = s.place_id
WHERE s.status IN ('completed', 'partially_returned')
  AND {$salesWhere}
GROUP BY p.name, pr.name, pp.name
ORDER BY _total_vendido DESC, producto ASC
SQL,
            ],
            [
                'base_order' => 30,
                'title' => 'Ventas por cajero',
                'background' => '#FFF8E7',
                'color' => '#8A5A00',
                'sql' => static fn (string $salesWhere): string => <<<SQL
SELECT
  p.name AS sede,
  u.name AS cajero,
  COUNT(*) AS ventas,
  SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) AS anuladas,
  SUM(CASE WHEN s.status = 'partially_returned' THEN 1 ELSE 0 END) AS con_devolucion,
  ROUND(SUM(
    CASE
      WHEN s.status IN ('completed', 'partially_returned')
        THEN s.total - COALESCE(s.returned_total, 0)
      ELSE 0
    END
  ), 0) AS _total_neto,
  ROUND(SUM(
    CASE
      WHEN s.status IN ('completed', 'partially_returned')
        THEN (s.total - COALESCE(s.returned_total, 0))
             - (s.cost_total - COALESCE(s.returned_cost_total, 0))
      ELSE 0
    END
  ), 0) AS _utilidad
FROM sales s
LEFT JOIN places p ON p.id = s.place_id
LEFT JOIN users u ON u.id = s.user_id
WHERE {$salesWhere}
GROUP BY p.name, u.name
ORDER BY _total_neto DESC, cajero ASC
SQL,
            ],
            [
                'base_order' => 40,
                'title' => 'Ventas por forma de pago',
                'background' => '#F3EEF9',
                'color' => '#5E35B1',
                'sql' => static fn (string $salesWhere): string => <<<SQL
SELECT
  p.name AS sede,
  CASE x.payment_method
    WHEN 'cash' THEN 'Efectivo'
    WHEN 'card' THEN 'Tarjeta'
    WHEN 'transfer' THEN 'Transferencia'
    ELSE COALESCE(x.payment_method, 'Sin dato')
  END AS forma_pago,
  COUNT(DISTINCT x.sale_id) AS ventas,
  ROUND(SUM(x.amount), 0) AS _total_neto
FROM (
  SELECT
    s.place_id,
    s.id AS sale_id,
    sp.payment_method,
    sp.amount
  FROM sale_payments sp
  INNER JOIN sales s ON s.id = sp.sale_id
  WHERE s.status IN ('completed', 'partially_returned')
    AND {$salesWhere}

  UNION ALL

  SELECT
    s.place_id,
    s.id AS sale_id,
    srp.payment_method,
    -srp.amount AS amount
  FROM sale_return_payments srp
  INNER JOIN sale_returns sr ON sr.id = srp.sale_return_id
  INNER JOIN sales s ON s.id = sr.sale_id
  WHERE s.status IN ('completed', 'partially_returned')
    AND {$salesWhere}
) x
LEFT JOIN places p ON p.id = x.place_id
GROUP BY p.name, x.payment_method
ORDER BY p.name ASC, _total_neto DESC
SQL,
            ],
            [
                'base_order' => 50,
                'title' => 'Cierres de caja',
                'background' => '#E8F5F5',
                'color' => '#00695C',
                'sql' => static fn (string $salesWhere, string $sessionsWhere): string => <<<SQL
SELECT
  p.name AS sede,
  st.name AS caja,
  u.name AS cajero,
  DATE_FORMAT(rs.opened_at, '%d/%m/%Y %H:%i') AS apertura,
  DATE_FORMAT(rs.closed_at, '%d/%m/%Y %H:%i') AS cierre,
  ROUND(rs.opening_cash, 0) AS _base,
  ROUND(COALESCE(rs.cash_sales_total, 0), 0) AS _ventas_efectivo,
  ROUND(COALESCE(rs.expected_cash, 0), 0) AS _efectivo_esperado,
  ROUND(COALESCE(rs.closing_cash, 0), 0) AS _efectivo_contado,
  ROUND(COALESCE(rs.difference_cash, 0), 0) AS _diferencia,
  ROUND(
    COALESCE(rs.cash_sales_total, 0)
    + COALESCE(rs.card_sales_total, 0)
    + COALESCE(rs.transfer_sales_total, 0),
    0
  ) AS _total_turno
FROM register_sessions rs
LEFT JOIN places p ON p.id = rs.place_id
LEFT JOIN stations st ON st.id = rs.station_id
LEFT JOIN users u ON u.id = rs.user_id
WHERE rs.status = 'closed'
  AND {$sessionsWhere}
ORDER BY rs.closed_at DESC
SQL,
            ],
            [
                'base_order' => 60,
                'title' => 'Top productos',
                'background' => '#FDECEE',
                'color' => '#B71C1C',
                'sql' => static fn (string $salesWhere): string => <<<SQL
SELECT
  p.name AS sede,
  pr.name AS producto,
  ROUND(SUM(sl.qty - COALESCE(sl.qty_returned, 0)), 2) AS unidades,
  ROUND(SUM(
    sl.line_total * ((sl.qty - COALESCE(sl.qty_returned, 0)) / NULLIF(sl.qty, 0))
  ), 0) AS _total_vendido,
  ROUND(SUM(
    (sl.line_total - sl.cost_total) * ((sl.qty - COALESCE(sl.qty_returned, 0)) / NULLIF(sl.qty, 0))
  ), 0) AS _utilidad
FROM sale_lines sl
INNER JOIN sales s ON s.id = sl.sale_id
INNER JOIN products pr ON pr.id = sl.product_id
LEFT JOIN places p ON p.id = s.place_id
WHERE s.status IN ('completed', 'partially_returned')
  AND {$salesWhere}
GROUP BY p.name, pr.name
ORDER BY _total_vendido DESC
LIMIT 50
SQL,
            ],
        ];

        foreach ($templates as $template) {
            $offset = 0;
            foreach ($periods as $period) {
                $sqlBuilder = $template['sql'];
                $sql = $sqlBuilder($period['sales'], $period['sessions']);

                Report::updateOrCreate(
                    ['name' => "POS · {$template['title']} ({$period['label']})"],
                    [
                        'sql' => $sql,
                        'order' => $template['base_order'] + $offset,
                        'permission' => 'reports.list',
                        'background' => $template['background'],
                        'color' => $template['color'],
                    ]
                );

                $offset++;
            }
        }
    }
}
