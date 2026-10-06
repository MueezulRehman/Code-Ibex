<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $order->order_number }}</title>
    @vite('resources/js/app.js')
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', DejaVu Sans, Arial, sans-serif;
            max-width: 420px;
            margin: 16px auto;
            color: #0f172a;
            font-size: 12.5px;
            background: #f8fafc;
            line-height: 1.45;
        }
        .sheet {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .brand {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 12px;
        }
        .brand h1 {
            font-size: 18px;
            margin: 0 0 4px;
            letter-spacing: 0.02em;
        }
        .brand p { margin: 0; color: #64748b; font-size: 11px; }
        .meta { width: 100%; margin: 0 0 10px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td:last-child { text-align: right; }
        .label { color: #64748b; font-size: 11px; }
        .divider { border-top: 1px dashed #cbd5e1; margin: 10px 0; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            padding: 4px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        table.items th:last-child, table.items td:last-child { text-align: right; }
        table.items td { padding: 7px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .item-name { font-weight: 600; }
        .item-sub { color: #64748b; font-size: 11px; }
        .totals { width: 100%; margin-top: 4px; }
        .totals td { padding: 3px 0; }
        .totals td:last-child { text-align: right; }
        .totals .grand td {
            font-size: 14px;
            font-weight: 800;
            padding-top: 8px;
            border-top: 2px solid #0f172a;
        }
        .footer {
            text-align: center;
            margin-top: 14px;
            color: #64748b;
            font-size: 11px;
        }
        .badge {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 10px;
            font-weight: 700;
        }
        .no-print { text-align: center; margin: 16px 0; }
        .no-print a, .no-print button {
            display: inline-block;
            margin: 4px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
        }
        .no-print .primary { background: #0f172a; color: #fff; border-color: #0f172a; }
        @media print {
            body { background: #fff; margin: 0; }
            .sheet { border: none; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="brand">
            <h1>{{ $restaurant->name ?? 'Receipt' }}</h1>
            <p>{{ $restaurant?->address ?? 'Thank you for your purchase' }}</p>
            @if($restaurant?->phone)
                <p>Phone: {{ $restaurant->phone }}</p>
            @endif
        </div>

        <table class="meta">
            <tr>
                <td>
                    <span class="label">Receipt</span><br>
                    <strong>{{ $order->invoice_number ?? $order->order_number }}</strong>
                </td>
                <td>
                    <span class="label">Date</span><br>
                    {{ $order->created_at->format('M d, Y h:i A') }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Order</span><br>
                    {{ $order->order_number }}
                </td>
                <td>
                    <span class="label">Payment</span><br>
                    {{ ucfirst($order->payment_method ?? 'cash') }}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Customer</span><br>
                    {{ $order->customer_name }}
                    @if($order->customer_phone && $order->customer_phone !== '0000000000')
                        · {{ $order->customer_phone }}
                    @endif
                    @if($order->table_number)
                        · Table {{ $order->table_number }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:58%">Item</th>
                    <th style="width:12%">Qty</th>
                    <th style="width:30%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->item_name }}</div>
                            @if($item->size_label)
                                <div class="item-sub">Size: {{ $item->size_label }}</div>
                            @endif
                            @foreach($item->toppings as $t)
                                <div class="item-sub">+ {{ $t->topping_name }}</div>
                            @endforeach
                            @if($item->unit_price)
                                <div class="item-sub">@ Rs. {{ number_format((float) $item->unit_price, 2) }}</div>
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td>Rs. {{ number_format((float) $item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td>Rs. {{ number_format((float) $order->subtotal, 2) }}</td>
            </tr>
            @if(($order->delivery_fee ?? 0) > 0)
                <tr>
                    <td>Delivery</td>
                    <td>Rs. {{ number_format((float) $order->delivery_fee, 2) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td>Total</td>
                <td>Rs. {{ number_format((float) $order->total, 2) }}</td>
            </tr>
            @if(!empty($order->notes))
                <tr>
                    <td colspan="2" style="text-align:left;color:#64748b;font-size:11px;">{{ $order->notes }}</td>
                </tr>
            @endif
            @if(($order->payment_method ?? '') === 'cash')
                <tr>
                    <td>Cash received</td>
                    <td>Rs. {{ number_format((float) ($order->amount_received ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>{{ ($order->change_amount ?? 0) >= 0 ? 'Change' : 'Balance due' }}</td>
                    <td>Rs. {{ number_format(abs((float) ($order->change_amount ?? 0)), 2) }}</td>
                </tr>
            @endif
        </table>

        <div class="footer">
            <p style="margin:0;">Thank you for your business!</p>
            <span class="badge">{{ $restaurant->name ?? 'Business' }} · Digital receipt</span>
        </div>
    </div>

    <div class="no-print">
        <button type="button" class="primary" id="share-receipt-pdf">Share PDF</button>
        <button type="button" id="download-receipt-pdf">Download PDF</button>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
        <a href="{{ route('manager.pos.index') }}">Back to POS</a>
        @if(!empty($customerShowUrl))
            <a href="{{ $customerShowUrl }}">Back to customer</a>
        @endif
        <p id="receipt-share-status" role="status" aria-live="polite"></p>
    </div>

    @php
        $receiptPdfData = [
            'business' => $restaurant->name ?? 'Receipt',
            'address' => $restaurant?->address,
            'phone' => $restaurant?->phone,
            'receiptNumber' => $order->invoice_number ?? $order->order_number,
            'orderNumber' => $order->order_number,
            'date' => $order->created_at->format('M d, Y h:i A'),
            'paymentMethod' => ucfirst($order->payment_method ?? 'cash'),
            'customer' => trim($order->customer_name . (($order->customer_phone && $order->customer_phone !== '0000000000') ? ' · ' . $order->customer_phone : '') . ($order->table_number ? ' · Table ' . $order->table_number : '')),
            'items' => $order->items->map(static function ($item) {
                $details = array_filter([
                    $item->size_label ? 'Size: ' . $item->size_label : null,
                    $item->toppings->pluck('topping_name')->filter()->implode(', ') ?: null,
                    $item->unit_price ? 'Unit: ' . number_format((float) $item->unit_price, 2) : null,
                ]);

                return [
                    'name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'total' => $item->total_price,
                    'details' => implode(' · ', $details),
                ];
            })->values(),
            'subtotal' => $order->subtotal,
            'deliveryFee' => $order->delivery_fee,
            'total' => $order->total,
            'notes' => $order->notes,
            'amountReceived' => $order->amount_received,
            'changeAmount' => $order->change_amount,
        ];
    @endphp
    <script id="receipt-pdf-data" type="application/json">@json($receiptPdfData)</script>

    <script>
        @if(request()->boolean('print'))
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 300);
            });
            window.onafterprint = function () {
                try { if (window.frameElement) window.frameElement.remove(); } catch (e) {}
            };
        @endif
    </script>
</body>
</html>
