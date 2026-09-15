<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Receipt {{ $billing->receipt_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gray-100 p-6 text-gray-900">
    <main class="mx-auto max-w-2xl rounded-xl bg-white p-8 shadow-sm print:shadow-none">
        <div class="flex items-start justify-between border-b pb-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Professional Hospital</p>
                <h1 class="mt-1 text-2xl font-bold">Payment Receipt</h1>
            </div>
            <button type="button" onclick="window.print()" class="no-print rounded bg-gray-900 px-3 py-2 text-sm font-semibold text-white">Print</button>
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs uppercase text-gray-500">Receipt number</dt><dd class="font-semibold">{{ $billing->receipt_number }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Invoice number</dt><dd class="font-semibold">{{ $billing->invoice_number }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Patient</dt><dd>{{ $billing->patient?->name }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Admission</dt><dd>{{ $billing->admission?->admission_number }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Paid at</dt><dd>{{ $billing->paid_at?->format('d M Y H:i') }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Received by</dt><dd>{{ $billing->paidBy?->name ?? 'Unknown' }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Payment method</dt><dd class="capitalize">{{ str_replace('_', ' ', $billing->payment_method ?: 'other') }}</dd></div>
            <div><dt class="text-xs uppercase text-gray-500">Payment reference</dt><dd>{{ $billing->payment_reference }}</dd></div>
        </dl>

        <div class="mt-8 flex items-center justify-between border-y py-5">
            <div>
                <p class="text-xs uppercase text-gray-500">Charge</p>
                <p class="font-semibold">{{ ucfirst($billing->charge_type) }}: {{ $billing->description }}</p>
                @if($billing->payment_notes)
                    <p class="mt-1 text-xs text-gray-500">{{ $billing->payment_notes }}</p>
                @endif
            </div>
            <p class="text-right text-2xl font-bold">{{ number_format($billing->settledAmount(), 2) }}<span class="block text-xs font-normal text-gray-500">of {{ number_format((float) $billing->total_amount, 2) }} settled</span></p>
        </div>

        <p class="mt-6 text-xs text-gray-500">This receipt confirms payment against the inpatient invoice shown above.</p>
    </main>
</body>
</html>
